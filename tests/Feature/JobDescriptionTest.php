<?php

use App\Models\Client;
use App\Models\Country;
use App\Models\Job;
use App\Models\PracticeArea;
use App\Models\Role;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

const SAMPLE_JD = <<<'TXT'
Senior Java Developer

Location: Bengaluru, India
Experience: 5-8 years
Relevant Experience: 4 years
Employment Type: Full-time
Work Mode: Hybrid
No. of Positions: 3
Budget: 18 - 24 LPA
Contract Duration: 12 months

Responsibilities:
• Design and build Spring Boot microservices
• Review code and mentor juniors

Must have:
• Java, Spring Boot
• Microservices / REST APIs

Good to have: Kafka, Docker
TXT;

function asHr(): User
{
    $user = User::factory()->role(Role::HR)->create();
    Sanctum::actingAs($user);

    return $user;
}

/** Minimal valid DOCX with the given paragraphs (bullet = list paragraph). */
function makeDocx(array $paragraphs): string
{
    $body = '';
    foreach ($paragraphs as $text) {
        $isBullet = str_starts_with($text, '- ');
        $runs = '<w:r><w:t xml:space="preserve">'.htmlspecialchars($isBullet ? substr($text, 2) : $text).'</w:t></w:r>';
        $body .= '<w:p>'.($isBullet ? '<w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>' : '').$runs.'</w:p>';
    }

    $path = tempnam(sys_get_temp_dir(), 'jd').'.docx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
    $zip->close();

    return $path;
}

/** Minimal valid XLSX: each row is [label, value]. */
function makeXlsx(array $rows): string
{
    $strings = [];
    $sheetRows = '';
    foreach ($rows as $r => $cells) {
        $sheetRows .= '<row r="'.($r + 1).'">';
        foreach ($cells as $c => $value) {
            $strings[] = $value;
            $sheetRows .= '<c r="'.chr(65 + $c).($r + 1).'" t="s"><v>'.(count($strings) - 1).'</v></c>';
        }
        $sheetRows .= '</row>';
    }

    $path = tempnam(sys_get_temp_dir(), 'jd').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="JD" sheetId="1"/></sheets></workbook>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s).'</t></si>', $strings)).'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    $zip->close();

    return $path;
}

/** Tiny text-based PDF (one line per Tj). */
function makePdf(array $lines): string
{
    $content = "BT /F1 11 Tf 50 780 Td 14 TL\n".implode("\n", array_map(fn ($l) => '('.addcslashes($l, '()\\').') Tj T*', $lines))."\nET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n".implode('', array_map(fn ($o) => sprintf("%010d 00000 n \n", $o), $offsets));
    $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    $path = tempnam(sys_get_temp_dir(), 'jd').'.pdf';
    file_put_contents($path, $pdf);

    return $path;
}

test('a text JD fills title, experience, skills, location, mode, type, positions and budget', function () {
    asHr();
    $india = Country::factory()->create(['name' => 'India', 'code' => 'IN', 'iso3_code' => 'IND']);
    $java = PracticeArea::factory()->create(['name' => 'Java & Microservices', 'code' => 'JAVA']);

    $response = $this->post('/api/v1/jobs/parse-jd', ['file' => UploadedFile::fake()->createWithContent('jd.txt', SAMPLE_JD)], ['Accept' => 'application/json'])
        ->assertOk();

    $fields = $response->json('data.fields');
    expect($fields)->toMatchArray([
        'job_title' => 'Senior Java Developer',
        'total_experience' => 5,
        'relevant_experience' => 4,
        'work_location' => 'Bengaluru, India',
        'work_mode' => 'hybrid',
        'employment_type' => 'full_time',
        'no_of_positions' => 3,
        'budget_min' => 1800000,
        'budget_max' => 2400000,
        'contract_duration' => '12 months',
        'country_id' => $india->id,
        'practice_area_id' => $java->id,
    ])
        ->and($fields['primary_skills'])->toBe(['Java', 'Spring Boot', 'Microservices', 'REST APIs'])
        ->and($fields['secondary_skills'])->toBe(['Kafka', 'Docker']);

    $html = $response->json('data.job_description');
    expect($html)->toContain('<h3>Responsibilities</h3>')->toContain('<li>Design and build Spring Boot microservices</li>');
});

test('a Word (.docx) JD is read, including bullet lists', function () {
    asHr();
    $path = makeDocx(['Data Engineer', 'Experience: 3+ years', 'Key Skills:', '- Python', '- Apache Spark', 'Work from home']);

    $response = $this->post('/api/v1/jobs/parse-jd', ['file' => new UploadedFile($path, 'Data Engineer JD.docx', null, null, true)], ['Accept' => 'application/json'])
        ->assertOk();

    expect($response->json('data.fields'))->toMatchArray(['job_title' => 'Data Engineer', 'total_experience' => 3, 'work_mode' => 'remote'])
        ->and($response->json('data.fields.primary_skills'))->toBe(['Python', 'Apache Spark'])
        ->and($response->json('data.job_description'))->toContain('<li>Python</li>');
});

