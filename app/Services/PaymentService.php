<?php

namespace App\Services;

use App\Enums\AccountLedgerEntryType;
use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SystemLedgerAccountType;
use App\Events\PaymentReceived;
use App\Models\AccountLedgerEntry;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Models\WaterAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of payments (D-28): posts the negative account-ledger
 * entry, the cash/income system-ledger journal, and settles bills
 * oldest-first (D-29) — one transaction. The PayHere webhook (D-33) will
 * reuse this same posting path on verified success.
 */
class PaymentService
{
    public function __construct(
        protected AccountLedgerService $accountLedger,
        protected SystemLedgerService $systemLedger,
        protected RunningNumberService $runningNumbers,
        protected AuditLogger $audit,
    ) {}

    /**
     * Record a completed manual payment against the account.
     */
    public function record(
        WaterAccount $waterAccount,
        float $amount,
        SystemLedgerAccount $destination,
        User $recordedBy,
        ?string $reference = null,
        ?CarbonInterface $paidAt = null,
        ?string $attachmentPath = null,
    ): Payment {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The payment amount must be positive.')]);
        }

        if ($destination->type !== SystemLedgerAccountType::Asset || ! $destination->is_active) {
            throw ValidationException::withMessages([
                'destination_account_id' => __('Payments must land in an active asset account (cash or bank).'),
            ]);
        }

        $paidAt ??= now();

