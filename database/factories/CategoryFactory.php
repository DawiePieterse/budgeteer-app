<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => fake()->unique()->word(),
            'kind' => CategoryKind::Expense,
        ];
    }
}
