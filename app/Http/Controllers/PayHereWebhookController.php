<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Services\AuditLogger;
use App\Services\PayHereService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * PayHere server-to-server notification (D-36). CSRF-exempt; every payload
 * is verified by md5sig recomputation against the merchant secret — a
 * mismatch is rejected and audit-logged, and nothing is posted.
 *
 * status_code: 2 = success, 0 = pending, -1 = canceled, -2 = failed,
 * -3 = chargedback.
 */
class PayHereWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PayHereService $payHere,
        PaymentService $payments,
        AuditLogger $audit,
    ): Response {

        $payload = $payHere->sanitizePayload($request->all());

        $payment = $payHere->paymentFromOrderId((string) ($payload['order_id'] ?? ''));

        if ($payment === null) {
            Log::warning('PayHere webhook for unknown order', ['order_id' => $payload['order_id'] ?? null]);

            return response('Unknown order', 404);
        }

        if (! $payHere->verifySignature($payment, $payload)) {
            $payments->logGatewayAttempt($payment, $payload, false, 'Signature mismatch against local record.');

            $audit->log('payment.webhook-rejected', $payment, [
                'reason' => 'md5sig mismatch against local record',
                'order_id' => $payload['order_id'] ?? null,
                'payhere_amount' => $payload['payhere_amount'] ?? null,
                'expected_amount' => (float) $payment->amount,
                'ip' => $request->ip(),
            ]);

            Log::warning('PayHere webhook rejected: md5sig mismatch', ['order_id' => $payload['order_id'] ?? null]);

            return response('Invalid signature', 400);
        }

        $statusCode = (int) ($payload['status_code'] ?? 0);

        if ($statusCode === 2) {
            $alreadyCompleted = $payment->status === PaymentStatus::Completed;

            $payments->completeGateway($payment, $payload);
            $payments->logGatewayAttempt($payment, $payload, true, $alreadyCompleted
                ? 'Duplicate notification — payment already completed.'
                : 'Payment completed.');
        } elseif (in_array($statusCode, [-1, -2, -3], true)) {
            $payments->failGateway($payment, $payload);
            $payments->logGatewayAttempt($payment, $payload, false, $payload['status_message'] ?? 'Payment canceled or failed.');
        } else {
            // status 0 (pending) changes nothing — a follow-up notification
            // arrives when the payment settles — but keep it in the history.
            $payments->logGatewayAttempt($payment, $payload, false, 'Pending notification — awaiting final status.');
        }

        return response('OK');
    }
}
