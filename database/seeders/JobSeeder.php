<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Country;
use App\Models\Job;
use App\Models\PracticeArea;
use App\Models\Role;
use App\Models\Skill;
use App\Models\Status;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * 5 sample jobs linked to the seeded masters. Safe to run more than once (matched by job title).
 * Codes are generated like real jobs: {ISO3}-{PRACTICE}-{00001}.
 */
class JobSeeder extends Seeder
{
    private const JOBS = [
        [
            'job_title' => 'Senior Java Developer', 'client' => 'Northwind Technologies Pvt Ltd',
            'country' => 'IN', 'practice' => 'JAVA', 'total_experience' => 6, 'relevant_experience' => 4,
            'budget_min' => 1800000, 'budget_max' => 2600000, 'budget_currency_code' => 'INR',
            'work_mode' => 'hybrid', 'work_location' => 'Bengaluru', 'employment_type' => 'full_time',
            'no_of_positions' => 3, 'priority' => 'high', 'notice_period_days' => 30, 'status' => 'open',
            'primary' => ['Java', 'Spring Boot', 'Microservices'], 'secondary' => ['Kafka', 'Docker'], 'vendors' => 2,
        ],
        [
            'job_title' => '.NET Core Engineer', 'client' => 'Bluepeak Systems Inc',
            'country' => 'US', 'practice' => 'DOTNET', 'total_experience' => 5, 'relevant_experience' => 3,
            'budget_min' => 95000, 'budget_max' => 120000, 'budget_currency_code' => 'USD',
            'work_mode' => 'remote', 'work_location' => 'New York (Remote)', 'employment_type' => 'contract',
            'no_of_positions' => 2, 'priority' => 'medium', 'notice_period_days' => 15, 'status' => 'open',
            'primary' => ['C#', '.NET Core', 'Azure'], 'secondary' => ['SQL Server'], 'vendors' => 1,
        ],
        [
            'job_title' => 'Data Engineer', 'client' => 'Harbourline Digital Ltd',
            'country' => 'GB', 'practice' => 'DATA', 'total_experience' => 4, 'relevant_experience' => 3,
            'budget_min' => 55000, 'budget_max' => 70000, 'budget_currency_code' => 'GBP',
            'work_mode' => 'onsite', 'work_location' => 'London', 'employment_type' => 'full_time',
            'no_of_positions' => 1, 'priority' => 'urgent', 'notice_period_days' => 30, 'status' => 'on_hold',
            'primary' => ['Python', 'Spark', 'SQL'], 'secondary' => ['Airflow', 'AWS'], 'vendors' => 1,
        ],
        [
            'job_title' => 'DevOps Lead', 'client' => 'Lionbridge Cloud Pte Ltd',
            'country' => 'SG', 'practice' => 'CLOUD', 'total_experience' => 8, 'relevant_experience' => 5,
            'budget_min' => 120000, 'budget_max' => 150000, 'budget_currency_code' => 'SGD',
            'work_mode' => 'hybrid', 'work_location' => 'Singapore', 'employment_type' => 'c2h',
            'no_of_positions' => 1, 'priority' => 'high', 'notice_period_days' => 60, 'status' => 'draft',
            'primary' => ['Kubernetes', 'Terraform', 'AWS'], 'secondary' => ['Jenkins', 'Prometheus'], 'vendors' => 0,
        ],
        [
            'job_title' => 'QA Automation Engineer', 'client' => null,
            'country' => 'IN', 'practice' => 'QA', 'total_experience' => 3, 'relevant_experience' => 2,
            'budget_min' => 800000, 'budget_max' => 1200000, 'budget_currency_code' => 'INR',
            'work_mode' => 'onsite', 'work_location' => 'Hyderabad', 'employment_type' => 'full_time',
            'no_of_positions' => 2, 'priority' => 'low', 'notice_period_days' => 30, 'status' => 'open',
            'primary' => ['Selenium', 'Java', 'TestNG'], 'secondary' => ['Postman', 'Jira'], 'vendors' => 0,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()->whereRelation('role', 'code', Role::HR)->oldest('id')->first();

        if (! $owner || ! Country::exists()) {
            $this->command?->warn('JobSeeder skipped: run HrAdminSeeder and MasterDataSeeder first.');

            return;
        }

        $vendorIds = Vendor::query()->oldest('id')->pluck('id');

        foreach (self::JOBS as $row) {
            $client = $row['client'] ? Client::where('name', $row['client'])->first() : null;

            $country = Country::where('code', $row['country'])->firstOrFail();
            $practiceArea = PracticeArea::where('code', $row['practice'])->firstOrFail();

            $job = Job::withTrashed()->firstOrNew(['job_title' => $row['job_title']]);
            $job->job_code ??= Job::nextCode($country, $practiceArea);
            $job->fill([
                'job_title' => $row['job_title'],
                'client_id' => $client?->id,
                'client_manager_id' => $client?->managers()->value('id'),
                'country_id' => $country->id,
                'practice_area_id' => $practiceArea->id,
                'requirement_date' => now()->addDays(20)->toDateString(),
                'contract_duration' => $row['employment_type'] === 'full_time' ? null : '12 months',
                'job_description' => "We are hiring a {$row['job_title']} for our {$row['work_location']} team. "
                    .'You will design, build and support production systems, work with product owners and mentor junior engineers.',
                ...array_intersect_key($row, array_flip([
                    'total_experience', 'relevant_experience', 'budget_min', 'budget_max', 'budget_currency_code',
                    'work_mode', 'work_location', 'employment_type', 'no_of_positions', 'priority', 'notice_period_days',
                ])),
            ]);
            $job->status_id = Status::idFor(Job::STATUS_MODULE, $row['status']);
            $job->created_by ??= $owner->id;
            $job->save();

            $skills = [];
            foreach (['primary', 'secondary'] as $type) {
                foreach ($row[$type] as $name) {
                    $skills[Skill::firstOrCreate(['name' => $name])->id] = ['skill_type' => $type];
                }
            }
            $job->skills()->sync($skills);
            $job->recruiters()->sync([$owner->id => ['is_primary' => true, 'assigned_at' => now()]]);
            $job->vendors()->sync($vendorIds->take($row['vendors'])->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all());
        }
    }
}
