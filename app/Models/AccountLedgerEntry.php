<?php

namespace App\Models;

use App\Enums\AccountLedgerEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single event on a water account's statement. Strictly append-only
 * (D-25): never updated or deleted after insert — corrections are posted as
 * reversing entries, and all writes go through AccountLedgerService.
 *
 * @property int $id
 * @property int $water_account_id
 * @property AccountLedgerEntryType $entry_type
 * @property numeric-string $amount
 * @property numeric-string $running_balance
 * @property Carbon $entry_date
 * @property string|null $billing_month
 * @property string|null $document_number
 * @property string $description
 * @property int|null $recorded_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WaterAccount $waterAccount
 * @property-read User|null $recorder
 */
#[Fillable(['water_account_id', 'entry_type', 'amount', 'running_balance', 'entry_date', 'billing_month', 'document_number', 'description', 'recorded_by', 'metadata'])]
class AccountLedgerEntry extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_type' => AccountLedgerEntryType::class,
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'entry_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the water account whose statement this entry belongs to.
     *
     * @return BelongsTo<WaterAccount, $this>
     */
    public function waterAccount(): BelongsTo
    {
        return $this->belongsTo(WaterAccount::class);
    }

    /**
     * Get the user who recorded the entry, if not system-posted.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
