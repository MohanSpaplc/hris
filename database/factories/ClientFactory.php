<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Country;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'created_by' => User::factory(),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('+91 ##########'),
            'address' => fake()->address(),
            'status_id' => fn () => Status::idFor(Status::MODULE_GENERAL, 'active'),
        ];
    }
}
