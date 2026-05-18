<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of society expenses (D-19, Phase 7). An expense records a
 * cash outflow and posts a balanced cash-basis journal to the system ledger:
 * debit the expense-category account, credit the asset account it was paid
 * from. Like every other ledger-posting record here, an expense is immutable
 * once written — a mistake is corrected with a reversing journal, never an
 * edit.
 */
class ExpenseService
{
    public function __construct(
        protected SystemLedgerService $systemLedger,
        protected RunningNumberService $runningNumbers,
        protected AuditLogger $audit,
    ) {}

    /**
     * Record an expense and post its journal, all in one transaction so the
     * number, the row, and the balanced journal live or die together.
     */
    public function record(
        CarbonInterface $expenseDate,
        float $amount,
        SystemLedgerAccount $categoryAccount,
        SystemLedgerAccount $paidFromAccount,
        string $description,
        User $recordedBy,
        ?int $maintenanceJobId = null,
        ?UploadedFile $referenceImage = null,
    ): Expense {
        return DB::transaction(function () use (
            $expenseDate,
            $amount,
            $categoryAccount,
            $paidFromAccount,
            $description,
            $recordedBy,
            $maintenanceJobId,
            $referenceImage,
        ): Expense {
            $expense = Expense::query()->create([
                'expense_number' => $this->runningNumbers->next('expense'),
                'expense_date' => $expenseDate->toDateString(),
                'amount' => round($amount, 2),
                'category_account_id' => $categoryAccount->id,
                'paid_from_account_id' => $paidFromAccount->id,
                'maintenance_job_id' => $maintenanceJobId,
                'description' => $description,
                'recorded_by' => $recordedBy->id,
            ]);

            if ($referenceImage !== null) {
                $path = $referenceImage->store('expense-attachments');

                $expense->referenceImage()->create([
                    'uploaded_by' => $recordedBy->id,
                    'path' => $path,
                    'original_name' => $referenceImage->getClientOriginalName(),
                    'mime_type' => $referenceImage->getClientMimeType(),
                    'size' => $referenceImage->getSize(),
                ]);
            }

            $journal = $this->systemLedger->post(
                __('Expense :number — :description', [
                    'number' => $expense->expense_number,
                    'description' => $description,
                ]),
                [
                    ['account' => $categoryAccount->code, 'amount' => round($amount, 2), 'description' => $description],
                    ['account' => $paidFromAccount->code, 'amount' => -round($amount, 2), 'description' => __('Paid from :account', ['account' => $paidFromAccount->name])],
                ],
                $expenseDate,
                sourceType: 'expense',
                sourceId: $expense->id,
            );

            $expense->update(['system_ledger_entry_id' => $journal->id]);

            $this->audit->log('expense.recorded', $expense, [
                'expense_number' => $expense->expense_number,
                'amount' => round($amount, 2),
                'category' => $categoryAccount->code,
                'paid_from' => $paidFromAccount->code,
            ], $recordedBy);

            return $expense;
        });
    }
}
