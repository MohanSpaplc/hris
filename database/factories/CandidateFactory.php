<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_code' => 'CAN-TEST-'.Str::upper(Str::random(8)),
            'created_by' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('98########'),
            'current_designation' => fake()->jobTitle(),
            'total_experience' => 5,
            'relevant_experience' => 3,
            'status_id' => fn () => Status::idFor(Candidate::STATUS_MODULE, 'active'),
        ];
    }
}
