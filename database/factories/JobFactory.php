<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Job;
use App\Models\PracticeArea;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_code' => 'JOB-TEST-'.Str::upper(Str::random(8)),
            'country_id' => Country::factory(),
            'practice_area_id' => PracticeArea::factory(),
            'created_by' => User::factory(),
            'job_title' => fake()->jobTitle(),
            'total_experience' => 5,
            'relevant_experience' => 3,
            'no_of_positions' => 1,
            'priority' => 'medium',
            'status_id' => fn () => Status::idFor(Job::STATUS_MODULE, 'open'),
        ];
    }
}
