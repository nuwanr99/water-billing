<?php

namespace App\Listeners;

use App\Events\BillGenerated;
use App\Jobs\SendBillViaWhatsApp;

/**
 * Queues WhatsApp delivery of every generated bill
 * The heavy work — PDF rendering and the gateway upload — happens
 * in the queued job, so bill generation itself stays fast.
 */
class SendBillWhatsAppNotification
{
    /**
     * Handle the event.
     */
    public function handle(BillGenerated $event): void
    {
        SendBillViaWhatsApp::dispatch($event->bill);
    }
}
