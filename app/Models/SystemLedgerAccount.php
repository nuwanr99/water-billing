<?php

namespace App\Models;

use App\Enums\SystemLedgerAccountType;
use Database\Factories\SystemLedgerAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A chart-of-accounts row for the society's system ledger (D-19). Seeded
 * only — no management UI in this phase.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property SystemLedgerAccountType $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SystemLedgerLine> $lines
 * @property-read float|null $lines_sum
 * @property-read int|null $lines_count
 */
#[Fillable(['code', 'name', 'type', 'is_active'])]
class SystemLedgerAccount extends Model
{
    /** @use HasFactory<SystemLedgerAccountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SystemLedgerAccountType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the journal lines posted against this account.
     *
     * @return HasMany<SystemLedgerLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SystemLedgerLine::class);
    }

    /**
     * The active asset accounts offered as transfer endpoints — the only
     * account type money can physically move between.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, code: string, name: string}>
     */
    public static function transferOptions(): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where('type', SystemLedgerAccountType::Asset)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (SystemLedgerAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->toBase();
    }

    /**
     * Convert a signed line sum (+debit/−credit) into the account's natural
     * balance: asset and expense accounts are debit-natural, income,
     * liability, and equity accounts are credit-natural — so a healthy
     * account of any type reads as a positive figure.
     */
    public function naturalBalance(float $signedSum): float
    {
        return in_array($this->type, [SystemLedgerAccountType::Asset, SystemLedgerAccountType::Expense], true)
            ? round($signedSum, 2)
            : round(-$signedSum, 2);
    }
}
