<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'maintenance_job_id' => null,
            'movement_type' => StockMovementType::Purchase,
            'quantity' => fake()->numberBetween(1, 50),
            'unit_rate' => fake()->randomFloat(2, 50, 5000),
            'note' => fake()->optional()->sentence(4),
            'moved_by' => User::factory(),
            'moved_at' => now(),
        ];
    }
}
