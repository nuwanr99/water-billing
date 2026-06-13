<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\SystemLedgerAccountType;
use App\Libraries\Datatable;
use App\Libraries\ReportPeriod;
use App\Models\Expense;
use App\Models\MaintenanceJob;
use App\Models\SystemLedgerAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R5 Expense Summary (docs/management-reports.md): the society's operating
 * expenditure for the period, broken down by expense-category ledger
 * account, with the underlying expense lines. Category totals reconcile
 * with the debit side of the expense accounts in the system ledger (R6).
 */
class ExpenseSummaryReportController extends ReportController
{
    protected function title(): string
    {
        return 'Expense Summary';
    }

    /**
     * Show the expense summary for the requested period and filters.
     */
    public function index(Request $request): Response
    {
        $period = $this->period($request);
        $category = $this->requestedCategory($request);
        $job = $this->requestedJob($request);

        $datatable = new Datatable(
            $request,
            searchColumns: ['expense_number', 'description'],
            orderColumns: ['expense_number', 'expense_date', 'amount'],
            defaultSort: 'expense_date',
            defaultDirection: 'desc',
        );

        $expenses = $datatable->paginate($this->lineQuery($period, $category?->id, $job?->id))
            ->through(fn (Expense $expense): array => $this->expenseLine($expense));

        return Inertia::render('admin/reports/ExpenseSummary', [
            'period' => $period->filters(),
            'reportFilters' => [
                'category' => $category?->id,
                'job' => $job?->id,
            ],
            'categories' => $this->categoryOptions(),
            'jobs' => $this->jobOptions(),
            'summary' => $this->summary($period, $category?->id, $job?->id),
            'byCategory' => $this->byCategory($period, $job?->id),
            'expenses' => $expenses,
            'filters' => $datatable->filters(),
            'exportParams' => $this->exportParams($period, $category?->id, $job?->id),
        ]);
    }

    /**
     * Download the expense summary as an A4 PDF.
     */
    public function pdf(Request $request): HttpResponse
    {
        $period = $this->period($request);
        $category = $this->requestedCategory($request);
        $job = $this->requestedJob($request);

        return $this->pdfResponse('pdf.reports.expenses', $period, [
            'summary' => $this->summary($period, $category?->id, $job?->id),
            'byCategory' => $this->byCategory($period, $job?->id),
            'expenses' => $this->lineQuery($period, $category?->id, $job?->id)
                ->orderBy('expense_date')
                ->orderBy('expense_number')
                ->get()
                ->map(fn (Expense $expense): array => $this->expenseLine($expense))
                ->all(),
            'categoryFilter' => $category?->name,
            'jobFilter' => $job?->job_number,
        ]);
    }

    /**
     * Export the period's expense lines as CSV.
     */
    public function csv(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $category = $this->requestedCategory($request);
        $job = $this->requestedJob($request);

        $rows = $this->lineQuery($period, $category?->id, $job?->id)
            ->orderBy('expense_date')
            ->orderBy('expense_number')
            ->get()
            ->map(fn (Expense $expense): array => [
                $expense->expense_number,
                $expense->expense_date->toDateString(),
                $expense->categoryAccount->name,
                $expense->paidFromAccount->name,
                number_format((float) $expense->amount, 2, '.', ''),
                $expense->description,
                $expense->maintenanceJob?->job_number,
            ]);

        return $this->csvResponse($period, [
            'Expense number', 'Expense date', 'Category', 'Paid from', 'Amount', 'Description', 'Job',
        ], $rows);
    }

    /**
     * The expenses within the period, narrowed by the optional filters.
     *
     * @return Builder<Expense>
     */
    protected function lineQuery(ReportPeriod $period, ?int $categoryId, ?int $jobId): Builder
    {
        return Expense::query()
            ->with(['categoryAccount:id,code,name', 'paidFromAccount:id,code,name', 'maintenanceJob:id,job_number'])
            ->whereBetween('expense_date', [$period->start, $period->end])
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_account_id', $categoryId))
            ->when($jobId !== null, fn (Builder $query) => $query->where('maintenance_job_id', $jobId));
    }

