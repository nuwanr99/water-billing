<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillStatus;
use App\Enums\ComplaintStatus;
use App\Enums\MaintenanceJobStatus;
use App\Enums\PaymentStatus;
use App\Enums\WaterAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\MaintenanceJob;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterAccount;
use App\Models\WaterAccountBalance;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard. Every widget is gated by its module's view
     * permission — widgets the user cannot see are sent as null and never
     * computed.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $month = now()->format('Y-m');

        return Inertia::render('admin/Dashboard', [
            'monthLabel' => now()->format('F Y'),
            'billing' => $user->can('bills.view') ? $this->billing($month) : null,
            'collections' => $user->can('payments.view-all') ? $this->collections() : null,
            'outstanding' => $user->can('ledger.view') ? $this->outstanding() : null,
            'accounts' => $user->can('water-accounts.view') ? $this->accounts() : null,
            'readings' => $user->can('readings.view') ? $this->readings($month) : null,
            'complaints' => $user->can('complaints.view-all') ? $this->complaints() : null,
            'maintenanceJobs' => $user->can('maintenance-jobs.view-all') ? $this->maintenanceJobs() : null,
            'myJobs' => $user->can('maintenance-jobs.view-assigned') ? $this->myJobs($user) : null,
            'expenses' => $user->can('expenses.view') ? $this->expenses() : null,
            'inventory' => $user->can('inventory.view') ? $this->inventory() : null,
        ]);
    }

    /**
     * Current-month billing totals plus society-wide unpaid/overdue counts.
     *
     * @return array{month_total: float, month_count: int, unpaid_count: int, overdue_count: int}
     */
    protected function billing(string $month): array
    {
        $monthBills = Bill::query()
            ->where('is_current', true)
            ->where('billing_month', $month);

        return [
            'month_total' => (float) (clone $monthBills)->sum('total_due'),
            'month_count' => (clone $monthBills)->count(),
            'unpaid_count' => Bill::query()
                ->where('is_current', true)
                ->where('status', '!=', BillStatus::Paid)
                ->count(),
            'overdue_count' => Bill::query()
                ->where('is_current', true)
                ->where('status', '!=', BillStatus::Paid)
                ->where(fn (Builder $query) => $query
                    ->where('status', BillStatus::Overdue)
                    ->orWhereDate('due_date', '<', today()))
                ->count(),
        ];
    }

    /**
     * Completed-payment totals and the latest receipts across all accounts.
     *
     * @return array{month_total: float, today_total: float, recent: list<array{id: int, receipt_number: string|null, account_number: string, amount: float, method: string, paid_at: string, receipt_url: string}>}
     */
    protected function collections(): array
    {
        $completed = Payment::query()->where('status', PaymentStatus::Completed);

        return [
            'month_total' => (float) (clone $completed)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'today_total' => (float) (clone $completed)
                ->whereDate('paid_at', today())
                ->sum('amount'),
            'recent' => (clone $completed)
                ->with('waterAccount:id,account_number')
                ->orderByDesc('paid_at')
                ->limit(5)
                ->get()
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'account_number' => $payment->waterAccount->account_number,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method->value,
                    'paid_at' => $payment->paid_at->format('d M Y'),
                    'receipt_url' => route('admin.payments.receipt', $payment),
                ])
                ->all(),
        ];
    }

    /**
     * Society-wide arrears from the per-account running balances.
     *
     * @return array{total_due: float, accounts_in_arrears: int}
     */
    protected function outstanding(): array
    {
        $inArrears = WaterAccountBalance::query()->where('balance', '>', 0);

        return [
            'total_due' => (float) (clone $inArrears)->sum('balance'),
            'accounts_in_arrears' => (clone $inArrears)->count(),
        ];
    }

    /**
     * Connection counts by status.
     *
     * @return array{active: int, inactive: int}
     */
    protected function accounts(): array
    {
        $countsByStatus = WaterAccount::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'active' => (int) $countsByStatus->get(WaterAccountStatus::Active->value, 0),
            'inactive' => (int) $countsByStatus->get(WaterAccountStatus::Inactive->value, 0),
        ];
    }

    /**
     * This month's meter-reading progress against the active connections.
     *
     * @return array{recorded: int, active_accounts: int}
     */
    protected function readings(string $month): array
    {
        return [
            'recorded' => MeterReading::query()->where('billing_month', $month)->count(),
            'active_accounts' => WaterAccount::query()
                ->where('status', WaterAccountStatus::Active)
                ->count(),
        ];
    }

    /**
     * Unresolved complaint counts and the oldest ones still waiting.
     *
     * @return array{open_count: int, in_progress_count: int, recent: list<array{id: int, complaint_number: string, subject: string, status: string, member_name: string, submitted_at: string}>}
     */
    protected function complaints(): array
    {
        return [
            'open_count' => Complaint::query()->where('status', ComplaintStatus::Open)->count(),
            'in_progress_count' => Complaint::query()->where('status', ComplaintStatus::InProgress)->count(),
            'recent' => Complaint::query()
                ->with('member:id,first_name,last_name')
                ->where('status', '!=', ComplaintStatus::Closed)
                ->latest('submitted_at')
                ->limit(4)
                ->get()
                ->map(fn (Complaint $complaint): array => [
                    'id' => $complaint->id,
                    'complaint_number' => $complaint->complaint_number,
                    'subject' => $complaint->subject,
                    'status' => $complaint->status->value,
                    'member_name' => $complaint->member->name,
                    'submitted_at' => $complaint->submitted_at->toFormattedDateString(),
                ])
                ->all(),
        ];
    }

    /**
     * Open job counts and the next ones on the schedule.
     *
     * @return array{open_count: int, due_count: int, upcoming: list<array{id: int, job_number: string, title: string, status: string, scheduled_date: string}>}
     */
    protected function maintenanceJobs(): array
    {
        $open = MaintenanceJob::query()
            ->whereIn('status', [MaintenanceJobStatus::Assigned, MaintenanceJobStatus::InProgress]);

        return [
            'open_count' => (clone $open)->count(),
            'due_count' => (clone $open)->whereDate('scheduled_date', '<=', today())->count(),
            'upcoming' => (clone $open)
                ->orderBy('scheduled_date')
                ->limit(4)
                ->get()
                ->map(fn (MaintenanceJob $job): array => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'title' => $job->title,
                    'status' => $job->status->value,
                    'scheduled_date' => $job->scheduled_date->format('d M Y'),
                ])
                ->all(),
        ];
    }

    /**
     * The current user's own open assignments, so staff working in the admin
     * area don't miss jobs assigned to them personally.
     *
     * @return array{open_count: int, open: list<array{id: int, job_number: string, title: string, status: string, scheduled_date: string, complaint_number: string|null}>}
     */
    protected function myJobs(User $user): array
    {
        $open = $user->assignedJobs()
            ->whereIn('status', [MaintenanceJobStatus::Assigned, MaintenanceJobStatus::InProgress]);

        return [
            'open_count' => (clone $open)->count(),
            'open' => (clone $open)
                ->with('complaint:id,complaint_number')
                ->orderBy('scheduled_date')
                ->limit(4)
                ->get()
                ->map(fn (MaintenanceJob $job): array => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'title' => $job->title,
                    'status' => $job->status->value,
                    'scheduled_date' => $job->scheduled_date->format('d M Y'),
                    'complaint_number' => $job->complaint?->complaint_number,
                ])
                ->all(),
        ];
    }

    /**
     * Current-month spend booked through the expenses module.
     *
     * @return array{month_total: float, month_count: int}
     */
    protected function expenses(): array
    {
        $monthExpenses = Expense::query()
            ->whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            'month_total' => (float) (clone $monthExpenses)->sum('amount'),
            'month_count' => (clone $monthExpenses)->count(),
        ];
    }

    /**
     * Active stock items at or below their reorder level.
     *
     * @return array{low_stock_count: int, low_stock_items: list<array{id: int, name: string, quantity_in_stock: int, unit: string, reorder_level: int}>}
     */
    protected function inventory(): array
    {
        $lowStock = InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('quantity_in_stock', '<=', 'reorder_level');

        return [
            'low_stock_count' => (clone $lowStock)->count(),
            'low_stock_items' => (clone $lowStock)
                ->orderBy('quantity_in_stock')
                ->limit(4)
                ->get()
                ->map(fn (InventoryItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity_in_stock' => $item->quantity_in_stock,
                    'unit' => $item->unit,
                    'reorder_level' => $item->reorder_level,
                ])
                ->all(),
        ];
    }
}
