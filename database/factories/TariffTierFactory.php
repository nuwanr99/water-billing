<?php

namespace Database\Factories;

use App\Models\BillingCategory;
use App\Models\TariffTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TariffTier>
 */
class TariffTierFactory extends Factory
{
    /**
     * Define the model's default state: a single open-ended slab.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_category_id' => BillingCategory::factory(),
            'lower_units' => 0,
            'upper_units' => null,
            'rate_per_unit' => fake()->randomFloat(2, 20, 150),
            'service_charge' => fake()->randomFloat(2, 100, 500),
        ];
    }
}
