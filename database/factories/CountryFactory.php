<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'code' => fake()->unique()->lexify('??'),
            'iso3_code' => strtoupper(fake()->unique()->lexify('???')),
            'currency_code' => fake()->currencyCode(),
            'dial_code' => '+'.fake()->numberBetween(1, 999),
            'timezone' => 'UTC',
            'status_id' => fn () => Status::idFor(Status::MODULE_GENERAL, 'active'),
        ];
    }
}
