<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\BillStatus;
use App\Libraries\Datatable;
use App\Libraries\ReportPeriod;
use App\Models\Bill;
use App\Models\BillingCategory;
use App\Models\WaterAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R1 Billing Summary (docs/management-reports.md): what was billed in the
 * period, summarized by status and billing category, with the per-account
 * bill lines. Selecting an account narrows the report to that account's
 * billing statement over the period.
 */
class BillingSummaryReportController extends ReportController
{
    protected function title(): string
    {
        return 'Billing Summary';
    }

    /**
     * Show the billing summary for the requested period and filters.
     */
    public function index(Request $request): Response
    {
        $period = $this->period($request);
        $category = $this->requestedCategory($request);
        $status = BillStatus::tryFrom($request->string('status')->value());
        $account = $this->requestedAccount($request);

        $datatable = new Datatable(
            $request,
            searchColumns: ['bill_number', 'waterAccount.account_number'],
            orderColumns: ['bill_number', 'billing_month', 'total_due', 'due_date'],
            defaultSort: 'billing_month',
            defaultDirection: 'desc',
        );

        $bills = $datatable->paginate($this->lineQuery($period, $category?->id, $status, $account?->id))
            ->through(fn (Bill $bill): array => $this->billLine($bill));

        return Inertia::render('admin/reports/BillingSummary', [
            'period' => $period->filters(),
            'reportFilters' => [
                'category' => $category?->id,
                'status' => $status?->value,
                'account' => $account?->id,
            ],
            'categories' => BillingCategory::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => array_column(BillStatus::cases(), 'value'),
            'summary' => $this->summary($period, $category?->id, $status, $account?->id),
            'byCategory' => $this->byCategory($period, $status),
            'bills' => $bills,
            'filters' => $datatable->filters(),
            'statement' => $account === null ? null : $this->statementAccount($account),
            'exportParams' => $this->exportParams($period, $category?->id, $status, $account?->id),
        ]);
    }

    /**
     * Download the billing summary (or account statement) as an A4 PDF.
     */
    public function pdf(Request $request): HttpResponse
    {
        $period = $this->period($request);
        $category = $this->requestedCategory($request);
        $status = BillStatus::tryFrom($request->string('status')->value());
        $account = $this->requestedAccount($request);

        return $this->pdfResponse('pdf.reports.billing', $period, [
            'summary' => $this->summary($period, $category?->id, $status, $account?->id),
            'byCategory' => $account === null ? $this->byCategory($period, $status) : [],
            'bills' => $this->lineQuery($period, $category?->id, $status, $account?->id)
                ->orderBy('billing_month')
                ->orderBy('bill_number')
                ->get()
                ->map(fn (Bill $bill): array => $this->billLine($bill))
                ->all(),
            'statementAccount' => $account === null ? null : $this->statementAccount($account),
            'categoryFilter' => $category?->name,
            'statusFilter' => $status?->value,
        ]);
    }

    /**
     * Export the period's bill lines as CSV.
     */
    public function csv(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $category = $this->requestedCategory($request);
        $status = BillStatus::tryFrom($request->string('status')->value());
        $account = $this->requestedAccount($request);

        $rows = $this->lineQuery($period, $category?->id, $status, $account?->id)
            ->orderBy('billing_month')
            ->orderBy('bill_number')
            ->get()
            ->map(fn (Bill $bill): array => [
                $bill->bill_number,
                $bill->billing_month,
                $bill->waterAccount->account_number,
                $bill->waterAccount->owner->name,
                $bill->waterAccount->billingCategory->name,
                number_format((float) $bill->usage_charge, 2, '.', ''),
                number_format((float) $bill->service_charge, 2, '.', ''),
                number_format((float) $bill->monthly_charge, 2, '.', ''),
                number_format((float) $bill->previous_balance, 2, '.', ''),
                number_format((float) $bill->total_due, 2, '.', ''),
                $bill->status->value,
                $bill->due_date->toDateString(),
            ]);

        return $this->csvResponse($period, [
            'Bill number', 'Billing month', 'Account', 'Owner', 'Category',
            'Usage charge', 'Service charge', 'Monthly charge', 'Previous balance',
            'Total due', 'Status', 'Due date',
        ], $rows);
    }

