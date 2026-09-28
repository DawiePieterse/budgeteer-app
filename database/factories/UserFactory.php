<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
