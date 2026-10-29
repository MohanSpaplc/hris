<?php

namespace Database\Factories;

use App\Models\PracticeArea;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PracticeArea>
 */
class PracticeAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'code' => Str::upper(fake()->unique()->bothify('PA-###??')),
            'status_id' => fn () => Status::idFor(Status::MODULE_GENERAL, 'active'),
        ];
    }
}
