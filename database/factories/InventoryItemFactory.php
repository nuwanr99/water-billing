<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'unit' => fake()->randomElement(['pcs', 'm', 'kg', 'roll', 'box']),
            'unit_rate' => fake()->randomFloat(2, 50, 5000),
            'quantity_in_stock' => fake()->numberBetween(0, 200),
            'reorder_level' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * An item at or below its reorder level.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity_in_stock' => 2,
            'reorder_level' => 5,
        ]);
    }
}
