<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a payment completes (D-28). Dispatched only after the posting
 * transaction commits. Listened to by SendReceiptWhatsAppNotification,
 * which queues WhatsApp delivery of the receipt.
 */
class PaymentReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Payment $payment) {}
}
