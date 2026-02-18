<?php

namespace App\Console\Commands;

use App\Enums\AccountLedgerEntryType;
use App\Enums\BillStatus;
use App\Models\Bill;
use App\Services\AccountLedgerService;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('bills:mark-overdue')]
#[Description('Mark unpaid approved bills past their due date overdue and post the category late-fee penalty (D-23)')]
class MarkOverdueBills extends Command
{
    /**
     * Execute the console command. Idempotent: a bill transitions to
     * overdue exactly once, so the penalty posts exactly once.
     */
    public function handle(AccountLedgerService $accountLedger, AuditLogger $audit): int
    {
        $bills = Bill::query()
            ->where('is_current', true)
            ->where('status', BillStatus::Approved)
            ->whereDate('due_date', '<', today())
            ->with('waterAccount.billingCategory')
            ->get();

        foreach ($bills as $bill) {
            DB::transaction(function () use ($bill, $accountLedger, $audit) {
                $bill->update(['status' => BillStatus::Overdue]);

                $percent = (float) ($bill->waterAccount->billingCategory->late_fee_percent ?? 0);
                $penalty = round((float) $bill->monthly_charge * $percent / 100, 2);

                if ($penalty > 0) {
                    $accountLedger->post(
                        $bill->waterAccount,
                        AccountLedgerEntryType::Penalty,
                        $penalty,
                        __('Late fee :percent% on :bill', ['percent' => $percent, 'bill' => $bill->bill_number]),
                        billingMonth: $bill->billing_month,
                        metadata: ['bill_id' => $bill->id],
                    );
                }

                $audit->log('bill.overdue', $bill, [
                    'bill_number' => $bill->bill_number,
                    'late_fee' => $penalty,
                ]);
            });
        }

        $this->info("Marked {$bills->count()} bill(s) overdue.");

        return self::SUCCESS;
    }
}
