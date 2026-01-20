<?php

namespace Database\Factories;

use App\Models\BillingCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingCategory>
 */
class BillingCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' tariff',
            'description' => fake()->optional()->sentence(),
            'late_fee_percent' => 2.50,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the billing category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
