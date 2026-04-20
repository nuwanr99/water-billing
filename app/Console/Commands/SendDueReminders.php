<?php

namespace App\Console\Commands;

use App\Enums\BillReminderType;
use App\Enums\BillStatus;
use App\Jobs\SendBillReminderViaWhatsApp;
use App\Models\Bill;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bills:send-due-reminders')]
#[Description('Queue WhatsApp due-date reminders for unpaid bills due three days from now')]
class SendDueReminders extends Command
{
    /**
     * Execute the console command. Idempotent: bills already reminded are
     * excluded here and re-checked in the job, so manual runs alongside
     * the schedule never double-message customers.
     */
    public function handle(): int
    {
        $type = BillReminderType::DueSoon;

        $bills = Bill::query()
            ->where('is_current', true)
            ->whereIn('status', [BillStatus::Approved, BillStatus::Overdue])
            ->whereDate('due_date', $type->dueDateFor())
            ->whereDoesntHave('reminders', fn ($query) => $query->where('type', $type))
            ->get();

        foreach ($bills as $bill) {
            SendBillReminderViaWhatsApp::dispatch($bill, $type);
        }

        $this->info("Queued {$bills->count()} due-date reminder(s).");

        return self::SUCCESS;
    }
}
