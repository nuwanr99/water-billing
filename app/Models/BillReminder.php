<?php

namespace App\Models;

use App\Enums\BillReminderType;
use Database\Factories\BillReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment reminder delivered for a bill over WhatsApp. The unique
 * (bill_id, type) pair makes every reminder type fire at most once per
 * bill, so manual command runs never double-message customers.
 *
 * @property int $id
 * @property int $bill_id
 * @property BillReminderType $type
 * @property Carbon $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Bill $bill
 */
#[Fillable(['bill_id', 'type', 'sent_at'])]
class BillReminder extends Model
{
    /** @use HasFactory<BillReminderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BillReminderType::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the bill the reminder was sent for.
     *
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
