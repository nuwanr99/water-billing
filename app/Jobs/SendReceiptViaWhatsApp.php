<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\ReceiptPdfService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use RuntimeException;

/**
 * Delivers a completed payment's receipt to the customer's WhatsApp number:
 * a Sinhala confirmation with the remaining balance and the receipt PDF —
 * the same ticket document the public download serves — attached. Skipped
 * silently when the customer has no number; a gateway failure throws so
 * the job retries.
 */
#[Tries(3)]
#[Backoff([60, 300])]
class SendReceiptViaWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsapp, ReceiptPdfService $pdf): void
    {
        $payment = $this->payment->loadMissing(['waterAccount.owner', 'ledgerEntry']);

        if ($payment->status !== PaymentStatus::Completed || ! $whatsapp->configured()) {
            return;
        }

        $owner = $payment->waterAccount->owner;
        $recipient = $owner->wa_number ?? $owner->phone;

        if (blank($recipient)) {
            return;
        }

        $sent = $whatsapp->sendDocument(
            $recipient,
            $this->message($payment),
            "{$payment->receipt_number}.pdf",
            $pdf->render($payment),
        );

        if (! $sent) {
            throw new RuntimeException("WhatsApp gateway rejected receipt {$payment->receipt_number}; will retry.");
        }
    }

    /**
     * The Sinhala confirmation accompanying the attached receipt PDF.
     */
    protected function message(Payment $payment): string
    {
        $formatLkr = fn (float $value): string => ($value < 0 ? 'CR ' : '').'රු. '.number_format(abs($value), 2);

        return implode("\n", [
            config('app.name').' — මුදල් ලදුපත',
            '',
            'ඔබගේ ගෙවීම ලැබී ඇත. ස්තූතියි!',
            '',
            'ලදුපත් අංකය: '.$payment->receipt_number,
            'ගිණුම් අංකය: '.$payment->waterAccount->account_number,
            'ගෙවූ මුදල: '.$formatLkr((float) $payment->amount),
            'ඉතිරි හිග මුදල: '.$formatLkr((float) ($payment->ledgerEntry->running_balance ?? 0)),
            'දිනය: '.$payment->paid_at->format('d M Y H:i'),
            '',
            'ලදුපත PDF ලෙස අමුණා ඇත.',
        ]);
    }
}
