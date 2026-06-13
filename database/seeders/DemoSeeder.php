<?php

namespace Database\Seeders;

use App\Enums\ComplaintCategory;
use App\Enums\MaintenanceJobStatus;
use App\Enums\StockMovementType;
use App\Enums\WaterAccountStatus;
use App\Models\BillingCategory;
use App\Models\InventoryItem;
use App\Models\MeterReading;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use App\Services\BillGenerationService;
use App\Services\ComplaintService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\MaintenanceJobService;
use App\Services\PaymentService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;

/**
 * Six months of realistic society operations for demos: 20 members and 25
 * water accounts around Medamahanuwara, monthly readings and bills, mixed
 * payment behaviours (prompt, partial, late, defaulters), complaints with
 * maintenance jobs, expenses, and inventory — all written through the
 * domain services so both ledgers stay consistent.
 *
 * Run with: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /**
     * The demo members: [first name, last name, village].
     *
     * @var list<array{string, string, string}>
     */
    protected array $memberProfiles = [
        ['Nimal', 'Bandara', 'Galekale'],
        ['Sunil', 'Rathnayake', 'Galekale'],
        ['Kamala', 'Kumarihamy', 'Bombrawa'],
        ['Piyasena', 'Herath', 'Galekale'],
        ['Gunapala', 'Dissanayake', 'Bombrawa'],
        ['Somawathi', 'Menike', 'Galekale'],
        ['Ariyaratne', 'Ekanayake', 'Bombrawa'],
        ['Chandrasena', 'Wijekoon', 'Galekale'],
        ['Leelawathi', 'Bandara', 'Bombrawa'],
        ['Premadasa', 'Tennakoon', 'Galekale'],
        ['Ranjith', 'Weerasekara', 'Bombrawa'],
        ['Wimalawathi', 'Rajapaksha', 'Galekale'],
        ['Jayasena', 'Mudiyanselage', 'Bombrawa'],
        ['Podimenike', 'Dissanayake', 'Galekale'],
        ['Karunaratne', 'Galagoda', 'Bombrawa'],
        ['Seetha', 'Amarakoon', 'Galekale'],
        ['Dharmasena', 'Wickramasinghe', 'Bombrawa'],
        ['Mallika', 'Senanayake', 'Galekale'],
        ['Upali', 'Abeykoon', 'Bombrawa'],
        ['Swarna', 'Halangoda', 'Galekale'],
    ];

    /**
     * Members (by index) who hold a second water account.
     *
     * @var list<int>
     */
    protected array $secondAccountMembers = [0, 2, 5, 9, 14];

    /**
     * The exco officers. A water connection is a membership requirement
     * and only members can hold office, so each officer is also a Member
     * with a home connection: [first, last, office role, home address].
     *
     * @var array<string, array{string, string, string, string}>
     */
    protected array $staffProfiles = [
        'saman@demo.lk' => ['Saman', 'Kumara', 'Water Controller', 'No. 21, Galekale, Medamahanuwara'],
        'herath@demo.lk' => ['Herath', 'Banda', 'Treasurer', 'No. 8, Bombrawa, Medamahanuwara'],
        'anula@demo.lk' => ['Anula', 'Ratnayake', 'Secretary', 'No. 54, Galekale, Medamahanuwara'],
        'tikiri@demo.lk' => ['Tikiri', 'Bandara', 'President', 'No. 17, Bombrawa, Medamahanuwara'],
    ];

    protected User $controller;

    protected User $treasurer;

    protected User $secretary;

    protected User $president;

    protected SystemLedgerAccount $cash;

    protected SystemLedgerAccount $bank;

    /**
     * Seed the full demo dataset. Re-running tops up anything missing
     * (e.g. exco staff without their required water accounts) instead of
     * duplicating data.
     */
    public function run(): void
    {
        // Notification jobs (WhatsApp bills/receipts/reminders) would pile
        // up in the queue table for every historical record; drop them.
        Queue::fake();

        if (User::query()->where('email', 'saman@demo.lk')->exists()) {
            $this->topUpStaffAccounts();

            return;
        }

        $this->call([
            RolePermissionSeeder::class,
            BillingCategorySeeder::class,
            SystemLedgerAccountSeeder::class,
        ]);

        $this->loadLedgerAccounts();

        try {
            $this->seedStaff();
            $accounts = [
                ...$this->seedMembersAndAccounts(),
                ...$this->seedStaffAccounts(),
            ];
            $items = $this->seedInventoryItems();
            $this->seedSixMonthsOfOperations($accounts, $items);
            $this->markDefaultersInactive($accounts);
        } finally {
            Date::setTestNow();
        }

        Artisan::call('bills:mark-overdue');

        $this->command->info('Demo data ready. Staff logins (password: "password"):');

        foreach ($this->staffProfiles as $email => [, , $role]) {
            $this->command->line("  {$role}: {$email}");
        }

        $this->command->line('  Members: nimal@demo.lk … swarna@demo.lk');
    }

    /**
     * Bring an already-seeded database in line with the membership rule:
     * every exco officer holds the Member role and a home water account
     * with the same six months of billing history.
     */
    protected function topUpStaffAccounts(): void
    {
        $this->command->warn('Demo data already present — ensuring exco staff hold water accounts.');

        $this->loadLedgerAccounts();
        $this->loadStaff();

        try {
            $newAccounts = $this->seedStaffAccounts();

            if ($newAccounts !== []) {
                $this->seedSixMonthsOfOperations($newAccounts, [], withOverheads: false, profileIndexOffset: 25);
            }
        } finally {
            Date::setTestNow();
        }

        Artisan::call('bills:mark-overdue');

        $this->command->info(count($newAccounts).' staff water account(s) backfilled with six months of history.');
    }

    protected function loadLedgerAccounts(): void
    {
        $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
        $this->bank = SystemLedgerAccount::query()->where('code', '1100')->firstOrFail();
    }

    protected function loadStaff(): void
    {
        $this->controller = User::query()->where('email', 'saman@demo.lk')->firstOrFail();
        $this->treasurer = User::query()->where('email', 'herath@demo.lk')->firstOrFail();
        $this->secretary = User::query()->where('email', 'anula@demo.lk')->firstOrFail();
        $this->president = User::query()->where('email', 'tikiri@demo.lk')->firstOrFail();
    }

    /**
     * The society office staff: members first, office-bearers second.
     */
    protected function seedStaff(): void
    {
        foreach ($this->staffProfiles as $email => [$first, $last, $role, $address]) {
            $user = User::factory()->create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => '07712300'.str_pad((string) random_int(10, 99), 2, '0'),
                'address' => $address,
                'wa_number' => null,
            ]);

            $user->assignRole(['Member', $role]);
        }

        $this->loadStaff();
    }

    /**
     * One home connection per exco officer, skipping any who already own
     * an account (makes re-runs and top-ups safe).
     *
     * @return list<WaterAccount>
     */
    protected function seedStaffAccounts(): array
    {
        $domestic = BillingCategory::query()->where('name', 'Domestic')->firstOrFail();
        $accounts = [];

        foreach ($this->staffProfiles as $email => [, , , $address]) {
            $user = User::query()->where('email', $email)->firstOrFail();

            if (! $user->hasRole('Member')) {
                $user->assignRole('Member');
            }

            if ($user->address === 'Society Office, Medamahanuwara') {
                $user->update(['address' => $address]);
            }

            if ($user->waterAccounts()->exists()) {
                continue;
            }

            $accounts[] = $this->waterAccount($user, $domestic, $address, WaterAccount::query()->count() + 1);
        }

        return $accounts;
    }

    /**
     * The 20 members and their 25 water accounts.
     *
     * @return list<WaterAccount>
     */
    protected function seedMembersAndAccounts(): array
    {
        $domestic = BillingCategory::query()->where('name', 'Domestic')->firstOrFail();
        $business = BillingCategory::query()->where('name', 'Business')->firstOrFail();

        $accounts = [];
        $accountSequence = 1;

        foreach ($this->memberProfiles as $index => [$first, $last, $village]) {
            $address = 'No. '.(($index * 7 % 90) + 3)."/{$village}, {$village}, Medamahanuwara";

            $member = User::factory()->create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first).'@demo.lk',
                'phone' => '07760012'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'address' => $address,
                'wa_number' => $index % 3 === 0 ? '947760012'.str_pad((string) $index, 2, '0', STR_PAD_LEFT) : null,
            ]);

            $member->assignRole('Member');

            // Chandrasena runs the village shop; Dharmasena the rice mill.
            $category = in_array($index, [7, 16], true) ? $business : $domestic;

            $accounts[] = $this->waterAccount($member, $category, $address, $accountSequence++);

            if (in_array($index, $this->secondAccountMembers, true)) {
                $accounts[] = $this->waterAccount(
                    $member,
                    $domestic,
                    'Paddy field plot, '.$village.', Medamahanuwara',
                    $accountSequence++,
                );
            }
        }

        return $accounts;
    }

    protected function waterAccount(User $owner, BillingCategory $category, string $address, int $sequence): WaterAccount
    {
        return WaterAccount::factory()->for($owner, 'owner')->create([
            'billing_category_id' => $category->id,
            'account_number' => 'MWS-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'meter_number' => 'MTR-'.str_pad((string) (52000 + $sequence), 6, '0', STR_PAD_LEFT),
            'initial_reading' => random_int(120, 2400),
            'connection_address' => $address,
            'connected_at' => now()->subMonths(random_int(8, 48))->startOfMonth(),
        ]);
    }

    /**
     * The two defaulters (indexes 3 and 17 — see $defaulters in
     * seedSixMonthsOfOperations) stopped paying from the fourth month, so
     * the society treats their connections as disconnected. Their bill and
     * payment history is untouched; only the account status changes. No
     * deactivation service exists yet, so this is a direct model update.
     *
     * @param  list<WaterAccount>  $accounts
     */
    protected function markDefaultersInactive(array $accounts): void
    {
        foreach ([3, 17] as $index) {
            $accounts[$index]->update(['status' => WaterAccountStatus::Inactive]);
        }
    }

    /**
     * The society's material stock, with opening purchases.
     *
     * @return array<string, InventoryItem>
     */
    protected function seedInventoryItems(): array
    {
        $definitions = [
            'pipe20' => ['PVC Pipe 20mm', 'm', 180.00, 120, 30],
            'pipe32' => ['PVC Pipe 32mm', 'm', 320.00, 60, 20],
            'valve' => ['Gate Valve 32mm', 'pcs', 1450.00, 10, 3],
            'meter' => ['Water Meter', 'pcs', 5200.00, 8, 2],
            'coupling' => ['HDPE Coupling', 'pcs', 260.00, 40, 10],
            'chlorine' => ['Chlorine', 'kg', 900.00, 50, 15],
        ];

        $inventory = app(InventoryService::class);
        $openingDay = now()->subMonthsNoOverflow(5)->startOfMonth()->addDays(2)->setTime(10, 0);
        $items = [];

        foreach ($definitions as $key => [$name, $unit, $rate, $openingQuantity, $reorderLevel]) {
            $item = InventoryItem::query()->create([
                'name' => $name,
                'unit' => $unit,
                'unit_rate' => $rate,
                'quantity_in_stock' => 0,
                'reorder_level' => $reorderLevel,
                'is_active' => true,
            ]);

            $inventory->recordMovement(
                $item,
                StockMovementType::Purchase,
                $openingQuantity,
                $this->treasurer,
                $rate,
                'Opening stock',
                null,
                $openingDay,
            );

            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * Replay six months of readings, bills, payments, complaints, jobs,
     * and expenses, oldest month first. With $withOverheads disabled only
     * the per-account history (readings, bills, payments) is written —
     * used when backfilling accounts into an already-seeded database.
     * $profileIndexOffset shifts the payment-behaviour indexes so
     * backfilled accounts don't inherit the defaulter/late profiles.
     *
     * @param  list<WaterAccount>  $accounts
     * @param  array<string, InventoryItem>  $items
     */
    protected function seedSixMonthsOfOperations(array $accounts, array $items, bool $withOverheads = true, int $profileIndexOffset = 0): void
    {
        $realNow = now();
        $billGeneration = app(BillGenerationService::class);
        $payments = app(PaymentService::class);
        $ledger = app(AccountLedgerService::class);
        $expenses = app(ExpenseService::class);

        // Stable per-account consumption base: business/industrial premises
        // draw far more than households.
        $baseConsumption = [];

        foreach ($accounts as $index => $account) {
            $baseConsumption[$account->id] = $account->billingCategory->name === 'Business'
                ? random_int(35, 65)
                : random_int(6, 24);
        }

        // Payment behaviour by account position: everyone else pays promptly.
        $partialPayers = [8, 13, 23];
        $latePayers = [5, 11, 21];
        $defaulters = [3, 17]; // stop paying from the fourth month
        $payhereAccounts = [10, 21]; // the two Business accounts settle online

        $meterValues = [];

        foreach ($accounts as $account) {
            $meterValues[$account->id] = (float) $account->initial_reading;
        }

        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $monthStart = $realNow->subMonthsNoOverflow($monthOffset)->startOfMonth();
            $monthIndex = 5 - $monthOffset;

            // 1. Readings + bills: the controller walks the villages early
            //    in the month.
            foreach ($accounts as $index => $account) {
                $readAt = $monthStart->addDays(4 + ($index % 5))->setTime(8 + ($index % 6), ($index * 7) % 55);

                if ($readAt->greaterThan($realNow)) {
                    continue;
                }

                Date::setTestNow($readAt);

                $consumption = max(1, $baseConsumption[$account->id] + random_int(-3, 4));
                $meterValues[$account->id] += $consumption;

                $reading = MeterReading::query()->create([
                    'water_account_id' => $account->id,
                    'recorded_by' => $this->controller->id,
                    'billing_month' => $monthStart->format('Y-m'),
                    'reading_value' => $meterValues[$account->id],
                    'consumption' => $consumption,
                    'reading_date' => $readAt->toDateString(),
                ]);

                $billGeneration->generate($reading, $this->controller);

                // 2. Payment, per the account's behaviour profile.
                $profileIndex = $index + $profileIndexOffset;
                $isDefaulting = in_array($profileIndex, $defaulters, true) && $monthIndex >= 3;

                if ($isDefaulting) {
                    continue;
                }

                $payDelay = in_array($profileIndex, $latePayers, true) ? random_int(17, 24) : random_int(2, 9);
                $payAt = $readAt->addDays($payDelay)->setTime(10 + ($index % 7), ($index * 11) % 55);

                if ($payAt->greaterThan($realNow)) {
                    continue;
                }

                Date::setTestNow($payAt);

                $balance = $ledger->balanceFor($account->refresh());

                if ($balance <= 0) {
                    continue;
                }

                $amount = in_array($profileIndex, $partialPayers, true)
                    ? round($balance * 0.6, 2)
                    : $balance;

                if (in_array($profileIndex, $payhereAccounts, true)) {
                    // The two businesses check out online instead of paying
                    // over the counter, so the Collection report has a
                    // PayHere split alongside the manual receipts.
                    $intent = $payments->createGatewayIntent($account, $amount);
                    $payments->completeGateway($intent, ['payment_id' => (string) random_int(300000, 399999)]);

                    continue;
                }

                // Everyone else pays cash at the office.
                $payments->record($account, $amount, $this->cash, $this->treasurer, null, $payAt);
            }

            if (! $withOverheads) {
                continue;
            }

            // 3. Running costs for the month.
            $this->seedMonthlyExpenses($expenses, $monthStart, $monthIndex, $realNow);

            // 4. Chlorine top-up purchase halfway through the demo window.
            if ($monthIndex === 3) {
                Date::setTestNow($monthStart->addDays(12)->setTime(11, 0));

                app(InventoryService::class)->recordMovement(
                    $items['chlorine'],
                    StockMovementType::Purchase,
                    40,
                    $this->treasurer,
                    920.00,
                    'Restock from Kandy supplier',
                );
            }
        }

        if ($withOverheads) {
            $this->seedComplaintsAndJobs($accounts, $items, $realNow);
        }
    }

    /**
     * Electricity, chemicals, and wages recorded by the treasurer.
     */
    protected function seedMonthlyExpenses(ExpenseService $expenses, CarbonInterface $monthStart, int $monthIndex, CarbonInterface $realNow): void
    {
        $maintenance = SystemLedgerAccount::query()->where('code', '5000')->firstOrFail();
        $other = SystemLedgerAccount::query()->where('code', '5900')->firstOrFail();

        $entries = [
            [10, random_int(4200, 6800), $other, $this->bank, 'Electricity bill — pump house'],
            [14, random_int(1800, 3600), $maintenance, $this->cash, 'Pump house caretaker wages'],
        ];

        if ($monthIndex % 2 === 0) {
            $entries[] = [16, random_int(2200, 3400), $maintenance, $this->cash, 'Chlorination and tank cleaning materials'];
        }

        foreach ($entries as [$day, $amount, $category, $paidFrom, $description]) {
            $expenseAt = $monthStart->addDays($day)->setTime(14, 30);

            if ($expenseAt->greaterThan($realNow)) {
                continue;
            }

            Date::setTestNow($expenseAt);

            $expenses->record($expenseAt, (float) $amount, $category, $paidFrom, $description, $this->treasurer);
        }
    }

    /**
     * Ten complaints across the window; six spawn maintenance jobs, most
     * get resolved, a few stay open for the demo.
     *
     * @param  list<WaterAccount>  $accounts
     * @param  array<string, InventoryItem>  $items
     */
    protected function seedComplaintsAndJobs(array $accounts, array $items, CarbonInterface $realNow): void
    {
        $complaintService = app(ComplaintService::class);
        $jobService = app(MaintenanceJobService::class);
        $expenseService = app(ExpenseService::class);
        $inventoryService = app(InventoryService::class);
        $maintenance = SystemLedgerAccount::query()->where('code', '5000')->firstOrFail();

        // [monthIndex, accountIndex, category, subject, description, job?, resolve?, usedItems]
        /** @var list<array{int, int, ComplaintCategory, string, string, bool, bool, array<string, int>}> $plans */
        $plans = [
            [0, 1, ComplaintCategory::Leak, 'Leak at the main line near Galekale junction', 'Water is leaking from the joint near the culvert at the Galekale junction. The road is getting washed away.', true, true, ['pipe32' => 4, 'coupling' => 2]],
            [0, 12, ComplaintCategory::LowPressure, 'Very low pressure in upper Bombrawa', 'For about a week the water pressure in the upper part of Bombrawa is too weak to fill even a bucket in the morning.', true, true, []],
            [1, 6, ComplaintCategory::Blockage, 'Blocked distribution line after heavy rains', 'After the heavy rains mud has blocked the line to our lane. No water since yesterday evening.', true, true, ['pipe20' => 6]],
            [1, 15, ComplaintCategory::Other, 'Meter cover broken', 'The cement cover of the meter pit is cracked and the meter is exposed to the rain.', false, true, []],
            [2, 9, ComplaintCategory::Leak, 'Leaking joint near the paddy field culvert', 'A steady leak has appeared near the culvert by our paddy field. Wasting a lot of water.', true, true, ['coupling' => 3]],
            [3, 4, ComplaintCategory::Other, 'Muddy water after tank cleaning', 'Since the tank cleaning last week the water comes muddy in the mornings.', false, true, []],
            [3, 18, ComplaintCategory::LowPressure, 'Water not coming in the morning hours', 'Between 6am and 9am there is no water at all in our section of Bombrawa.', true, true, []],
            [4, 7, ComplaintCategory::Other, 'Billing amount seems too high', 'My shop bill for last month is much higher than usual. Please check whether the reading was taken correctly.', false, true, []],
            [4, 2, ComplaintCategory::Leak, 'Leak in front of the temple road', 'Water is seeping up through the road surface in front of the temple road junction.', true, false, ['pipe20' => 3, 'valve' => 1]],
            [5, 10, ComplaintCategory::Blockage, 'No water for two days', 'We have had no water for two days now. Neighbours on the same line have the same problem.', true, false, []],
        ];

        foreach ($plans as $planIndex => [$monthIndex, $accountIndex, $category, $subject, $description, $hasJob, $resolve, $usedItems]) {
            $account = $accounts[$accountIndex];
            $member = $account->owner;

            $submittedAt = $realNow->subMonthsNoOverflow(5 - $monthIndex)
                ->startOfMonth()
                ->addDays(9 + ($planIndex % 14))
                ->setTime(9 + ($planIndex % 8), ($planIndex * 13) % 55);

            if ($submittedAt->greaterThan($realNow)) {
                continue;
            }

            Date::setTestNow($submittedAt);

            $complaint = $complaintService->submit($member, [
                'water_account_id' => $account->id,
                'category' => $category->value,
                'subject' => $subject,
                'description' => $description,
            ]);

            if (! $hasJob) {
                if ($resolve) {
                    Date::setTestNow($submittedAt->addDays(2)->setTime(11, 15));
                    $complaintService->reply($complaint, $this->secretary, 'Thank you for informing us — the office looked into this and it has been attended to.');
                    $complaintService->close($complaint, 'Resolved by the society office.', $this->secretary);
                }

                continue;
            }

            Date::setTestNow($submittedAt->addDay()->setTime(9, 30));
            $complaintService->assign($complaint, [$this->controller->id], $this->secretary);

            $job = $jobService->create([
                'title' => 'Repair: '.$subject,
                'description' => 'Dispatched from complaint '.$complaint->complaint_number.'. '.$description,
                'scheduled_date' => now()->addDays(2)->toDateString(),
            ], [$this->controller->id], $this->secretary, $complaint);

            Date::setTestNow($submittedAt->addDays(2)->setTime(8, 45));
            $jobService->updateStatus($job, MaintenanceJobStatus::InProgress, 'Crew on site, work started.', $this->controller);

            foreach ($usedItems as $itemKey => $quantity) {
                $inventoryService->recordMovement(
                    $items[$itemKey],
                    StockMovementType::Usage,
                    $quantity,
                    $this->controller,
                    null,
                    'Used for '.$job->job_number,
                    $job->id,
                );
            }

            if (! $resolve) {
                continue;
            }

            $completedAt = $submittedAt->addDays(4)->setTime(16, 20);

            if ($completedAt->greaterThan($realNow)) {
                continue;
            }

            Date::setTestNow($completedAt);
            $jobService->updateStatus($job, MaintenanceJobStatus::Completed, 'Repair completed and line tested.', $this->controller);

            if ($usedItems !== []) {
                $expenseService->record(
                    now(),
                    (float) random_int(1500, 6500),
                    $maintenance,
                    $this->cash,
                    'Contract labour — '.$job->job_number,
                    $this->treasurer,
                    $job->id,
                );
            }

            $complaintService->close($complaint, 'Repair completed by the maintenance crew. Thank you for reporting.', $this->secretary);
        }

        // A run of valve replacements across the smaller repair jobs above
        // (not each individually worth its own complaint) dips the item to
        // its reorder level, so the Inventory report has a low-stock row.
        Date::setTestNow($realNow->subDays(2)->setTime(15, 30));

        $inventoryService->recordMovement(
            $items['valve'],
            StockMovementType::Usage,
            7,
            $this->controller,
            null,
            'Gate valves fitted across several minor repairs this month',
        );
    }
}
