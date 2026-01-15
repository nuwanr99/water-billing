<?php

namespace Database\Factories;

use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterReading>
 */
class MeterReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $readingDate = fake()->dateTimeBetween('-1 year');

        return [
            'water_account_id' => WaterAccount::factory(),
            'recorded_by' => User::factory(),
            'billing_month' => $readingDate->format('Y-m'),
            'reading_value' => fake()->randomFloat(2, 100, 5000),
            'consumption' => fake()->randomFloat(2, 5, 60),
            'reading_date' => $readingDate,
        ];
    }

    /**
     * Indicate that the reading belongs to the given billing month.
     */
    public function forMonth(string $billingMonth): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_month' => $billingMonth,
            'reading_date' => $billingMonth.'-05',
        ]);
    }
}
