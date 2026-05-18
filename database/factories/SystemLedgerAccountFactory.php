<?php

namespace Database\Factories;

use App\Enums\SystemLedgerAccountType;
use App\Models\SystemLedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemLedgerAccount>
 */
class SystemLedgerAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(SystemLedgerAccountType::cases()),
            'is_active' => true,
        ];
    }

    /**
     * An asset account — where money physically sits (cash box, bank).
     */
    public function asset(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SystemLedgerAccountType::Asset,
        ]);
    }

    /**
     * An expense-category account — what money was spent on.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SystemLedgerAccountType::Expense,
        ]);
    }
}