        return DB::transaction(function () use ($waterAccount, $amount, $destination, $recordedBy, $reference, $paidAt, $attachmentPath): Payment {
            $receiptNumber = $this->runningNumbers->next('receipt');

            $entry = $this->accountLedger->post(
                $waterAccount,
                AccountLedgerEntryType::Payment,
                -$amount,
                __('Payment received — :receipt', ['receipt' => $receiptNumber]),
                recordedBy: $recordedBy,
                documentNumber: $receiptNumber,
                entryDate: $paidAt,
            );

            $payment = Payment::query()->create([
                'receipt_number' => $receiptNumber,
                'public_token' => Str::uuid()->toString(),
                'water_account_id' => $waterAccount->id,
                'account_ledger_entry_id' => $entry->id,
                'destination_account_id' => $destination->id,
                'method' => PaymentMethod::Manual,
                'status' => PaymentStatus::Completed,
                'amount' => $amount,
                'reference' => $reference,
                'attachment_path' => $attachmentPath,
                'recorded_by' => $recordedBy->id,
                'paid_at' => $paidAt,
            ]);

            $journal = $this->systemLedger->post(
                __('Payment :receipt from :owner (:account)', [
                    'receipt' => $receiptNumber,
                    'owner' => $waterAccount->owner->name,
                    'account' => $waterAccount->account_number,
                ]),
                [
                    ['account' => $destination->code, 'amount' => $amount, 'description' => __('Payment received')],
                    ['account' => '4000', 'amount' => -$amount, 'description' => __('Water charges income')],
                ],
                $paidAt,
                sourceType: 'payment',
                sourceId: $payment->id,
            );

            $payment->update(['system_ledger_entry_id' => $journal->id]);

            $this->settleBills($waterAccount);

            $this->audit->log('payment.recorded', $payment, [
                'receipt_number' => $receiptNumber,
                'amount' => $amount,
                'destination' => $destination->code,
            ], $recordedBy);

            PaymentReceived::dispatch($payment);

            return $payment;
        });
    }

    /**
     * Create a pending online-payment intent (D-36): no receipt number, no
     * ledger postings — nothing financial happens until the webhook
     * confirms. Abandoned checkouts simply stay pending.
     */
    public function createGatewayIntent(WaterAccount $waterAccount, float $amount): Payment
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The payment amount must be positive.')]);
        }

        return Payment::query()->create([
            'public_token' => Str::uuid()->toString(),
            'water_account_id' => $waterAccount->id,
            'method' => PaymentMethod::Payhere,
            'status' => PaymentStatus::Pending,
            'amount' => round($amount, 2),
            'paid_at' => now(),
        ]);
    }

    /**
     * Complete a gateway intent from a verified webhook: assign the receipt
     * number, post both ledgers (online money lands in the Bank account),
     * and settle bills — idempotent under a row lock, so duplicate webhook
     * deliveries post nothing twice.
     *
     * The posted amount is OUR intent's amount, never the payload's — the
     * signature verification already proved they agree, and the local
     * record is the source of truth for what enters the books.
     *
     * @param  array<string, mixed>  $payload
     */
    public function completeGateway(Payment $payment, array $payload): Payment
    {
        return DB::transaction(function () use ($payment, $payload): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::Completed) {
                return $payment;
            }

            $waterAccount = $payment->waterAccount;
            $amount = round((float) $payment->amount, 2);
            $receiptNumber = $this->runningNumbers->next('receipt');

            $bank = SystemLedgerAccount::query()->where('code', '1100')->firstOrFail();

            $entry = $this->accountLedger->post(
                $waterAccount,
                AccountLedgerEntryType::Payment,
                -$amount,
                __('Online payment — :receipt', ['receipt' => $receiptNumber]),
                documentNumber: $receiptNumber,
            );

            $journal = $this->systemLedger->post(
                __('Online payment :receipt from :owner (:account) via PayHere', [
                    'receipt' => $receiptNumber,
                    'owner' => $waterAccount->owner->name,
                    'account' => $waterAccount->account_number,
                ]),
                [
                    ['account' => $bank->code, 'amount' => $amount, 'description' => __('Payment received')],
                    ['account' => '4000', 'amount' => -$amount, 'description' => __('Water charges income')],
                ],
                now(),
                sourceType: 'payment',
                sourceId: $payment->id,
            );

            $payment->update([
                'receipt_number' => $receiptNumber,
                'account_ledger_entry_id' => $entry->id,
                'system_ledger_entry_id' => $journal->id,
                'destination_account_id' => $bank->id,
                'status' => PaymentStatus::Completed,
                'payhere_reference' => $payload['payment_id'] ?? null,
                'paid_at' => now(),
            ]);

            $this->settleBills($waterAccount);

            $this->audit->log('payment.completed', $payment, [
                'receipt_number' => $receiptNumber,
                'amount' => $amount,
                'gateway' => 'payhere',
            ]);

            PaymentReceived::dispatch($payment);

            return $payment;
        });
    }

    /**
     * Mark a gateway intent failed/canceled from a verified webhook. Only
     * pending intents transition — a completed payment is never downgraded.
     *
     * @param  array<string, mixed>  $payload
     */
    public function failGateway(Payment $payment, array $payload): void
    {
        DB::transaction(function () use ($payment, $payload) {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Pending) {
                return;
            }

            $payment->update([
                'status' => PaymentStatus::Failed,
                'payhere_reference' => $payload['payment_id'] ?? null,
            ]);

            $this->audit->log('payment.failed', $payment, [
                'status_code' => $payload['status_code'] ?? null,
                'gateway' => 'payhere',
            ]);
        });
    }

    /**
     * Append one gateway notification to the payment's history — every
     * webhook logs exactly once, success or not, so tampered or failed
     * attempts leave a trace alongside the real payment.
     *
     * @param  array<string, mixed>  $payload
     */
    public function logGatewayAttempt(Payment $payment, array $payload, bool $success, ?string $message = null): void
    {
        DB::transaction(function () use ($payment, $payload, $success, $message) {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $payment->update([
                'gateway_payload' => [
                    ...($payment->gateway_payload ?? []),
                    [
                        'timestamp' => now()->toIso8601String(),
                        'success' => $success,
                        'message' => $message,
                        'payload' => $payload,
                    ],
                ],
            ]);
        });
    }

    /**
     * Flip unpaid current bills to paid, oldest first (D-29). A bill is
     * settled once the account balance no longer contains any of it: the
     * balance is at most the debits posted after the bill's own
     * water_charge entry. Derived and idempotent — never user-driven.
     */
    public function settleBills(WaterAccount $waterAccount): void
    {
        $balance = $this->accountLedger->balanceFor($waterAccount);

        $unpaidBills = $waterAccount->bills()
            ->where('is_current', true)
            ->whereIn('status', [BillStatus::Approved, BillStatus::Overdue])
            ->orderBy('account_ledger_entry_id')
            ->get();

        foreach ($unpaidBills as $bill) {
            $owedBeyondBill = (float) AccountLedgerEntry::query()
                ->where('water_account_id', $waterAccount->id)
                ->where('id', '>', $bill->account_ledger_entry_id)
                ->where('amount', '>', 0)
                ->sum('amount');

            if ($balance <= $owedBeyondBill + 0.005) {
                $bill->update(['status' => BillStatus::Paid]);
            }
        }
    }
}
