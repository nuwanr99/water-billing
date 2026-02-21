<?php

namespace App\Services;

use App\Models\SystemLedgerAccount;
use App\Models\SystemLedgerEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of the society's double-entry system ledger (D-19). The
 * ledger is cash-basis: payments (Phase 4) and expenses (Phase 7) post
 * journals here — bills and charges never do.
 */
class SystemLedgerService
{
    public function __construct(protected RunningNumberService $runningNumbers) {}

    /**
     * Post a balanced journal. Lines are signed amounts against chart
     * account codes: positive = debit, negative = credit; they must sum to
     * zero or the journal is refused.
     *
     * @param  list<array{account: string, amount: float, description?: string}>  $lines
     */
    public function post(
        string $description,
        array $lines,
        ?Carbon $entryDate = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): SystemLedgerEntry {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal needs at least two lines.');
        }

        if (abs(array_sum(array_column($lines, 'amount'))) >= 0.005) {
            throw new InvalidArgumentException('Journal lines must sum to zero (debits = credits).');
        }

        return DB::transaction(function () use ($description, $lines, $entryDate, $sourceType, $sourceId): SystemLedgerEntry {
            $accounts = SystemLedgerAccount::query()
                ->whereIn('code', array_column($lines, 'account'))
                ->get()
                ->keyBy('code');

            $entry = SystemLedgerEntry::query()->create([
                'reference_number' => $this->runningNumbers->next('journal'),
                'entry_date' => ($entryDate ?? now())->toDateString(),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'total_debit' => round(array_sum(array_filter(array_column($lines, 'amount'), fn (float $amount) => $amount > 0)), 2),
                'total_credit' => round(abs(array_sum(array_filter(array_column($lines, 'amount'), fn (float $amount) => $amount < 0))), 2),
                'is_balanced' => true,
                'is_posted' => true,
            ]);

            foreach (array_values($lines) as $index => $line) {
                $account = $accounts->get($line['account'])
                    ?? throw new InvalidArgumentException("Unknown system ledger account code [{$line['account']}].");

                $entry->lines()->create([
                    'system_ledger_account_id' => $account->id,
                    'amount' => round($line['amount'], 2),
                    'line_number' => $index + 1,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry;
        });
    }
}
