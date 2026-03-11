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