    /**
     * The expense row shape shared by the page and the PDF.
     *
     * @return array{id: int, expense_number: string, expense_date: string, category: string, paid_from: string, amount: float, description: string, job_number: string|null}
     */
    protected function expenseLine(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_number' => $expense->expense_number,
            'expense_date' => $expense->expense_date->format('d M Y'),
            'category' => $expense->categoryAccount->name,
            'paid_from' => $expense->paidFromAccount->name,
            'amount' => (float) $expense->amount,
            'description' => $expense->description,
            'job_number' => $expense->maintenanceJob?->job_number,
        ];
    }

    /**
     * The headline tiles: total expenditure, expense count, and the number
     * of distinct expense categories used in the period.
     *
     * @return array{total_expenditure: float, expense_count: int, category_count: int}
     */
    protected function summary(ReportPeriod $period, ?int $categoryId, ?int $jobId): array
    {
        $base = fn (): Builder => $this->lineQuery($period, $categoryId, $jobId);

        return [
            'total_expenditure' => (float) $base()->sum('amount'),
            'expense_count' => (int) $base()->count(),
            'category_count' => (int) $base()->distinct()->count('category_account_id'),
        ];
    }

    /**
     * The per-category breakdown: count and total for every expense-category
     * account used in the period, regardless of the category filter (this is
     * the breakdown that reconciles with the ledger's expense accounts).
     *
     * @return list<array{account_id: int, code: string, name: string, expense_count: int, total: float}>
     */
    protected function byCategory(ReportPeriod $period, ?int $jobId): array
    {
        $rows = Expense::query()
            ->join('system_ledger_accounts', 'system_ledger_accounts.id', '=', 'expenses.category_account_id')
            ->whereBetween('expenses.expense_date', [$period->start, $period->end])
            ->when($jobId !== null, fn (Builder $query) => $query->where('expenses.maintenance_job_id', $jobId))
            ->groupBy('system_ledger_accounts.id', 'system_ledger_accounts.code', 'system_ledger_accounts.name')
            ->orderBy('system_ledger_accounts.code')
            ->selectRaw(
                'system_ledger_accounts.id as account_id,
                system_ledger_accounts.code as code,
                system_ledger_accounts.name as name,
                count(*) as expense_count,
                sum(expenses.amount) as total',
            )
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'account_id' => (int) $row->account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'expense_count' => (int) $row->expense_count,
                'total' => (float) $row->total,
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * The active expense-category accounts an expense can be filtered to
     * (mirrors ExpenseController::categoryOptions()).
     *
     * @return list<array{id: int, code: string, name: string}>
     */
    protected function categoryOptions(): array
    {
        $accounts = SystemLedgerAccount::query()
            ->where('is_active', true)
            ->where('type', SystemLedgerAccountType::Expense)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (SystemLedgerAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->all();

        return array_values($accounts);
    }

    /**
     * The maintenance jobs available for the job filter select.
     *
     * @return list<array{id: int, job_number: string, title: string}>
     */
    protected function jobOptions(): array
    {
        $jobs = MaintenanceJob::query()
            ->orderBy('job_number')
            ->get(['id', 'job_number', 'title'])
            ->map(fn (MaintenanceJob $job): array => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'title' => $job->title,
            ])
            ->all();

        return array_values($jobs);
    }

    protected function requestedCategory(Request $request): ?SystemLedgerAccount
    {
        $id = $request->integer('category');

        return $id > 0
            ? SystemLedgerAccount::query()->where('type', SystemLedgerAccountType::Expense)->find($id)
            : null;
    }

    protected function requestedJob(Request $request): ?MaintenanceJob
    {
        $id = $request->integer('job');

        return $id > 0 ? MaintenanceJob::query()->find($id) : null;
    }

    /**
     * The query string the export links reproduce this view with.
     *
     * @return array<string, string|int>
     */
    protected function exportParams(ReportPeriod $period, ?int $categoryId, ?int $jobId): array
    {
        return array_filter([
            ...$period->queryParameters(),
            'category' => $categoryId,
            'job' => $jobId,
        ], fn (string|int|null $value): bool => $value !== null && $value !== '');
    }
}