    /**
     * Search water accounts for the statement account picker.
     */
    public function accounts(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $accounts = WaterAccount::query()
            ->with('owner:id,first_name,last_name')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = "%{$search}%";

                $query->where(fn (Builder $sub) => $sub
                    ->orWhere('account_number', 'like', $term)
                    ->orWhereHas('owner', fn (Builder $owner) => $owner
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)));
            })
            ->orderBy('account_number')
            ->limit(20)
            ->get(['id', 'account_number', 'user_id'])
            ->map(fn (WaterAccount $account): array => [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'owner' => $account->owner->name,
            ]);

        return response()->json($accounts);
    }

    /**
     * The current bills within the period, narrowed by the optional filters.
     *
     * @return Builder<Bill>
     */
    protected function lineQuery(ReportPeriod $period, ?int $categoryId, ?BillStatus $status, ?int $accountId): Builder
    {
        return Bill::query()
            ->with(['waterAccount.owner:id,first_name,last_name', 'waterAccount.billingCategory:id,name'])
            ->where('is_current', true)
            ->whereIn('billing_month', $period->months())
            ->when($accountId !== null, fn (Builder $query) => $query->where('water_account_id', $accountId))
            ->when($categoryId !== null, fn (Builder $query) => $query
                ->whereHas('waterAccount', fn (Builder $sub) => $sub->where('billing_category_id', $categoryId)))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status));
    }

    /**
     * The bill row shape shared by the page and the PDF.
     *
     * @return array{id: int, bill_number: string, billing_month: string, account_number: string, owner: string, category: string, usage_charge: float, service_charge: float, monthly_charge: float, previous_balance: float, total_due: float, status: string, due_date: string}
     */
    protected function billLine(Bill $bill): array
    {
        return [
            'id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'billing_month' => $bill->billing_month,
            'account_number' => $bill->waterAccount->account_number,
            'owner' => $bill->waterAccount->owner->name,
            'category' => $bill->waterAccount->billingCategory->name,
            'usage_charge' => (float) $bill->usage_charge,
            'service_charge' => (float) $bill->service_charge,
            'monthly_charge' => (float) $bill->monthly_charge,
            'previous_balance' => (float) $bill->previous_balance,
            'total_due' => (float) $bill->total_due,
            'status' => $bill->status->value,
            'due_date' => $bill->due_date->format('d M Y'),
        ];
    }

    /**
     * The headline tiles: total billed, bill count, and counts by status.
     *
     * @return array{total_billed: float, bill_count: int, status_counts: array<string, int>}
     */
    protected function summary(ReportPeriod $period, ?int $categoryId, ?BillStatus $status, ?int $accountId): array
    {
        $base = fn (): Builder => Bill::query()
            ->where('is_current', true)
            ->whereIn('billing_month', $period->months())
            ->when($accountId !== null, fn (Builder $query) => $query->where('water_account_id', $accountId))
            ->when($categoryId !== null, fn (Builder $query) => $query
                ->whereHas('waterAccount', fn (Builder $sub) => $sub->where('billing_category_id', $categoryId)))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status));

        $byStatus = $base()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total_billed' => (float) $base()->sum('total_due'),
            'bill_count' => (int) $byStatus->sum(),
            'status_counts' => collect(BillStatus::cases())
                ->mapWithKeys(fn (BillStatus $case): array => [$case->value => (int) $byStatus->get($case->value, 0)])
                ->all(),
        ];
    }

    /**
     * The per-billing-category breakdown. "Paid" follows the bill status:
     * payments settle the account balance rather than individual bills, so a
     * bill counts as paid once its status is Paid.
     *
     * @return list<array{category: string, bill_count: int, total_billed: float, total_paid: float, total_outstanding: float}>
     */
    protected function byCategory(ReportPeriod $period, ?BillStatus $status): array
    {
        $rows = Bill::query()
            ->join('water_accounts', 'water_accounts.id', '=', 'bills.water_account_id')
            ->join('billing_categories', 'billing_categories.id', '=', 'water_accounts.billing_category_id')
            ->where('bills.is_current', true)
            ->whereIn('bills.billing_month', $period->months())
            ->when($status !== null, fn (Builder $query) => $query->where('bills.status', $status))
            ->groupBy('billing_categories.id', 'billing_categories.name')
            ->orderBy('billing_categories.name')
            ->selectRaw(
                'billing_categories.name as category,
                count(*) as bill_count,
                sum(bills.total_due) as total_billed,
                sum(case when bills.status = ? then bills.total_due else 0 end) as total_paid',
                [BillStatus::Paid->value],
            )
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'category' => (string) $row->category,
                'bill_count' => (int) $row->bill_count,
                'total_billed' => (float) $row->total_billed,
                'total_paid' => (float) $row->total_paid,
                'total_outstanding' => round((float) $row->total_billed - (float) $row->total_paid, 2),
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * The account header block for statement mode.
     *
     * @return array{account_number: string, owner: string, category: string, connection_address: string}
     */
    protected function statementAccount(WaterAccount $account): array
    {
        return [
            'account_number' => $account->account_number,
            'owner' => $account->owner->name,
            'category' => $account->billingCategory->name,
            'connection_address' => $account->connection_address,
        ];
    }

    protected function requestedCategory(Request $request): ?BillingCategory
    {
        $id = $request->integer('category');

        return $id > 0 ? BillingCategory::query()->find($id) : null;
    }

    protected function requestedAccount(Request $request): ?WaterAccount
    {
        $id = $request->integer('account');

        return $id > 0
            ? WaterAccount::query()->with(['owner:id,first_name,last_name', 'billingCategory:id,name'])->find($id)
            : null;
    }

    /**
     * The query string the export links reproduce this view with.
     *
     * @return array<string, string|int>
     */
    protected function exportParams(ReportPeriod $period, ?int $categoryId, ?BillStatus $status, ?int $accountId): array
    {
        return array_filter([
            ...$period->queryParameters(),
            'category' => $categoryId,
            'status' => $status?->value,
            'account' => $accountId,
        ], fn (string|int|null $value): bool => $value !== null && $value !== '');
    }
}
