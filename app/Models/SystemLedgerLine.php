<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single journal line: a signed amount against a chart account —
 * positive = debit, negative = credit.
 *
 * @property int $id
 * @property int $system_ledger_entry_id
 * @property int $system_ledger_account_id
 * @property numeric-string $amount
 * @property int $line_number
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SystemLedgerEntry $entry
 * @property-read SystemLedgerAccount $account
 */
#[Fillable(['system_ledger_entry_id', 'system_ledger_account_id', 'amount', 'line_number', 'description'])]
class SystemLedgerLine extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the journal header the line belongs to.
     *
     * @return BelongsTo<SystemLedgerEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(SystemLedgerEntry::class, 'system_ledger_entry_id');
    }

    /**
     * Get the chart account the line debits or credits.
     *
     * @return BelongsTo<SystemLedgerAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SystemLedgerAccount::class, 'system_ledger_account_id');
    }
}
