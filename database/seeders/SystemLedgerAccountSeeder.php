<?php

namespace Database\Seeders;

use App\Enums\SystemLedgerAccountType;
use App\Models\SystemLedgerAccount;
use Illuminate\Database\Seeder;

class SystemLedgerAccountSeeder extends Seeder
{
    /**
     * Seed the minimal chart of accounts (D-19). Journals reference these
     * codes; Phase 4 (payments) and Phase 7 (expenses) are the writers.
     *
     * Runs only against an empty table: the chart is managed through the
     * admin UI afterwards, so re-seeding must never resurrect or fight
     * renamed, recoded, or deleted accounts.
     */
    public function run(): void
    {
        if (SystemLedgerAccount::query()->exists()) {
            return;
        }

        $accounts = [
            ['code' => '1000', 'name' => 'Cash on Hand', 'type' => SystemLedgerAccountType::Asset],
            ['code' => '1100', 'name' => 'Bank', 'type' => SystemLedgerAccountType::Asset],
            ['code' => '4000', 'name' => 'Water Charges Income', 'type' => SystemLedgerAccountType::Income],
            ['code' => '4100', 'name' => 'Penalties & Other Income', 'type' => SystemLedgerAccountType::Income],
            ['code' => '5000', 'name' => 'Maintenance Expense', 'type' => SystemLedgerAccountType::Expense],
            ['code' => '5900', 'name' => 'Other Expenses', 'type' => SystemLedgerAccountType::Expense],
        ];

        foreach ($accounts as $account) {
            SystemLedgerAccount::query()->firstOrCreate(
                ['code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type'], 'is_active' => true],
            );
        }
    }
}
