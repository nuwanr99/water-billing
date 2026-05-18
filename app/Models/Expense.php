<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * A society expense (D-19, Phase 7): a cash outflow booked against an
 * expense-category account and paid from an asset account, posted to the
 * system ledger as a balanced journal. Optionally tied to a maintenance job.
 *
 * @property int $id
 * @property string $expense_number
 * @property Carbon $expense_date
 * @property numeric-string $amount
 * @property int $category_account_id
 * @property int $paid_from_account_id
 * @property int|null $maintenance_job_id
 * @property string $description
 * @property int $recorded_by
 * @property int|null $system_ledger_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SystemLedgerAccount $categoryAccount
 * @property-read SystemLedgerAccount $paidFromAccount
 * @property-read MaintenanceJob|null $maintenanceJob
 * @property-read User $recorder
 * @property-read SystemLedgerEntry|null $journalEntry
 * @property-read Attachment|null $referenceImage
 */
#[Fillable(['expense_number', 'expense_date', 'amount', 'category_account_id', 'paid_from_account_id', 'maintenance_job_id', 'description', 'recorded_by', 'system_ledger_entry_id'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The expense-category account this spend is booked against (debited).
     *
     * @return BelongsTo<SystemLedgerAccount, $this>
     */
    public function categoryAccount(): BelongsTo
    {
        return $this->belongsTo(SystemLedgerAccount::class, 'category_account_id');
    }

    /**
     * The asset account the money was paid from (credited).
     *
     * @return BelongsTo<SystemLedgerAccount, $this>
     */
    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(SystemLedgerAccount::class, 'paid_from_account_id');
    }

    /**
     * The maintenance job this expense settled, if any — the trail back to a
     * complaint.
     *
     * @return BelongsTo<MaintenanceJob, $this>
     */
    public function maintenanceJob(): BelongsTo
    {
        return $this->belongsTo(MaintenanceJob::class);
    }

    /**
     * The user who recorded the expense.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The system-ledger journal this expense posted.
     *
     * @return BelongsTo<SystemLedgerEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(SystemLedgerEntry::class, 'system_ledger_entry_id');
    }

    /**
     * The optional reference image — a receipt or photo of what was bought.
     *
     * @return MorphOne<Attachment, $this>
     */
    public function referenceImage(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }
}
