<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\WaterAccount;

/**
 * PayHere hosted checkout (D-36): builds the signed checkout form fields
 * and verifies webhook signatures. One-time payments only — no recurring,
 * no tokenization.
 */
class PayHereService
{
    /**
     * Whether merchant credentials are configured.
     */
    public function configured(): bool
    {
        return (string) config('payhere.merchant_id') !== ''
            && (string) config('payhere.merchant_secret') !== '';
    }

    /**
     * The hosted checkout endpoint (sandbox or live).
     */
    public function checkoutUrl(): string
    {
        return config('payhere.sandbox')
            ? 'https://sandbox.payhere.lk/pay/checkout'
            : 'https://www.payhere.lk/pay/checkout';
    }

    /**
     * The form fields the pay page auto-submits to PayHere, including the
     * order hash: md5(merchant_id + order_id + amount + currency +
     * md5(secret)), uppercased.
     *
     * @return array<string, string>
     */
    public function checkoutFields(Payment $payment, WaterAccount $waterAccount): array
    {
        $owner = $waterAccount->owner;
        $amount = number_format((float) $payment->amount, 2, '.', '');
        $currency = (string) config('payhere.currency');
        $orderId = $this->orderId($payment);

        [$firstName, $lastName] = $this->splitName($owner->name);

        return [
            'merchant_id' => (string) config('payhere.merchant_id'),
            'return_url' => route('pay.result', $payment->public_token),
            'cancel_url' => route('pay.result', $payment->public_token),
            'notify_url' => route('payhere.notify'),
            'order_id' => $orderId,
            'items' => __('Water bill payment — :account', ['account' => $waterAccount->account_number]),
            'currency' => $currency,
            'amount' => $amount,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $owner->email ?? 'no-reply@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            'phone' => $owner->phone ?? $owner->wa_number ?? '0000000000',
            'address' => $waterAccount->connection_address ?? $owner->address ?? '-',
            'city' => 'Sri Lanka',
            'country' => 'Sri Lanka',
            'hash' => strtoupper(md5(
                config('payhere.merchant_id')
                .$orderId
                .$amount
                .$currency
                .strtoupper(md5((string) config('payhere.merchant_secret')))
            )),
        ];
    }

    /**
     * Verify a notify webhook's md5sig against OUR record, not the
     * payload: the expected signature is rebuilt from the configured
     * merchant id, the payment's own order id, amount, and currency —
     * only status_code is taken from the wire. This checks authenticity
     * and record-consistency in one comparison: a payload whose amount or
     * order was manipulated in transit can never match, because the
     * expected hash is derived from what we know the order to be.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(Payment $payment, array $payload): bool
    {
        $expected = strtoupper(md5(
            config('payhere.merchant_id')
            .$this->orderId($payment)
            .number_format((float) $payment->amount, 2, '.', '')
            .config('payhere.currency')
            .($payload['status_code'] ?? '')
            .strtoupper(md5((string) config('payhere.merchant_secret')))
        ));

        return hash_equals($expected, strtoupper((string) ($payload['md5sig'] ?? '')));
    }

    /**
     * The fields a PayHere notify payload may legitimately carry. Anything
     * else is stripped before the payload is stored or processed.
     *
     * @var list<string>
     */
    protected const array NOTIFY_FIELDS = [
        'merchant_id',
        'order_id',
        'payment_id',
        'payhere_amount',
        'payhere_currency',
        'status_code',
        'md5sig',
        'method',
        'status_message',
        'card_holder_name',
        'card_no',
        'card_expiry',
        'custom_1',
        'custom_2',
    ];

    /**
     * Reduce an incoming notify payload to the known PayHere fields, cast
     * to strings and truncated — the sanitized form is what gets verified,
     * processed, and stored in the payment's gateway history. Storage uses
     * bound parameters (no injection risk), so this is data hygiene: no
     * junk keys, no oversized values.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach (self::NOTIFY_FIELDS as $field) {
            if (! array_key_exists($field, $payload) || ! is_scalar($payload[$field])) {
                continue;
            }

            $sanitized[$field] = mb_substr((string) $payload[$field], 0, 200);
        }

        return $sanitized;
    }

    /**
     * The gateway order reference for a payment intent.
     */
    public function orderId(Payment $payment): string
    {
        return 'PMT-'.$payment->id;
    }

    /**
     * Resolve a webhook order_id back to its payment intent.
     */
    public function paymentFromOrderId(string $orderId): ?Payment
    {
        if (! str_starts_with($orderId, 'PMT-')) {
            return null;
        }

        return Payment::query()->find((int) substr($orderId, 4));
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0] ?: 'Member', $parts[1] ?? '-'];
    }
}
