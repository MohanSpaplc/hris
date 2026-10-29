<?php

use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\HiringStage;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Role;
use App\Models\Skill;
use App\Models\Status;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function actAs(string $roleCode): User
{
    $user = User::factory()->role($roleCode)->create();
    Sanctum::actingAs($user);

    return $user;
}

test('TAG team can add a candidate with skills, educations and employments', function () {
    $recruiter = actAs(Role::TAG_TEAM);
    $vendor = Vendor::factory()->create();
    Skill::create(['name' => 'Java']);

    $response = $this->postJson('/api/v1/candidates', [
        'first_name' => 'Anita',
        'last_name' => 'Sharma',
        'education_details' => 'MCA',
        'email' => ' Anita.Sharma@Example.com ',
        'phone' => '+91 98765 43210',
        'total_experience' => 6,
        'relevant_experience' => 4,
        'current_ctc' => 1200000,
        'ctc_currency_code' => 'inr',
        'resume_source' => 'vendor',
        'vendor_id' => $vendor->id,
        'skills' => [
            ['name' => 'java', 'skill_type' => 'primary', 'experience_months' => 48],
            ['name' => ' Spring Boot ', 'skill_type' => 'secondary'],
        ],
        'educations' => [['qualification' => 'B.Tech', 'year_of_passing' => 2018, 'percentage_or_cgpa' => 8.2]],
        'employments' => [
            ['company_name' => 'Acme Corp', 'start_date' => '2021-06-01', 'is_current' => true],
            ['company_name' => 'Old Co', 'start_date' => '2018-07-01', 'end_date' => '2021-05-31'],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.candidate_code', 'CAN-00001')
        ->assertJsonPath('data.email', 'anita.sharma@example.com')
        ->assertJsonPath('data.ctc_currency_code', 'INR')
        ->assertJsonPath('data.education_details', 'MCA')
        ->assertJsonPath('data.status.code', 'active')
        ->assertJsonPath('data.vendor.id', $vendor->id)
        ->assertJsonPath('data.skills.0.name', 'Java') // "java" reused the existing "Java"
        ->assertJsonCount(1, 'data.educations')
        ->assertJsonPath('data.employments.0.company_name', 'Acme Corp');

    expect(Candidate::firstOrFail()->created_by)->toBe($recruiter->id)
        ->and(Skill::count())->toBe(2);
});

test('candidate codes keep counting after a delete', function () {
    actAs(Role::HR);

    $first = $this->postJson('/api/v1/candidates', ['first_name' => 'One'])->assertCreated()->json('data.id');
    $this->deleteJson("/api/v1/candidates/{$first}")->assertOk();

    $this->postJson('/api/v1/candidates', ['first_name' => 'Two'])->assertCreated()->assertJsonPath('data.candidate_code', 'CAN-00002');
});

test('a duplicate email or phone is rejected with the existing candidate code', function () {
    actAs(Role::HR);
    $existing = Candidate::factory()->create(['email' => 'anita@example.com', 'phone' => '9876543210']);

    $this->postJson('/api/v1/candidates', ['first_name' => 'Anita', 'email' => 'ANITA@example.com', 'phone' => '9876543210'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'phone'])
        ->assertJsonPath('errors.email.0', "A candidate with this email already exists ({$existing->candidate_code}).");

    // Saving the same candidate with its own email is fine.
    $this->putJson("/api/v1/candidates/{$existing->id}", ['email' => 'anita@example.com'])->assertOk();
});

test('candidate validation rules', function (array $payload, string $field) {
    actAs(Role::HR);

    $this->postJson('/api/v1/candidates', ['first_name' => 'Anita', ...$payload])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'relevant > total' => [['total_experience' => 2, 'relevant_experience' => 5], 'relevant_experience'],
    'vendor source without vendor' => [['resume_source' => 'vendor'], 'vendor_id'],
    'referral without name' => [['resume_source' => 'referral'], 'referral_name'],
    'two current jobs' => [['employments' => [['company_name' => 'A', 'is_current' => true], ['company_name' => 'B', 'is_current' => true]]], 'employments'],
    'current job with end date' => [['employments' => [['company_name' => 'A', 'is_current' => true, 'end_date' => '2024-01-01']]], 'employments.0.end_date'],
    'status of another module' => [['status_id' => fn () => Status::idFor('job', 'open')], 'status_id'],
]);

test('update replaces lists only when they are sent', function () {
    actAs(Role::HR);
    $id = $this->postJson('/api/v1/candidates', [
        'first_name' => 'Anita',
        'skills' => [['name' => 'Java', 'skill_type' => 'primary']],
        'educations' => [['qualification' => 'B.Tech'], ['qualification' => 'M.Tech']],
    ])->json('data.id');

    $this->putJson("/api/v1/candidates/{$id}", ['current_company' => 'Acme'])
        ->assertOk()
        ->assertJsonCount(1, 'data.skills')
        ->assertJsonCount(2, 'data.educations');

    $this->putJson("/api/v1/candidates/{$id}", ['educations' => [], 'status_id' => Status::idFor('candidate', 'blacklisted')])
        ->assertOk()
        ->assertJsonCount(0, 'data.educations')
        ->assertJsonPath('data.status.code', 'blacklisted');
});

test('list filters by search, skill and experience', function () {
    actAs(Role::TAG_TEAM);
    $java = Skill::create(['name' => 'Java']);
    $match = Candidate::factory()->create(['first_name' => 'Anita', 'last_name' => 'Sharma', 'total_experience' => 6]);
    $match->skills()->attach($java, ['skill_type' => 'primary']);
    Candidate::factory()->create(['first_name' => 'Anita', 'last_name' => 'Rao', 'total_experience' => 6]);
    Candidate::factory()->create(['first_name' => 'Ravi', 'total_experience' => 1])->skills()->attach($java, ['skill_type' => 'primary']);

    $this->getJson('/api/v1/candidates?search=anita sharma')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    $this->getJson('/api/v1/candidates?skill=Java&min_experience=3')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
});

test('TAG team cannot delete candidates', function () {
    actAs(Role::TAG_TEAM);

    $this->deleteJson('/api/v1/candidates/'.Candidate::factory()->create()->id)->assertForbidden();
});

test('a new resume becomes the next version and its text is searchable', function () {
    Storage::fake('local');
    actAs(Role::TAG_TEAM);
    $candidate = Candidate::factory()->create();
    $upload = fn (string $text) => $this->post("/api/v1/candidates/{$candidate->id}/documents", [
        'document_type' => 'resume',
        'file' => UploadedFile::fake()->createWithContent('resume.txt', $text),
    ], ['Accept' => 'application/json']);

    $first = $upload('Java developer with Kafka experience')->assertCreated()->assertJsonPath('data.version', 1)->json('data.id');
    $upload('Java developer with Kubernetes experience')->assertCreated()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.is_current', true)
        ->assertJsonPath('data.status.code', 'pending_verification');

    expect(CandidateDocument::find($first)->is_current)->toBeFalse();
    $this->getJson("/api/v1/candidates/{$candidate->id}")->assertJsonPath('data.resume.version', 2);
    $this->getJson('/api/v1/candidates?keyword=Kubernetes')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/candidates?keyword=Kafka')->assertJsonCount(0, 'data');
});

test('document type limits the allowed files', function () {
    Storage::fake('local');
    actAs(Role::HR);
    $candidate = Candidate::factory()->create();

    $this->post("/api/v1/candidates/{$candidate->id}/documents", [
        'document_type' => 'resume',
        'file' => UploadedFile::fake()->image('resume.png'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
});

test('documents can be verified, downloaded and deleted', function () {
    Storage::fake('local');
    $hr = actAs(Role::HR);
    $candidate = Candidate::factory()->create();
    $upload = fn () => $this->post("/api/v1/candidates/{$candidate->id}/documents", [
        'document_type' => 'resume',
        'file' => UploadedFile::fake()->createWithContent('resume.txt', 'Resume text'),
    ], ['Accept' => 'application/json'])->json('data.id');
    $first = $upload();
    $second = $upload();

    $this->patchJson("/api/v1/candidates/{$candidate->id}/documents/{$second}", ['status_id' => Status::idFor('document', 'verified')])
        ->assertOk()
        ->assertJsonPath('data.status.code', 'verified')
        ->assertJsonPath('data.verified_by.id', $hr->id);

    $this->get("/api/v1/candidates/{$candidate->id}/documents/{$second}")->assertOk()->assertDownload('resume.txt');

    // Deleting the current resume makes the previous version current again.
    $this->deleteJson("/api/v1/candidates/{$candidate->id}/documents/{$second}")->assertOk();
    expect(CandidateDocument::find($first)->is_current)->toBeTrue();
});

test('a document of another candidate is not found', function () {
    Storage::fake('local');
    actAs(Role::HR);
    $other = Candidate::factory()->create();
    $document = new CandidateDocument;
    $document->forceFill([
        'candidate_id' => $other->id,
        'uploaded_by' => User::factory()->create()->id,
        'document_type' => 'resume',
        'original_filename' => 'x.pdf',
        'file_path' => 'x.pdf',
        'status_id' => Status::idFor('document', 'pending_verification'),
    ])->save();

    $this->get('/api/v1/candidates/'.Candidate::factory()->create()->id."/documents/{$document->id}", ['Accept' => 'application/json'])->assertNotFound();
});

test('a candidate can be tagged to an open job at stage Sourced', function () {
    $hr = actAs(Role::HR);
    $job = Job::factory()->create(['status_id' => Status::idFor('job', 'open')]);

    $response = $this->postJson('/api/v1/candidates', ['first_name' => 'Anita', 'job_id' => $job->id, 'expected_ctc' => 1500000])
        ->assertCreated()
        ->assertJsonPath('data.applications.0.job.id', $job->id)
        ->assertJsonPath('data.applications.0.stage.code', 'sourced')
        ->assertJsonPath('data.applications.0.status.code', 'active')
        ->assertJsonPath('data.ctc_currency_code', $job->country->currency_code ?? 'INR'); // no currency field on the form

    $id = $response->json('data.id');
    $this->assertDatabaseHas('application_stage_history', ['to_stage_id' => HiringStage::idFor('sourced'), 'changed_by' => $hr->id]);

    // Saving again with the same job does not tag it twice.
    $this->putJson("/api/v1/candidates/{$id}", ['job_id' => $job->id])->assertOk()->assertJsonCount(1, 'data.applications');
});

test('a closed job cannot be selected', function () {
    actAs(Role::HR);
    $job = Job::factory()->create(['status_id' => Status::idFor('job', 'closed')]);

    $this->postJson('/api/v1/candidates', ['first_name' => 'Anita', 'job_id' => $job->id])->assertUnprocessable()->assertJsonValidationErrors('job_id');
});

test('CTC without a currency and without a job defaults to INR', function () {
    actAs(Role::HR);

    $this->postJson('/api/v1/candidates', ['first_name' => 'Anita', 'current_ctc' => 900000])->assertCreated()->assertJsonPath('data.ctc_currency_code', 'INR');
});

test('a resume is read into candidate form values', function () {
    actAs(Role::TAG_TEAM);
    Skill::create(['name' => 'Kafka']);
    $resume = <<<'TXT'
        ANITA SHARMA
        anita.sharma@example.com | +91 98765 43210
        Gender: Female
        Current Location: Hyderabad
        Preferred Location: Bengaluru
        Current Designation: Senior Java Developer
        Current Company: Acme Corp
        Total Experience: 6 years 6 months
        Relevant Experience: 4 years
        Current CTC: 12 LPA
        Expected CTC: 16.5 Lakhs
        Notice Period: 2 months
        Technical Skills: Java, Spring Boot, Microservices
        Other Skills: Docker, Java
        Worked with Kafka for event streaming.
        Education
        B.Tech in Computer Science, JNTU University, 2018 - 8.2 CGPA
        Intermediate (12th), Narayana College, 2014, 92%
        TXT;

    $fields = $this->post('/api/v1/candidates/parse-resume', ['file' => UploadedFile::fake()->createWithContent('resume.txt', $resume)], ['Accept' => 'application/json'])
        ->assertOk()
        ->json('data.fields');

    expect($fields)->toMatchArray([
        'first_name' => 'Anita',
        'last_name' => 'Sharma',
        'email' => 'anita.sharma@example.com',
        'phone' => '+91 98765 43210',
        'gender' => 'female',
        'current_location' => 'Hyderabad',
        'preferred_location' => 'Bengaluru',
        'current_designation' => 'Senior Java Developer',
        'current_company' => 'Acme Corp',
        'total_experience' => 6.5,
        'relevant_experience' => 4,
        'current_ctc' => 1200000,
        'expected_ctc' => 1650000,
        'notice_period_days' => 60,
        'primary_skills' => ['Java', 'Spring Boot', 'Microservices'],
        'secondary_skills' => ['Docker'],
    ])->and($fields['educations'][0])->toMatchArray(['qualification' => 'B.Tech', 'year_of_passing' => 2018, 'percentage_or_cgpa' => 8.2])
        ->and($fields['educations'][1])->toMatchArray(['qualification' => 'Intermediate (12th)', 'year_of_passing' => 2014, 'percentage_or_cgpa' => 92])
        ->and($fields['education_details'])->toBe('B.Tech'); // highest of B.Tech and 12th
});

test('a resume that is not a document is rejected', function () {
    actAs(Role::HR);

    $this->post('/api/v1/candidates/parse-resume', ['file' => UploadedFile::fake()->image('resume.png')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('jobs list the number of tagged resumes and candidates filter by job', function () {
    $hr = actAs(Role::HR);
    $job = Job::factory()->create();
    [$tagged, $other] = Candidate::factory()->count(2)->create();
    JobApplication::tag($tagged, $job, $hr);

    $this->getJson('/api/v1/jobs')->assertOk()->assertJsonPath('data.0.applications_count', 1);
    $this->getJson("/api/v1/candidates?job_id={$job->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tagged->id);
});
