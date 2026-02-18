<?php

namespace App\Services;

use App\Enums\AccountLedgerEntryType;
use App\Enums\BillStatus;
use App\Enums\WaterAccountStatus;
use App\Events\BillGenerated;
use App\Models\AccountLedgerEntry;
use App\Models\Bill;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates the customer-facing bill at reading confirmation (D-14): posts
 * the month's water_charge entry (the cutoff marker, D-25), freezes the
 * statement snapshot, and auto-approves (D-15). Corrections go through
 * reissue — reversal + regeneration — never edits (D-20).
 */
class BillGenerationService
{
    public function __construct(
        protected AccountLedgerService $accountLedger,
        protected TariffCalculationService $tariffs,
        protected RunningNumberService $runningNumbers,
        protected AuditLogger $audit,
    ) {}

    /**
     * The bill exactly as it would be generated, computed live and not
     * persisted — what the Water Controller reviews before confirming.
     *
     * @return array<string, mixed>
     */
    public function preview(MeterReading $reading): array
    {
        $waterAccount = $reading->waterAccount;

        $this->assertBillable($reading, $waterAccount);

        $tariff = $this->tariffs->calculate($waterAccount->billingCategory, (float) $reading->consumption);

        $previousBill = $this->latestCurrentBill($waterAccount);
        $presented = $this->accountLedger->entriesAfter($waterAccount, $previousBill?->account_ledger_entry_id);
        $previousBalance = $this->accountLedger->balanceFor($waterAccount);

        return $this->snapshot($reading, $tariff, $previousBill, $presented, $previousBalance);
    }

    /**
     * Confirm the reading and generate its bill in one transaction. The
     * bill is final on return; the printed figures can never change (D-26).
     */
    public function generate(MeterReading $reading, User $user, bool $isReissue = false, ?Bill $supersedes = null): Bill
    {
        try {
            $bill = DB::transaction(function () use ($reading, $user, $isReissue, $supersedes): Bill {
                $waterAccount = $reading->waterAccount;

                $this->assertBillable($reading, $waterAccount);

                $tariff = $this->tariffs->calculate($waterAccount->billingCategory, (float) $reading->consumption);
                $previousBill = $this->latestCurrentBill($waterAccount);

                $billNumber = $this->runningNumbers->next('bill');

                $entry = $this->accountLedger->post(
                    $waterAccount,
                    AccountLedgerEntryType::WaterCharge,
                    $tariff['total'],
                    __('Water charge :month', ['month' => $reading->billing_month]),
                    recordedBy: $user,
                    billingMonth: $reading->billing_month,
                    documentNumber: $billNumber,
                );

                $presented = $this->accountLedger
                    ->entriesAfter($waterAccount, $previousBill?->account_ledger_entry_id)
                    ->reject(fn (AccountLedgerEntry $presentedEntry): bool => $presentedEntry->id === $entry->id)
                    ->values();

                $previousBalance = round((float) $entry->running_balance - $tariff['total'], 2);

                $bill = Bill::query()->create([
                    'bill_number' => $billNumber,
                    'water_account_id' => $waterAccount->id,
                    'meter_reading_id' => $reading->id,
                    'billing_month' => $reading->billing_month,
                    'is_current' => true,
                    'usage_charge' => $tariff['usage_charge'],
                    'service_charge' => $tariff['service_charge'],
                    'monthly_charge' => $tariff['total'],
                    'previous_balance' => $previousBalance,
                    'total_due' => (float) $entry->running_balance,
                    'breakdown' => $this->snapshot($reading, $tariff, $previousBill, $presented, $previousBalance),
                    'status' => BillStatus::Approved,
                    'due_date' => now()->addDays((int) config('billing.due_days'))->toDateString(),
                    'approved_at' => now(),
                    'generated_by' => $user->id,
                    'account_ledger_entry_id' => $entry->id,
                    'is_reissue' => $isReissue,
                    'supersedes_bill_id' => $supersedes?->id,
                ]);

                $this->audit->log('bill.generated', $bill, [
                    'bill_number' => $bill->bill_number,
                    'billing_month' => $bill->billing_month,
                    'total_due' => (float) $bill->total_due,
                    'is_reissue' => $isReissue,
                ], $user);

                BillGenerated::dispatch($bill);

                return $bill;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'bill' => __('A bill for this month already exists on this account.'),
            ]);
        }

        return $bill;
    }

