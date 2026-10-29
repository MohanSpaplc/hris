<?php

use App\Models\Client;
use App\Models\ClientManager;
use App\Models\Country;
use App\Models\Job;
use App\Models\PracticeArea;
use App\Models\Role;
use App\Models\Skill;
use App\Models\Status;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\JobAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function jobPayload(array $overrides = []): array
{
    return [
        'job_title' => 'Senior Java Developer',
        'country_id' => Country::factory()->create()->id,
        'practice_area_id' => PracticeArea::factory()->create()->id,
        ...$overrides,
    ];
}

function loginAs(string $roleCode): User
{
    $user = User::factory()->role($roleCode)->create();
    Sanctum::actingAs($user);

    return $user;
}

test('HR can create a job with skills, recruiters and vendors', function () {
    $hr = loginAs(Role::HR);
    $manager = ClientManager::factory()->create();
    $recruiter = User::factory()->create();
    $vendors = Vendor::factory()->count(2)->create();
    Skill::create(['name' => 'Java']);

    $response = $this->postJson('/api/v1/jobs', jobPayload([
        'client_id' => $manager->client_id,
        'client_manager_id' => $manager->id,
        'total_experience' => 6,
        'relevant_experience' => 4,
        'budget_min' => 1800000,
        'budget_max' => 2400000,
        'budget_currency_code' => 'inr',
        'work_mode' => 'hybrid',
        'employment_type' => 'full_time',
        'no_of_positions' => 2,
        'priority' => 'high',
        'primary_skills' => ['java', ' Spring Boot '],
        'secondary_skills' => ['Kafka'],
        'recruiter_ids' => [$hr->id, $recruiter->id],
        'primary_recruiter_id' => $recruiter->id,
        'vendor_ids' => $vendors->pluck('id')->all(),
    ]))->assertCreated();

    $job = Job::firstOrFail();
    expect($job->job_code)->toBe($job->country->iso3_code.'-'.$job->practiceArea->code.'-00001')
        ->and($job->created_by)->toBe($hr->id)
        ->and($job->status_id)->toBe(Status::idFor('job', 'draft'))
        ->and($job->budget_currency_code)->toBe('INR');

    $response->assertJsonPath('data.is_internal', false)
        ->assertJsonPath('data.status.code', 'draft')
        ->assertJsonPath('data.primary_skills', ['Java', 'Spring Boot']) // "java" reused the existing "Java"
        ->assertJsonPath('data.secondary_skills', ['Kafka'])
        ->assertJsonCount(2, 'data.vendors');

    expect(Skill::count())->toBe(3)
        ->and(collect($response->json('data.recruiters'))->firstWhere('is_primary', true)['id'])->toBe($recruiter->id);
});

test('internal job has no client and no client manager', function () {
    loginAs(Role::HR);
    $manager = ClientManager::factory()->create();

    $this->postJson('/api/v1/jobs', jobPayload())->assertCreated()->assertJsonPath('data.is_internal', true);

    $this->postJson('/api/v1/jobs', jobPayload(['client_manager_id' => $manager->id]))
        ->assertJsonValidationErrors('client_manager_id');
});

test('job validation checks related fields', function () {
    loginAs(Role::HR);
    $manager = ClientManager::factory()->create();
    $otherClient = Client::factory()->create();

    $this->postJson('/api/v1/jobs', jobPayload([
        'client_id' => $otherClient->id,
        'client_manager_id' => $manager->id,
        'total_experience' => 3,
        'relevant_experience' => 5,
        'budget_min' => 100,
        'budget_max' => 50,
        'budget_currency_code' => 'USD',
        'primary_skills' => ['Java'],
        'secondary_skills' => ['java'],
    ]))->assertJsonValidationErrors(['client_manager_id', 'relevant_experience', 'budget_max', 'secondary_skills']);

    $this->postJson('/api/v1/jobs', ['work_mode' => 'moon', 'priority' => 'asap', 'status_id' => Status::idFor('offer', 'accepted')])
        ->assertJsonValidationErrors(['job_title', 'country_id', 'practice_area_id', 'work_mode', 'priority', 'status_id']);
});

test('budget needs a currency when the country has none', function () {
    loginAs(Role::HR);
    $country = Country::factory()->create(['currency_code' => null]);

    $this->postJson('/api/v1/jobs', jobPayload(['country_id' => $country->id, 'budget_min' => 1000]))->assertJsonValidationErrors('budget_currency_code');
});

test('final status stamps closed_at and reopening clears it', function () {
    loginAs(Role::HR);
    $job = Job::factory()->create();

    $this->putJson("/api/v1/jobs/{$job->id}", ['status_id' => Status::idFor('job', 'closed'), 'closed_reason' => 'Client paused hiring'])
        ->assertOk()
        ->assertJsonPath('data.status.code', 'closed')
        ->assertJsonPath('data.closed_reason', 'Client paused hiring');
    expect($job->fresh()->closed_at)->not->toBeNull();

    $this->putJson("/api/v1/jobs/{$job->id}", ['status_id' => Status::idFor('job', 'open')])->assertOk();
    expect($job->fresh()->closed_at)->toBeNull();
});

