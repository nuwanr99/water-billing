<?php

namespace App\Jobs;

use App\Models\Bill;
use App\Services\BillPdfService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Delivers a freshly generated bill to the customer's WhatsApp number: a
 * Sinhala summary message with the bill PDF attached as a document upload.
 * Skipped silently when the customer has no number or the bill has been
 * superseded before the queue got to it; a gateway failure throws so the
 * job retries.
 */
#[Tries(3)]
#[Backoff([60, 300])]
class SendBillViaWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(public Bill $bill) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsapp, BillPdfService $pdf): void
    {
        $bill = $this->bill->loadMissing('waterAccount.owner');

        if ($bill->is_current !== true || ! $whatsapp->configured()) {
            return;
        }

        $owner = $bill->waterAccount->owner;
        $recipient = $owner->wa_number ?? $owner->phone;

        if (blank($recipient)) {
            return;
        }

        $sent = $whatsapp->sendDocument(
            $recipient,
            $this->message($bill),
            "{$bill->bill_number}.pdf",
            $pdf->render($bill),
        );

        if (! $sent) {
            throw new RuntimeException("WhatsApp gateway rejected bill {$bill->bill_number}; will retry.");
        }
    }

    /**
     * The Sinhala summary accompanying the attached bill PDF.
     */
    protected function message(Bill $bill): string
    {
        $formatLkr = fn (float $value): string => ($value < 0 ? 'CR ' : '').'රු. '.number_format(abs($value), 2);

        $payUrl = route('pay.show', [
            'account' => $bill->waterAccount->account_number,
            'meter' => $bill->waterAccount->meter_number,
        ]);

        return implode("\n", [
            config('app.name').' — මාසික ජල බිල්පත',
            '',
            'බිල් අංකය: '.$bill->bill_number,
            'මාසය: '.Carbon::createFromFormat('Y-m', $bill->billing_month)->format('F Y'),
            'ගිණුම් අංකය: '.$bill->waterAccount->account_number,
            'මේ මස එකතුව: '.$formatLkr((float) $bill->monthly_charge),
            'ගෙවිය යුතු මුළු මුදල: '.$formatLkr((float) $bill->total_due),
            'ගෙවිය යුතු දිනය: '.$bill->due_date->format('d M Y'),
            '',
            'බිල්පත PDF ලෙස අමුණා ඇත.',
            'ඔන්ලයින් ගෙවීමට: '.$payUrl,
        ]);
    }
}
