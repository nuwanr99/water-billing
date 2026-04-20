<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The three payment reminders a bill can receive over WhatsApp: a heads-up
 * three days before the due date, one on the due date itself, and a final
 * one three days after it passed. Each fires at most once per bill.
 */
enum BillReminderType: string
{
    case DueSoon = 'due_soon';
    case DueDate = 'due_date';
    case Overdue = 'overdue';

    /**
     * The due date a bill must carry to receive this reminder today.
     */
    public function dueDateFor(?CarbonInterface $today = null): CarbonInterface
    {
        $today = Carbon::parse($today ?? today());

        return match ($this) {
            self::DueSoon => $today->copy()->addDays(3),
            self::DueDate => $today->copy(),
            self::Overdue => $today->copy()->subDays(3),
        };
    }
}
