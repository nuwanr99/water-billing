<?php

namespace App\Jobs;

use App\Enums\BillReminderType;
use App\Enums\BillStatus;
use App\Models\Bill;
use App\Services\AccountLedgerService;
use App\Services\BillPdfService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use RuntimeException;

/**
 * Delivers one payment reminder for a bill to the customer's WhatsApp
 * number: a Sinhala message with the bill PDF re-attached, rendered from
 * the bill's frozen snapshot so it is the same document the customer got
 * at generation. Re-checks everything at send time — the bill may have
 * been paid, superseded, or already reminded between dispatch and
 * execution — and records the delivery so each reminder type fires at
 * most once per bill. A gateway failure throws so the job retries.
 */
#[Tries(3)]
#[Backoff([60, 300])]
class SendBillReminderViaWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(public Bill $bill, public BillReminderType $type) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsapp, AccountLedgerService $accountLedger, BillPdfService $pdf): void
    {
        $bill = $this->bill->loadMissing('waterAccount.owner');

        if ($bill->is_current !== true || $bill->status === BillStatus::Paid || ! $whatsapp->configured()) {
            return;
        }

        if ($bill->reminders()->where('type', $this->type)->exists()) {
            return;
        }

        $balance = $accountLedger->balanceFor($bill->waterAccount);

        if ($balance <= 0) {
            return;
        }

        $owner = $bill->waterAccount->owner;
        $recipient = $owner->wa_number ?? $owner->phone;

        if (blank($recipient)) {
            return;
        }

        $sent = $whatsapp->sendDocument(
            $recipient,
            $this->message($bill, $balance),
            "{$bill->bill_number}.pdf",
            $pdf->render($bill),
        );

        if (! $sent) {
            throw new RuntimeException("WhatsApp gateway rejected {$this->type->value} reminder for bill {$bill->bill_number}; will retry.");
        }

        $bill->reminders()->create(['type' => $this->type, 'sent_at' => now()]);
    }

    /**
     * The Sinhala reminder matching the bill's position around its due date.
     */
    protected function message(Bill $bill, float $balance): string
    {
        $dueDate = $bill->due_date->format('d M Y');

        $lead = match ($this->type) {
            BillReminderType::DueSoon => 'ඔබගේ ජල බිල්පත '.$dueDate.' දිනට පෙර ගෙවිය යුතුය.',
            BillReminderType::DueDate => 'අද ('.$dueDate.') ඔබගේ ජල බිල්පත ගෙවීමේ අවසන් දිනයයි.',
            BillReminderType::Overdue => 'ඔබගේ ජල බිල්පත ගෙවීමේ දිනය ('.$dueDate.') ඉකුත් වී ඇත. කරුණාකර හැකි ඉක්මනින් ගෙවීම සිදු කරන්න.',
        };

        $payUrl = route('pay.show', [
            'account' => $bill->waterAccount->account_number,
            'meter' => $bill->waterAccount->meter_number,
        ]);

        return implode("\n", [
            config('app.name').' — ගෙවීම් සිහිකැඳවීම',
            '',
            $lead,
            '',
            'බිල් අංකය: '.$bill->bill_number,
            'ගිණුම් අංකය: '.$bill->waterAccount->account_number,
            'හිග මුදල: රු. '.number_format($balance, 2),
            '',
            'බිල්පත PDF ලෙස අමුණා ඇත.',
            'ඔන්ලයින් ගෙවීමට: '.$payUrl,
            'ගෙවීමක් දැනටමත් සිදු කර ඇත්නම්, මෙම පණිවිඩය නොසලකා හරින්න.',
        ]);
    }
}
