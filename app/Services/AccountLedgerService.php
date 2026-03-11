<?php

namespace App\Services;

use App\Enums\AccountLedgerEntryType;
use App\Models\AccountLedgerEntry;
use App\Models\User;
use App\Models\WaterAccount;
use App\Models\WaterAccountBalance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of account_ledger_entries — insert-only (D-25). Every
 * posting locks the account's balance row, computes the running balance,
 * inserts the entry, and updates the cached balance in one transaction.
 */
class AccountLedgerService
{
    /**
     * Append a signed entry to the account's statement. Positive = the
     * member owes more; negative = reduces what they owe.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function post(
        WaterAccount $waterAccount,
        AccountLedgerEntryType $type,
        float $amount,
        string $description,
        ?User $recordedBy = null,
        ?string $billingMonth = null,
        ?string $documentNumber = null,
        ?array $metadata = null,
        ?CarbonInterface $entryDate = null,
    ): AccountLedgerEntry {
        return DB::transaction(function () use ($waterAccount, $type, $amount, $description, $recordedBy, $billingMonth, $documentNumber, $metadata, $entryDate): AccountLedgerEntry {
            $balance = $this->lockBalanceRow($waterAccount);

            $runningBalance = round((float) $balance->balance + $amount, 2);

            $entry = AccountLedgerEntry::query()->create([
                'water_account_id' => $waterAccount->id,
                'entry_type' => $type,
                'amount' => round($amount, 2),
                'running_balance' => $runningBalance,
                'entry_date' => ($entryDate ?? now())->toDateString(),
                'billing_month' => $billingMonth,
                'document_number' => $documentNumber,
                'description' => $description,
                'recorded_by' => $recordedBy?->id,
                'metadata' => $metadata,
            ]);

            $balance->update(['balance' => $runningBalance]);

            return $entry;
        });
    }

    /**
     * The account's current ledger balance: what the member owes right now
     * (negative = credit in their favour).
     */
    public function balanceFor(WaterAccount $waterAccount): float
    {
        return (float) (WaterAccountBalance::query()
            ->where('water_account_id', $waterAccount->id)
            ->value('balance') ?? 0.0);
    }

    /**
     * The entries posted after the given cutoff entry, in statement order.
     * Passing null returns the account's entire statement. This is how bill
     * membership is derived (D-25) — callers supply the previous current
     * bill's water_charge entry id as the cutoff.
     *
     * @return Collection<int, AccountLedgerEntry>
     */
    public function entriesAfter(WaterAccount $waterAccount, ?int $cutoffEntryId): Collection
    {
        return AccountLedgerEntry::query()
            ->where('water_account_id', $waterAccount->id)
            ->when($cutoffEntryId !== null, fn ($query) => $query->where('id', '>', $cutoffEntryId))
            ->orderBy('id')
            ->get();
    }

    /**
     * Recompute the cached balance from the entries — a consistency guard
     * for tests and maintenance, not part of any normal flow.
     */
    public function recalculate(WaterAccount $waterAccount): float
    {
        return DB::transaction(function () use ($waterAccount): float {
            $balance = $this->lockBalanceRow($waterAccount);

            $actual = round((float) AccountLedgerEntry::query()
                ->where('water_account_id', $waterAccount->id)
                ->sum('amount'), 2);

            $balance->update(['balance' => $actual]);

            return $actual;
        });
    }

    /**
     * Lock (creating if needed) the account's balance row — the per-account
     * mutex that serializes postings.
     */
    protected function lockBalanceRow(WaterAccount $waterAccount): WaterAccountBalance
    {
        $balance = WaterAccountBalance::query()
            ->where('water_account_id', $waterAccount->id)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            WaterAccountBalance::query()->firstOrCreate(['water_account_id' => $waterAccount->id]);

            $balance = WaterAccountBalance::query()
                ->where('water_account_id', $waterAccount->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $balance;
    }
}
