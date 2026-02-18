<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cached ledger balance per water account; the row is also the per-account
 * posting lock. Written only by AccountLedgerService in the same transaction
 * as each entry.
 *
 * @property int $id
 * @property int $water_account_id
 * @property numeric-string $balance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WaterAccount $waterAccount
 */
#[Fillable(['water_account_id', 'balance'])]
class WaterAccountBalance extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Get the water account the balance belongs to.
     *
     * @return BelongsTo<WaterAccount, $this>
     */
    public function waterAccount(): BelongsTo
    {
        return $this->belongsTo(WaterAccount::class);
    }
}