test('partial update keeps fields and relations that are not sent', function () {
    loginAs(Role::HR);
    $job = Job::factory()->create(['job_title' => 'Old title', 'total_experience' => 8]);
    $job->skills()->attach(Skill::create(['name' => 'Go'])->id, ['skill_type' => 'primary']);

    $this->putJson("/api/v1/jobs/{$job->id}", ['relevant_experience' => 6])
        ->assertOk()
        ->assertJsonPath('data.job_title', 'Old title')
        ->assertJsonPath('data.primary_skills', ['Go']);

    // Uses the saved total (8) to check the new relevant experience.
    $this->putJson("/api/v1/jobs/{$job->id}", ['relevant_experience' => 9])->assertJsonValidationErrors('relevant_experience');
});

test('jobs can be filtered', function () {
    loginAs(Role::TAG_TEAM);
    $client = Client::factory()->create();
    Job::factory()->create(['job_title' => 'React Developer', 'client_id' => $client->id, 'country_id' => $client->country_id, 'priority' => 'urgent']);
    Job::factory()->create(['job_title' => 'Java Developer']);

    $this->getJson('/api/v1/jobs?search=react')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.job_title', 'React Developer');
    $this->getJson('/api/v1/jobs?job_type=internal')->assertJsonCount(1, 'data')->assertJsonPath('data.0.job_title', 'Java Developer');
    $this->getJson("/api/v1/jobs?client_id={$client->id}&priority=urgent")->assertJsonCount(1, 'data');
});

test('job permissions per role', function () {
    $job = Job::factory()->create();

    loginAs(Role::TAG_TEAM);
    $this->getJson('/api/v1/jobs')->assertOk();
    $this->postJson('/api/v1/jobs', jobPayload())->assertForbidden();
    $this->putJson("/api/v1/jobs/{$job->id}", ['job_title' => 'X'])->assertForbidden();
    $this->deleteJson("/api/v1/jobs/{$job->id}")->assertForbidden();

    loginAs(Role::TAG_MANAGER);
    $this->postJson('/api/v1/jobs', jobPayload())->assertCreated();
    $this->deleteJson("/api/v1/jobs/{$job->id}")->assertOk();
    expect($job->fresh()->trashed())->toBeTrue();
});

test('a database failure returns a safe 500 message and saves nothing', function () {
    loginAs(Role::HR);
    $payload = jobPayload(['primary_skills' => ['Java']]);

    // Make the skills insert fail inside the transaction.
    DB::statement('DROP TABLE job_skills');

    $this->postJson('/api/v1/jobs', $payload)
        ->assertStatus(500)
        ->assertExactJson(['message' => 'Could not create the job. Please try again.']);

    expect(Job::count())->toBe(0); // rolled back
});

test('skills autocomplete returns active skills matching the start of the name', function () {
    loginAs(Role::TAG_TEAM);
    Skill::create(['name' => 'Java']);
    Skill::create(['name' => 'JavaScript']);
    Skill::create(['name' => 'Python']);

    $this->getJson('/api/v1/skills?search=jav')->assertOk()->assertJsonCount(2, 'data');
});

test('job codes are numbered per country and practice area, like IND-SAP-00001', function () {
    loginAs(Role::HR);
    $india = Country::factory()->create(['code' => 'IN', 'iso3_code' => 'IND']);
    $usa = Country::factory()->create(['code' => 'US', 'iso3_code' => 'USA']);
    $sap = PracticeArea::factory()->create(['code' => 'SAP']);
    $java = PracticeArea::factory()->create(['code' => 'JAVA']);

    $create = fn ($country, $practice) => $this->postJson('/api/v1/jobs', [
        'job_title' => 'Consultant', 'country_id' => $country->id, 'practice_area_id' => $practice->id,
    ])->assertCreated()->json('data.job_code');

    expect($create($india, $sap))->toBe('IND-SAP-00001')
        ->and($create($india, $sap))->toBe('IND-SAP-00002')
        ->and($create($india, $java))->toBe('IND-JAVA-00001')
        ->and($create($usa, $sap))->toBe('USA-SAP-00001');

    // Deleted jobs keep their number; it is never reused.
    Job::where('job_code', 'IND-SAP-00002')->first()->delete();
    expect($create($india, $sap))->toBe('IND-SAP-00003');
});

test('job code uses the 2-letter code when a country has no ISO3 code', function () {
    loginAs(Role::HR);
    $country = Country::factory()->create(['code' => 'DE', 'iso3_code' => null]);
    $practice = PracticeArea::factory()->create(['code' => 'SAP_HANA']);

    $this->postJson('/api/v1/jobs', ['job_title' => 'X', 'country_id' => $country->id, 'practice_area_id' => $practice->id])
        ->assertCreated()
        ->assertJsonPath('data.job_code', 'DE-SAP_HANA-00001');
});

