<?php

namespace App\Services;

use App\Models\Payment;

/**
 * Renders a completed payment's receipt as a Sinhala PDF in the same 78mm
 * ticket layout the receipt page prints. The one template serves both the
 * public download and the WhatsApp attachment, so they are always the
 * exact same document.
 */
class ReceiptPdfService extends TicketPdfService
{
    /**
     * Render the receipt PDF and return the raw document bytes.
     */
    public function render(Payment $payment): string
    {
        $payment->loadMissing(['waterAccount.owner', 'ledgerEntry', 'recorder:id,first_name,last_name']);

        $html = view('pdf.receipt', [
            'payment' => $payment,
            'account' => $payment->waterAccount,
            'orgName' => config('app.name'),
            'balanceAfter' => (float) ($payment->ledgerEntry->running_balance ?? 0),
        ])->render();

        return $this->renderTicket($html);
    }
}
