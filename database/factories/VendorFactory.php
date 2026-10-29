<?php

namespace Database\Factories;

use App\Models\Status;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'name' => fake()->company().' Staffing',
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('+91 ##########'),
            'contact_person' => fake()->name(),
            'status_id' => fn () => Status::idFor(Status::MODULE_GENERAL, 'active'),
        ];
    }
}
