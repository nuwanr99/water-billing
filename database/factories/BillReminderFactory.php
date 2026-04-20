<?php

namespace Database\Factories;

use App\Enums\BillReminderType;
use App\Models\BillReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bills have no factory (they are only written by BillGenerationService),
 * so callers must supply the bill_id.
 *
 * @extends Factory<BillReminder>
 */
class BillReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(BillReminderType::cases()),
            'sent_at' => now(),
        ];
    }
}
