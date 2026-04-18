<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Jobs\SendReceiptViaWhatsApp;

/**
 * Queues WhatsApp delivery of every completed payment's receipt — manual
 * and gateway alike, since both dispatch PaymentReceived. The heavy work
 * happens in the queued job, so payment recording stays fast.
 */
class SendReceiptWhatsAppNotification
{
    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        SendReceiptViaWhatsApp::dispatch($event->payment);
    }
}
