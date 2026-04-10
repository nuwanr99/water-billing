<?php

namespace App\Events;

use App\Models\Bill;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a bill is generated at reading confirmation (D-14). Dispatched
 * only after the generation transaction commits. Listened to by
 * SendBillWhatsAppNotification, which queues WhatsApp delivery of the bill.
 */
class BillGenerated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Bill $bill) {}
}
