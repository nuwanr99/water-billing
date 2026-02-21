<?php

namespace Database\Seeders;

use App\Models\BillingCategory;
use Illuminate\Database\Seeder;

class BillingCategorySeeder extends Seeder
{
    /**
     * The default tariffs per billing category. Slabs are stored as
     * contiguous half-open ranges (lower, upper]; the late fee is a monthly
     * surcharge on overdue balances.
     *
     * @var array<string, array{description: string, tiers: list<array{int, int|null, float, float}>}>
     */
    protected array $categories = [
        'Domestic' => [
            'description' => 'Household water connections',
            'tiers' => [
                // [lower_units, upper_units, rate_per_unit, service_charge]
                [0, 5, 60.00, 300.00],
                [5, 10, 80.00, 300.00],
                [10, 15, 100.00, 300.00],
                [15, 20, 110.00, 400.00],
                [20, 25, 130.00, 500.00],
                [25, 30, 160.00, 600.00],
                [30, 40, 180.00, 1500.00],
                [40, 50, 210.00, 3000.00],
                [50, 75, 240.00, 3500.00],
                [75, 100, 270.00, 4000.00],
                [100, null, 300.00, 4500.00],
            ],
        ],
        'Business' => [
            'description' => 'Commercial premises and shops',
            'tiers' => [
                [0, 25, 150.00, 500.00],
                [25, 50, 150.00, 750.00],
                [50, 75, 150.00, 1500.00],
                [75, 100, 150.00, 1750.00],
                [100, 200, 150.00, 2000.00],
                [200, 500, 150.00, 3000.00],
                [500, 1000, 150.00, 5000.00],
                [1000, 2000, 150.00, 10000.00],
                [2000, 4000, 150.00, 15000.00],
                [4000, 10000, 150.00, 30000.00],
                [10000, 20000, 150.00, 60000.00],
                [20000, null, 150.00, 130000.00],
            ],
        ],
        'Industrial' => [
            'description' => 'Industrial water connections',
            'tiers' => [
                [0, 25, 110.00, 500.00],
                [25, 50, 110.00, 750.00],
                [50, 75, 110.00, 1500.00],
                [75, 100, 110.00, 1750.00],
                [100, 200, 110.00, 2000.00],
                [200, 500, 110.00, 3000.00],
                [500, 1000, 110.00, 5000.00],
                [1000, 2000, 110.00, 10000.00],
                [2000, 4000, 110.00, 15000.00],
                [4000, 10000, 110.00, 30000.00],
                [10000, 20000, 110.00, 60000.00],
                [20000, null, 110.00, 130000.00],
            ],
        ],
    ];

    /**
     * Seed the billing categories and their tariff slabs.
     *
     * Runs only against an empty table: categories and slabs are managed
     * through the admin UI afterwards, so re-seeding must never overwrite
     * edited tariffs (it would delete and recreate every slab).
     */
    public function run(): void
    {
        if (BillingCategory::query()->exists()) {
            return;
        }

        foreach ($this->categories as $name => $definition) {
            $category = BillingCategory::updateOrCreate(
                ['name' => $name],
                ['description' => $definition['description'], 'late_fee_percent' => 2.50, 'is_active' => true],
            );

            $category->tiers()->delete();

            $category->tiers()->createMany(array_map(
                fn (array $tier): array => [
                    'lower_units' => $tier[0],
                    'upper_units' => $tier[1],
                    'rate_per_unit' => $tier[2],
                    'service_charge' => $tier[3],
                ],
                $definition['tiers'],
            ));
        }
    }
}
