<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment received against a water account (D-28). Written only through
 * PaymentService, which posts both ledger sides and settles bills.
 *
 * @property int $id
 * @property string $receipt_number
 * @property int $water_account_id
 * @property int|null $account_ledger_entry_id
 * @property int|null $system_ledger_entry_id
 * @property int|null $destination_account_id
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property numeric-string $amount
 * @property string|null $reference
 * @property string|null $attachment_path
 * @property string|null $payhere_reference
 * @property array<string, mixed>|null $gateway_payload
 * @property int|null $recorded_by
 * @property Carbon $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WaterAccount $waterAccount
 * @property-read AccountLedgerEntry|null $ledgerEntry
 * @property-read SystemLedgerEntry|null $journal
 * @property-read SystemLedgerAccount|null $destinationAccount
 * @property-read User|null $recorder
 */
#[Fillable(['receipt_number', 'water_account_id', 'account_ledger_entry_id', 'system_ledger_entry_id', 'destination_account_id', 'method', 'status', 'amount', 'reference', 'attachment_path', 'payhere_reference', 'gateway_payload', 'recorded_by', 'paid_at'])]
class Payment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the water account the payment settles.
     *
     * @return BelongsTo<WaterAccount, $this>
     */
    public function waterAccount(): BelongsTo
    {
        return $this->belongsTo(WaterAccount::class);
    }

    /**
     * Get the payment's negative account-ledger entry.
     *
     * @return BelongsTo<AccountLedgerEntry, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(AccountLedgerEntry::class, 'account_ledger_entry_id');
    }

    /**
     * Get the payment's system-ledger journal.
     *
     * @return BelongsTo<SystemLedgerEntry, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(SystemLedgerEntry::class, 'system_ledger_entry_id');
    }

    /**
     * Get the asset account the money landed in (D-30).
     *
     * @return BelongsTo<SystemLedgerAccount, $this>
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(SystemLedgerAccount::class, 'destination_account_id');
    }

    /**
     * Get the user who recorded the payment; null for gateway payments.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
