<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A journal header in the cash-basis system ledger (D-19). Created only by
 * SystemLedgerService, which refuses unbalanced journals.
 *
 * @property int $id
 * @property string $reference_number
 * @property Carbon $entry_date
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $description
 * @property numeric-string $total_debit
 * @property numeric-string $total_credit
 * @property bool $is_balanced
 * @property bool $is_posted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SystemLedgerLine> $lines
 */
#[Fillable(['reference_number', 'entry_date', 'source_type', 'source_id', 'description', 'total_debit', 'total_credit', 'is_balanced', 'is_posted'])]
class SystemLedgerEntry extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'is_balanced' => 'boolean',
            'is_posted' => 'boolean',
        ];
    }

    /**
     * Get the journal's lines in posting order.
     *
     * @return HasMany<SystemLedgerLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SystemLedgerLine::class)->orderBy('line_number');
    }
}