test('job code does not change when the job is edited', function () {
    loginAs(Role::HR);
    $india = Country::factory()->create(['iso3_code' => 'IND']);
    $practice = PracticeArea::factory()->create(['code' => 'SAP']);
    $code = $this->postJson('/api/v1/jobs', ['job_title' => 'X', 'country_id' => $india->id, 'practice_area_id' => $practice->id])->json('data.job_code');
    $job = Job::where('job_code', $code)->firstOrFail();

    $this->putJson("/api/v1/jobs/{$job->id}", ['practice_area_id' => PracticeArea::factory()->create()->id])
        ->assertOk()
        ->assertJsonPath('data.job_code', $code);
});

test('country iso3 code is validated and upper-cased', function () {
    loginAs(Role::HR);
    Country::factory()->create(['iso3_code' => 'IND']);

    $this->postJson('/api/v1/countries', ['name' => 'Germany', 'code' => 'DE', 'iso3_code' => 'deu'])
        ->assertCreated()
        ->assertJsonPath('data.iso3_code', 'DEU');
    $this->postJson('/api/v1/countries', ['name' => 'Indonesia', 'code' => 'ID', 'iso3_code' => 'IND'])
        ->assertJsonValidationErrors('iso3_code');
    $this->postJson('/api/v1/countries', ['name' => 'X', 'code' => 'XX', 'iso3_code' => 'X1'])
        ->assertJsonValidationErrors('iso3_code');
});

test('form options return all job dropdowns in one call, users only when allowed', function () {
    loginAs(Role::HR);
    Country::factory()->create(['name' => 'India']);
    Country::factory()->create(['name' => 'Aruba', 'status_id' => Status::idFor('general', 'inactive')]);

    $data = $this->getJson('/api/v1/jobs/form-options')->assertOk()->json('data');

    expect(array_keys($data))->toBe(['job_statuses', 'countries', 'practice_areas', 'clients', 'vendors', 'users'])
        ->and(collect($data['countries'])->pluck('name')->all())->toBe(['India']) // inactive excluded
        ->and(collect($data['job_statuses'])->pluck('code')->all())->toContain('draft', 'open', 'closed')
        ->and($data['users'])->not->toBeEmpty();

    loginAs(Role::TAG_TEAM);
    $this->getJson('/api/v1/jobs/form-options')->assertOk()->assertJsonPath('data.users', []);
});

test('newly assigned recruiters are emailed and the assignment is recorded', function () {
    Notification::fake();
    $hr = loginAs(Role::HR);
    [$first, $second, $third] = User::factory()->count(3)->create();

    $id = $this->postJson('/api/v1/jobs', jobPayload(['recruiter_ids' => [$first->id, $second->id], 'primary_recruiter_id' => $second->id]))
        ->assertCreated()
        ->json('data.id');

    Notification::assertSentTo($second, JobAssignedNotification::class, fn ($notification) => $notification->isPrimary && $notification->assignedBy->is($hr));
    Notification::assertSentTo($first, JobAssignedNotification::class, fn ($notification) => ! $notification->isPrimary);
    $this->assertDatabaseHas('job_recruiters', ['job_id' => $id, 'user_id' => $first->id, 'assigned_by' => $hr->id]);

    // Editing: only the newly added recruiter gets an email.
    $this->putJson("/api/v1/jobs/{$id}", ['recruiter_ids' => [$first->id, $second->id, $third->id], 'primary_recruiter_id' => $second->id])->assertOk();

    Notification::assertSentToTimes($first, JobAssignedNotification::class, 1);
    Notification::assertSentToTimes($third, JobAssignedNotification::class, 1);
});

test('assigned_to_me lists only the jobs of the logged-in recruiter', function () {
    $recruiter = loginAs(Role::TAG_TEAM);
    $mine = Job::factory()->create();
    $mine->recruiters()->attach($recruiter, ['is_primary' => true, 'assigned_at' => now()]);
    Job::factory()->create()->recruiters()->attach(User::factory()->create(), ['is_primary' => true, 'assigned_at' => now()]);

    $this->getJson('/api/v1/jobs?assigned_to_me=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
});

test('the job assigned email shows the job and a link to it', function () {
    $job = Job::factory()->create(['job_title' => 'Senior Java Developer']);
    $recruiter = User::factory()->create(['first_name' => 'Ravi']);

    $mail = (new JobAssignedNotification($job, User::factory()->create(), true))->toMail($recruiter);

    expect($mail->subject)->toBe("Job assigned: {$job->job_code} · Senior Java Developer")
        ->and($mail->greeting)->toBe('Hello Ravi,')
        ->and($mail->actionUrl)->toEndWith("/jobs/{$job->id}");
});

test('jobs can be filtered by posted date range', function () {
    loginAs(Role::TAG_TEAM);
    $inRange = Job::factory()->create(['created_at' => '2026-10-15 18:30:00']);
    Job::factory()->create(['created_at' => '2026-10-01 10:00:00']);
    Job::factory()->create(['created_at' => '2026-10-21 00:00:01']);

    $this->getJson('/api/v1/jobs?posted_from=2026-10-10&posted_to=2026-10-20')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inRange->id);
    $this->getJson('/api/v1/jobs?posted_from=2026-10-20&posted_to=2026-10-10')->assertUnprocessable()->assertJsonValidationErrors('posted_to');
});
