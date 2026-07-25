<?php

namespace Database\Seeders;

use App\Enums\BillStatus;
use App\Enums\ComplaintCategory;
use App\Enums\MaintenanceJobStatus;
use App\Enums\StockMovementType;
use App\Enums\WaterAccountStatus;
use App\Models\Bill;
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
use App\Services\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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
    protected array $secondAccountMembers = [0, 2, 5, 9, 14, 18];

    /**
     * Demonstrations send every WhatsApp message to one handset, so the
     * notifications can be shown arriving on a real phone.
     */
    protected const string DEMO_WA_NUMBER = '0776665137';

    /**
     * The member who signs in as member@demo.lk: one login per role.
     */
    protected const int DEMO_MEMBER_INDEX = 18;

    /**
     * The member who signs in as member2@demo.lk: the late payer. Their
     * single connection (account position 11) carries real arrears and the
     * bill held back for the live `bills:mark-overdue` demonstration.
     */
    protected const int DEMO_MEMBER_2_INDEX = 8;

    /**
     * Accounts in arrears whose newest past-due bill is left Approved, so
     * running "bills:mark-overdue" during a demonstration flips them and
     * posts the late fees while the audience watches. ACC-0012 belongs to
     * the late-payment member; the others are ordinary accounts behind on
     * payment, so the job is seen working across the society, not on one
     * rigged account.
     *
     * @var list<string>
     */
    protected const array LATE_FEE_DEMO_ACCOUNTS = ['ACC-0009', 'ACC-0012', 'ACC-0014'];

    /**
     * member2's login, kept next to the index it belongs to.
     */
    protected const string DEMO_MEMBER_2_EMAIL = 'member2@demo.lk';

    /**
     * The exco officers. A water connection is a membership requirement
     * and only members can hold office, so each officer is also a Member
     * with a home connection: [first, last, office role, home address].
     *
     * @var array<string, array{string, string, string, string}>
     */
    protected array $staffProfiles = [
        'watercontroller@demo.lk' => ['Saman', 'Kumara', 'Water Controller', 'No. 21, Galekale, Medamahanuwara'],
        'treasurer@demo.lk' => ['Herath', 'Banda', 'Treasurer', 'No. 8, Bombrawa, Medamahanuwara'],
        'secretary@demo.lk' => ['Anula', 'Ratnayake', 'Secretary', 'No. 54, Galekale, Medamahanuwara'],
        'president@demo.lk' => ['Tikiri', 'Bandara', 'President', 'No. 17, Bombrawa, Medamahanuwara'],
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

        if (User::query()->where('email', 'watercontroller@demo.lk')->exists()) {
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

        $this->markOverdueHoldingBackDemoBills();

        // Every account, including the setup administrator, notifies the
        // one handset used for demonstrations.
        User::query()->update(['wa_number' => self::DEMO_WA_NUMBER]);

        $this->command->info('Demo data ready. Staff logins (password: "password"):');

        foreach ($this->staffProfiles as $email => [, , $role]) {
            $this->command->line("  {$role}: {$email}");
        }

        $this->command->line('  Member: member@demo.lk (other members: nimal@demo.lk … swarna@demo.lk)');
        $this->command->line('  Member — late payment demo: '.self::DEMO_MEMBER_2_EMAIL.' (in arrears; one past-due bill is still Approved, so "php artisan bills:mark-overdue" flips it and posts the late fee live)');
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
        $this->seedComplaintNotifyRecipients();

        try {
            $newAccounts = $this->seedStaffAccounts();

            if ($newAccounts !== []) {
                $this->seedSixMonthsOfOperations($newAccounts, [], withOverheads: false, profileIndexOffset: 25);
            }
        } finally {
            Date::setTestNow();
        }

        $this->markOverdueHoldingBackDemoBills();

        $this->command->info(count($newAccounts).' staff water account(s) backfilled with six months of history.');
    }

    protected function loadLedgerAccounts(): void
    {
        $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
        $this->bank = SystemLedgerAccount::query()->where('code', '1100')->firstOrFail();
    }

    protected function loadStaff(): void
    {
        $this->controller = User::query()->where('email', 'watercontroller@demo.lk')->firstOrFail();
        $this->treasurer = User::query()->where('email', 'treasurer@demo.lk')->firstOrFail();
        $this->secretary = User::query()->where('email', 'secretary@demo.lk')->firstOrFail();
        $this->president = User::query()->where('email', 'president@demo.lk')->firstOrFail();
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
                'wa_number' => self::DEMO_WA_NUMBER,
            ]);

            $user->assignRole(['Member', $role]);
        }

        $this->loadStaff();
        $this->seedComplaintNotifyRecipients();
    }

    /**
     * Who hears about a new complaint. The President oversees the society
     * and the Water Controller does the field work, so both are set as
     * recipients out of the box rather than leaving the list empty.
     */
    protected function seedComplaintNotifyRecipients(): void
    {
        app(Settings::class)->set(Settings::COMPLAINT_NOTIFY_USER_IDS, [
            $this->president->id,
            $this->controller->id,
        ]);
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
                'email' => match ($index) {
                    self::DEMO_MEMBER_INDEX => 'member@demo.lk',
                    self::DEMO_MEMBER_2_INDEX => self::DEMO_MEMBER_2_EMAIL,
                    default => strtolower($first).'@demo.lk',
                },
                'phone' => '07760012'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'address' => $address,
                'wa_number' => self::DEMO_WA_NUMBER,
            ]);

            $member->assignRole('Member');

            // Chandrasena runs the village shop; Dharmasena the rice mill.
            $category = in_array($index, [7, 16], true) ? $business : $domestic;

            $accounts[] = $this->waterAccount($member, $category, $address, $accountSequence++);

            if (in_array($index, $this->secondAccountMembers, true)) {
                // The demo member's second connection is a business one, so
                // account switching and both tariff structures can be shown
                // from a single login.
                $isDemoMember = $index === self::DEMO_MEMBER_INDEX;

                $accounts[] = $this->waterAccount(
                    $member,
                    $isDemoMember ? $business : $domestic,
                    $isDemoMember
                        ? 'Village bakery, '.$village.', Medamahanuwara'
                        : 'Paddy field plot, '.$village.', Medamahanuwara',
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
            'account_number' => 'ACC-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'meter_number' => 'MTR-'.str_pad((string) (52000 + $sequence), 6, '0', STR_PAD_LEFT),
            'initial_reading' => random_int(120, 2400),
            'connection_address' => $address,
            'connected_at' => now()->subMonths(random_int(8, 48))->startOfMonth(),
        ]);
    }

    /**
     * Two of the accounts that stop paying (indexes 3 and 17 — see
     * $defaulters in seedSixMonthsOfOperations) are treated as
     * disconnected by the society. member2's account (index 11) is not:
     * its arrears are demonstrated live, so the connection stays Active.
     * Their bill and payment history is untouched; only the account status
     * changes. No deactivation service exists yet, so this is a direct
     * model update.
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
     * Flip every past-due bill overdue except the newest one on each of
     * member2's accounts: that bill stays Approved with its due date in
     * the past, so running `bills:mark-overdue` on stage flips it and
     * posts the late fee in front of the audience.
     *
     * The hold-back has to happen before the command runs, not after:
     * account ledger entries carry a running balance, so deleting or
     * editing a penalty entry once posted would leave every later balance
     * on the account wrong. Parking the due date one month ahead keeps the
     * bill out of the command's query, and the original date is written
     * back straight afterwards.
     */
    protected function markOverdueHoldingBackDemoBills(): void
    {
        $heldBack = $this->heldBackPastDueBills();

        Bill::query()
            ->whereIn('id', $heldBack->keys())
            ->update(['due_date' => today()->addMonth()->toDateString()]);

        try {
            Artisan::call('bills:mark-overdue');
        } finally {
            foreach ($heldBack as $billId => $dueDate) {
                Bill::query()->whereKey($billId)->update(['due_date' => $dueDate]);
            }
        }
    }

    /**
     * The bills held back from the seed's own overdue run, keyed by id with
     * their original due date: the newest past-due bill on each of a few
     * accounts in arrears, including the late-payment member's.
     *
     * One bill per account, spread over several accounts, is what a nightly
     * run actually produces. Several on one account would mean the job had
     * not run for months.
     *
     * @return Collection<int, string>
     */
    protected function heldBackPastDueBills(): Collection
    {
        $accountIds = WaterAccount::query()
            ->whereIn('account_number', self::LATE_FEE_DEMO_ACCOUNTS)
            ->pluck('id');

        return Bill::query()
            ->whereIn('water_account_id', $accountIds)
            ->where('is_current', true)
            ->where('status', BillStatus::Approved)
            ->whereDate('due_date', '<', today())
            ->orderByDesc('due_date')
            ->get()
            ->groupBy('water_account_id')
            ->map(fn (Collection $accountBills): Bill => $accountBills->first())
            ->mapWithKeys(fn (Bill $bill): array => [$bill->id => $bill->due_date->toDateString()]);
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
        // Position 11 is member2@demo.lk (member profile 8): late and short
        // from the start, then nothing from the fourth month, so the account
        // reaches the demo with genuine arrears behind it.
        $partialPayers = [8, 11, 13, 23];
        $latePayers = [5, 11, 21];
        $defaulters = [3, 11, 17]; // stop paying from the fourth month
        $payhereAccounts = [10, 21]; // the two Business accounts settle online

        // Positions 23 and 24 are the demo member's two connections and 11 is
        // the late-payment member's, all left unread so the reading can be
        // taken live; the rest give the reading list other accounts to show.
        $unreadInCurrentMonth = [2, 7, 11, 13, 19, 23, 24];

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
                if ($monthOffset === 0 && in_array($index + $profileIndexOffset, $unreadInCurrentMonth, true)) {
                    continue;
                }

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
     * Twelve complaints across the window; seven spawn maintenance jobs,
     * most get resolved. The newest month always carries live activity —
     * an open unassigned complaint, an assigned job, and work in
     * progress — so the operational screens and reports have something
     * current to show.
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
            [5, 16, ComplaintCategory::Other, 'Water meter reading seems wrong', 'This month the meter shows far more units than we could have used. Please check the meter for a fault.', false, false, []],
            [5, 13, ComplaintCategory::Leak, 'Leak at the roadside valve pit near the school', 'The valve pit by the school road is filling with water and overflowing onto the road.', true, false, ['coupling' => 2]],
        ];

        foreach ($plans as $planIndex => [$monthIndex, $accountIndex, $category, $subject, $description, $hasJob, $resolve, $usedItems]) {
            $account = $accounts[$accountIndex];
            $member = $account->owner;

            $submittedAt = $realNow->subMonthsNoOverflow(5 - $monthIndex)
                ->startOfMonth()
                ->addDays(9 + ($planIndex % 14))
                ->setTime(9 + ($planIndex % 8), ($planIndex * 13) % 55);

            if ($monthIndex === 5) {
                // The newest month always shows live complaint activity,
                // however far into the month the data is seeded: these sit
                // within the last few days rather than on a fixed date that
                // ages out as real time passes.
                $submittedAt = $realNow
                    ->subDays(1 + ($planIndex % 3))
                    ->setTime(9 + ($planIndex % 8), ($planIndex * 13) % 55)
                    ->max($realNow->startOfMonth()->addHours(8));

                if ($submittedAt->greaterThan($realNow)) {
                    $submittedAt = $realNow->subHours(2);
                }
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

            $assignAt = $submittedAt->addDay()->setTime(9, 30);

            // The office has not triaged the newest complaints yet: they
            // stay open and unassigned.
            if ($assignAt->greaterThan($realNow)) {
                continue;
            }

            Date::setTestNow($assignAt);
            $complaintService->assign($complaint, [$this->controller->id], $this->secretary);

            $job = $jobService->create([
                'title' => 'Repair: '.$subject,
                'description' => 'Dispatched from complaint '.$complaint->complaint_number.'. '.$description,
                'scheduled_date' => now()->addDays(2)->toDateString(),
            ], [$this->controller->id], $this->secretary, $complaint);

            $startAt = $submittedAt->addDays(2)->setTime(8, 45);

            // The crew has not reached the newest jobs: they stay Assigned.
            if ($startAt->greaterThan($realNow)) {
                continue;
            }

            Date::setTestNow($startAt);
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
