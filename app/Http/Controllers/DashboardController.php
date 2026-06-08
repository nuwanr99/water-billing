<?php

namespace App\Http\Controllers;

use App\Enums\BillStatus;
use App\Enums\ComplaintStatus;
use App\Enums\PaymentStatus;
use App\Enums\WaterAccountStatus;
use App\Models\Bill;
use App\Models\Complaint;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected AccountLedgerService $accountLedger,
    ) {}

    /**
     * Show the user dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $currentAccount = $user->resolveCurrentWaterAccount();
        $latestReading = $currentAccount?->latestReading;
        $currentBill = $currentAccount?->latestCurrentBill;

        return Inertia::render('Dashboard', [
            'currentAccount' => $currentAccount === null ? null : [
                'id' => $currentAccount->id,
                'account_number' => $currentAccount->account_number,
                'meter_number' => $currentAccount->meter_number,
                'connection_address' => $currentAccount->connection_address,
                'status' => $currentAccount->status->value,
                'connected_at' => $currentAccount->connected_at?->toFormattedDateString(),
                'balance' => $this->accountLedger->balanceFor($currentAccount),
                'pay_url' => route('my.pay.show', $currentAccount),
            ],
            'currentBill' => $currentBill === null ? null : [
                'id' => $currentBill->id,
                'bill_number' => $currentBill->bill_number,
                'month_label' => Carbon::createFromFormat('Y-m', $currentBill->billing_month)->format('F Y'),
                'total_due' => (float) $currentBill->total_due,
                'status' => $currentBill->status->value,
                'due_date' => $currentBill->due_date->format('d M Y'),
                'is_overdue' => $this->isOverdue($currentBill),
                'days_until_due' => (int) now()->startOfDay()->diffInDays($currentBill->due_date->startOfDay()),
                'pdf_url' => $user->can('view', $currentBill) ? route('bills.pdf', $currentBill) : null,
            ],
            'latestReading' => $latestReading === null ? null : [
                'value' => (float) $latestReading->reading_value,
                'consumption' => (float) $latestReading->consumption,
                'previous_consumption' => $latestReading->previousReading()?->consumption,
                'date' => $latestReading->reading_date->toFormattedDateString(),
            ],
            'usageHistory' => Inertia::defer(fn (): array => $this->usageHistory($currentAccount)),
            'recentPayments' => $this->recentPayments($currentAccount, $user->can('payments.view-own')),
            'activeAccountsCount' => $user->waterAccounts
                ->where('status', WaterAccountStatus::Active)
                ->count(),
            'can' => [
                'view_bills' => $user->can('bills.view-own'),
                'view_payments' => $user->can('payments.view-own'),
            ],
            'complaints' => [
                'can_submit' => $user->can('complaints.submit'),
                'open_count' => $user->complaints()->where('status', '!=', ComplaintStatus::Closed)->count(),
                'recent' => $user->complaints()
                    ->with('jobs:id,complaint_id,status')
                    ->latest('submitted_at')
                    ->limit(3)
                    ->get()
                    ->map(fn (Complaint $complaint): array => [
                        'id' => $complaint->id,
                        'complaint_number' => $complaint->complaint_number,
                        'subject' => $complaint->subject,
                        'status' => $complaint->status->value,
                        'job_status' => $complaint->jobs->sortByDesc('id')->first()?->status->value,
                        'submitted_at' => $complaint->submitted_at->toFormattedDateString(),
                    ]),
            ],
        ]);
    }

    /**
     * A bill counts as overdue once flagged by the reminder scheduler, or
     * as soon as its due date passes while still unpaid.
     */
    protected function isOverdue(Bill $bill): bool
    {
        if ($bill->status === BillStatus::Overdue) {
            return true;
        }

        return $bill->status !== BillStatus::Paid && $bill->due_date->isPast();
    }

    /**
     * The last 12 months of recorded consumption, oldest first.
     *
     * @return list<array{month: string, label: string, consumption: float}>
     */
    protected function usageHistory(?WaterAccount $account): array
    {
        if ($account === null) {
            return [];
        }

        return $account->readings()
            ->orderByDesc('billing_month')
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (MeterReading $reading): array => [
                'month' => $reading->billing_month,
                'label' => Carbon::createFromFormat('Y-m', $reading->billing_month)->format('M'),
                'consumption' => (float) $reading->consumption,
            ])
            ->all();
    }

    /**
     * The account's three most recent completed payments.
     *
     * @return list<array{id: int, receipt_number: string|null, amount: float, method: string, paid_at: string, receipt_url: string}>
     */
    protected function recentPayments(?WaterAccount $account, bool $canViewPayments): array
    {
        if ($account === null || ! $canViewPayments) {
            return [];
        }

        return Payment::query()
            ->where('water_account_id', $account->id)
            ->where('status', PaymentStatus::Completed)
            ->orderByDesc('paid_at')
            ->limit(3)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->method->value,
                'paid_at' => $payment->paid_at->format('d M Y'),
                'receipt_url' => route('my.payments.receipt', $payment),
            ])
            ->all();
    }
}
