<?php

namespace Database\Factories;

use App\Enums\WaterAccountStatus;
use App\Models\User;
use App\Models\WaterAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterAccount>
 */
class WaterAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_number' => 'ACC-'.fake()->unique()->numerify('####'),
            'meter_number' => 'MTR-'.fake()->unique()->numerify('######'),
            'initial_reading' => 0,
            'connection_address' => fake()->optional()->address(),
            'status' => WaterAccountStatus::Active,
            'connected_at' => fake()->dateTimeBetween('-5 years'),
        ];
    }

    /**
     * Indicate that the water account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WaterAccountStatus::Inactive,
        ]);
    }
}
