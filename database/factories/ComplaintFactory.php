<?php

namespace Database\Factories;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'complaint_number' => fake()->unique()->numerify('CMP-2026-#####'),
            'user_id' => User::factory(),
            'water_account_id' => null,
            'category' => fake()->randomElement(ComplaintCategory::cases()),
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => ComplaintStatus::Open,
            'submitted_at' => now(),
        ];
    }

    /**
     * Indicate the complaint is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ComplaintStatus::Closed,
            'closure_note' => fake()->sentence(),
            'closed_at' => now(),
        ]);
    }
}
