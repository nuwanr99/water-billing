<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_number' => fake()->unique()->numerify('EXP-2026-#####'),
            'expense_date' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'category_account_id' => SystemLedgerAccount::factory()->expense(),
            'paid_from_account_id' => SystemLedgerAccount::factory()->asset(),
            'maintenance_job_id' => null,
            'description' => fake()->sentence(4),
            'recorded_by' => User::factory(),
            'system_ledger_entry_id' => null,
        ];
    }
}