    /**
     * Correct a finalized bill (D-20): reverse its water_charge entry,
     * supersede it, apply the corrected reading value, and regenerate. No
     * existing ledger row is touched — the new bill's cutoff reaches back
     * past the superseded one, re-presenting its charges with the reversal.
     */
    public function reissue(Bill $bill, float $correctedReadingValue, User $user): Bill
    {
        if ($bill->is_current !== true) {
            throw ValidationException::withMessages(['bill' => __('This bill has already been superseded.')]);
        }

        if ($bill->status === BillStatus::Paid) {
            throw ValidationException::withMessages(['bill' => __('A paid bill cannot be reissued.')]);
        }

        $reading = $bill->meterReading;
        $waterAccount = $bill->waterAccount;

        if ($reading->id !== $waterAccount->latestReading?->id) {
            throw ValidationException::withMessages([
                'bill' => __('Only the bill for the latest reading can be reissued.'),
            ]);
        }

        $previousValue = $reading->previousValue();

        if ($correctedReadingValue < $previousValue) {
            throw ValidationException::withMessages([
                'reading_value' => __('The corrected reading cannot be lower than the previous reading (:value).', [
                    'value' => number_format($previousValue, 2),
                ]),
            ]);
        }

        $newBill = DB::transaction(function () use ($bill, $reading, $waterAccount, $correctedReadingValue, $previousValue, $user): Bill {
            $this->accountLedger->post(
                $waterAccount,
                AccountLedgerEntryType::Reversal,
                -(float) $bill->monthly_charge,
                __('Reversal of :bill', ['bill' => $bill->bill_number]),
                recordedBy: $user,
                billingMonth: $bill->billing_month,
                metadata: ['reversed_bill_id' => $bill->id],
            );

            $bill->update(['is_current' => null]);

            $reading->update([
                'reading_value' => $correctedReadingValue,
                'consumption' => round($correctedReadingValue - $previousValue, 2),
            ]);

            $newBill = $this->generate($reading->refresh(), $user, isReissue: true, supersedes: $bill);

            $this->audit->log('bill.reissued', $newBill, [
                'superseded_bill_number' => $bill->bill_number,
                'corrected_reading_value' => $correctedReadingValue,
            ], $user);

            return $newBill;
        });

        return $newBill;
    }

    /**
     * The generation preconditions; each failure carries a field-level
     * message the preview and confirm screens surface directly.
     */
    protected function assertBillable(MeterReading $reading, WaterAccount $waterAccount): void
    {
        if ($waterAccount->status !== WaterAccountStatus::Active) {
            throw ValidationException::withMessages(['account' => __('Only active accounts are billed.')]);
        }

        if ($waterAccount->billingCategory === null || ! $waterAccount->billingCategory->tiers()->exists()) {
            throw ValidationException::withMessages([
                'account' => __('The account has no billing category with tariff slabs. Configure the tariff first.'),
            ]);
        }

        if ($reading->id !== $waterAccount->latestReading?->id) {
            throw ValidationException::withMessages(['reading' => __('Only the latest reading can be billed.')]);
        }

        $alreadyBilled = Bill::query()
            ->where('water_account_id', $waterAccount->id)
            ->where('billing_month', $reading->billing_month)
            ->where('is_current', true)
            ->exists();

        if ($alreadyBilled) {
            throw ValidationException::withMessages([
                'bill' => __('A bill for this month already exists on this account.'),
            ]);
        }
    }

    /**
     * The immutable breakdown persisted on the bill (D-06/D-25/D-27):
     * reading values, tier allocation, the presented ledger entries, and
     * the statement-equation summary the printout renders.
     *
     * @param  array{consumption: float, tiers: list<array<string, mixed>>, usage_charge: float, service_charge: float, total: float}  $tariff
     * @param  Collection<int, AccountLedgerEntry>  $presented
     * @return array<string, mixed>
     */
    protected function snapshot(MeterReading $reading, array $tariff, ?Bill $previousBill, Collection $presented, float $previousBalance): array
    {
        $payments = $presented
            ->filter(fn (AccountLedgerEntry $entry): bool => $entry->entry_type === AccountLedgerEntryType::Payment)
            ->sum(fn (AccountLedgerEntry $entry): float => abs((float) $entry->amount));

        $credits = $presented
            ->filter(fn (AccountLedgerEntry $entry): bool => $entry->entry_type !== AccountLedgerEntryType::Payment && (float) $entry->amount < 0)
            ->sum(fn (AccountLedgerEntry $entry): float => abs((float) $entry->amount));

        $debits = $presented
            ->filter(fn (AccountLedgerEntry $entry): bool => (float) $entry->amount > 0)
            ->sum(fn (AccountLedgerEntry $entry): float => (float) $entry->amount);

        return [
            'billing_category' => $reading->waterAccount->billingCategory?->name,
            'reading' => [
                'previous_value' => $reading->previousValue(),
                'current_value' => (float) $reading->reading_value,
                'consumption' => (float) $reading->consumption,
                'reading_date' => $reading->reading_date->toDateString(),
                'billing_month' => $reading->billing_month,
            ],
            'tiers' => $tariff['tiers'],
            'usage_charge' => $tariff['usage_charge'],
            'service_charge' => $tariff['service_charge'],
            'presented_entries' => $presented->map(fn (AccountLedgerEntry $entry): array => [
                'id' => $entry->id,
                'type' => $entry->entry_type->value,
                'date' => $entry->entry_date->toDateString(),
                'document_number' => $entry->document_number,
                'description' => $entry->description,
                'amount' => (float) $entry->amount,
            ])->all(),
            'summary' => [
                'previous_due' => (float) ($previousBill->total_due ?? 0.0),
                'payments' => round($payments, 2),
                'credits' => round($credits, 2),
                'debits' => round($debits, 2),
                'this_month' => $tariff['total'],
                'previous_balance' => $previousBalance,
                'total_due' => round($previousBalance + $tariff['total'], 2),
            ],
        ];
    }

    /**
     * Fresh lookup of the account's newest current bill — its water_charge
     * entry is the cutoff the new bill presents entries from (D-25).
     */
    protected function latestCurrentBill(WaterAccount $waterAccount): ?Bill
    {
        return $waterAccount->bills()
            ->where('is_current', true)
            ->orderByDesc('account_ledger_entry_id')
            ->first();
    }
}