test('an Excel (.xlsx) JD with label / value rows is read', function () {
    asHr();
    $client = Client::factory()->create(['name' => 'Northwind Technologies']);
    $path = makeXlsx([
        ['Job Title', 'DevOps Lead'],
        ['Client', 'Northwind Technologies'],
        ['Experience', '8 - 12 yrs'],
        ['Employment Type', 'Contract to hire'],
        ['Mandatory Skills', 'Kubernetes, Terraform, AWS'],
        ['Openings', '2'],
    ]);

    $fields = $this->post('/api/v1/jobs/parse-jd', ['file' => new UploadedFile($path, 'jd.xlsx', null, null, true)], ['Accept' => 'application/json'])
        ->assertOk()
        ->json('data.fields');

    expect($fields)->toMatchArray([
        'job_title' => 'DevOps Lead',
        'client_id' => $client->id,
        'total_experience' => 8,
        'employment_type' => 'c2h',
        'no_of_positions' => 2,
        'primary_skills' => ['Kubernetes', 'Terraform', 'AWS'],
    ]);
});

test('a text-based PDF JD is read', function () {
    asHr();
    $path = makePdf(['QA Automation Engineer', 'Experience: 4 years', 'Primary Skills: Selenium, TestNG', 'Location: Hyderabad']);

    $fields = $this->post('/api/v1/jobs/parse-jd', ['file' => new UploadedFile($path, 'qa.pdf', null, null, true)], ['Accept' => 'application/json'])
        ->assertOk()
        ->json('data.fields');

    expect($fields['job_title'])->toBe('QA Automation Engineer')
        ->and($fields['total_experience'])->toEqual(4)
        ->and($fields['work_location'])->toBe('Hyderabad')
        ->and($fields['primary_skills'])->toBe(['Selenium', 'TestNG']);
});

test('known skills are found anywhere when there is no skills section', function () {
    asHr();
    Skill::create(['name' => 'React']);
    Skill::create(['name' => 'Node.js']);
    Skill::create(['name' => 'Go']);

    $text = "Frontend Engineer\nYou will build React apps backed by Node.js services. Good communication is a must.";
    $fields = $this->post('/api/v1/jobs/parse-jd', ['file' => UploadedFile::fake()->createWithContent('jd.txt', $text)], ['Accept' => 'application/json'])
        ->json('data.fields');

    expect($fields['primary_skills'])->toEqualCanonicalizing(['React', 'Node.js']); // not "Go" inside "Good"
});

test('wrong file types, big files and empty files are rejected', function () {
    asHr();

    foreach ([
        UploadedFile::fake()->create('jd.exe', 10, 'application/x-msdownload'),
        UploadedFile::fake()->create('jd.doc', 10, 'application/msword'),
        UploadedFile::fake()->createWithContent('jd.txt', str_repeat('a', 6 * 1024 * 1024)),
    ] as $file) {
        $this->post('/api/v1/jobs/parse-jd', ['file' => $file], ['Accept' => 'application/json'])->assertJsonValidationErrors('file');
    }

    $this->post('/api/v1/jobs/parse-jd', ['file' => UploadedFile::fake()->createWithContent('jd.txt', '  ')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('a broken docx returns a clear 422, not a server error', function () {
    asHr();
    $path = tempnam(sys_get_temp_dir(), 'jd').'.docx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/other.xml', '<x/>');
    $zip->close();

    $this->post('/api/v1/jobs/parse-jd', ['file' => new UploadedFile($path, 'broken.docx', null, null, true)], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors('file');
});

test('the job description HTML is sanitized on save', function () {
    asHr();
    $job = Job::factory()->create();

    $this->putJson("/api/v1/jobs/{$job->id}", [
        'job_description' => '<p onclick="steal()">Build <strong>APIs</strong><script>alert(1)</script></p><a href="javascript:alert(1)">x</a><img src=x onerror=alert(1)><ul><li>Java</li></ul>',
    ])->assertOk();

    $saved = $job->fresh()->job_description;
    expect($saved)->toContain('<strong>APIs</strong>')->toContain('<li>Java</li>')
        ->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:')->not->toContain('onerror')->not->toContain('<img');

    $this->putJson("/api/v1/jobs/{$job->id}", ['job_description' => '<p>&nbsp;</p>'])->assertOk();
    expect($job->fresh()->job_description)->toBeNull();
});

test('budget currency defaults to the country currency', function () {
    asHr();
    $country = Country::factory()->create(['currency_code' => 'INR']);

    $this->postJson('/api/v1/jobs', [
        'job_title' => 'X', 'country_id' => $country->id, 'practice_area_id' => PracticeArea::factory()->create()->id, 'budget_min' => 100000,
    ])->assertCreated()->assertJsonPath('data.budget_currency_code', 'INR');
});

test('JD file can be attached, downloaded and removed; TAG Team can only download', function () {
    Storage::fake('local');
    asHr();
    $job = Job::factory()->create();

    $this->post("/api/v1/jobs/{$job->id}/jd-file", ['file' => UploadedFile::fake()->createWithContent('Java JD.txt', SAMPLE_JD)], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.jd_file.name', 'Java JD.txt');
    $path = $job->fresh()->jd_file_path;
    Storage::disk('local')->assertExists($path);
    expect($path)->not->toContain('Java JD'); // stored under a random name

    Sanctum::actingAs(User::factory()->role(Role::TAG_TEAM)->create());
    $this->get("/api/v1/jobs/{$job->id}/jd-file")->assertOk()->assertDownload('Java JD.txt');
    $this->post("/api/v1/jobs/{$job->id}/jd-file", ['file' => UploadedFile::fake()->createWithContent('x.txt', 'x')], ['Accept' => 'application/json'])->assertForbidden();

    asHr();
    $this->deleteJson("/api/v1/jobs/{$job->id}/jd-file")->assertOk();
    Storage::disk('local')->assertMissing($path);
    $this->getJson("/api/v1/jobs/{$job->id}/jd-file")->assertNotFound();
});
