<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SystemLedgerAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExpenseRequest;
use App\Libraries\Datatable;
use App\Models\Expense;
use App\Models\MaintenanceJob;
use App\Models\SystemLedgerAccount;
use App\Services\ExpenseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Society expense management (D-19, Phase 7): record cash outflows against an
 * expense-category account, each posting a balanced journal to the system
 * ledger. Expenses are create-and-view only — posted journals are immutable,
 * so a mistake is fixed with a reversing journal, never an edit.
 */
class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenses) {}

    /**
     * Show the expense register, newest first.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['expense_number', 'description'],
            orderColumns: ['expense_number', 'expense_date', 'amount', 'created_at'],
            defaultSort: 'expense_date',
            defaultDirection: 'desc',
        );

        $query = Expense::query()
            ->with(['categoryAccount:id,code,name', 'paidFromAccount:id,code,name', 'maintenanceJob:id,job_number']);

        $expenses = $datatable->paginate($query)
            ->through(fn (Expense $expense): array => [
                'id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'expense_date' => $expense->expense_date->format('d M Y'),
                'amount' => (float) $expense->amount,
                'category' => $expense->categoryAccount->name,
                'paid_from' => $expense->paidFromAccount->name,
                'description' => $expense->description,
                'job_number' => $expense->maintenanceJob?->job_number,
            ]);

        return Inertia::render('admin/expenses/Index', [
            'expenses' => $expenses,
            'filters' => $datatable->filters(),
        ]);
    }

    /**
     * Search maintenance jobs to optionally link an expense to (D-49).
     */
    public function jobs(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $jobs = MaintenanceJob::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = "%{$search}%";

                $query->where(fn (Builder $sub) => $sub
                    ->orWhere('job_number', 'like', $term)
                    ->orWhere('title', 'like', $term));
            })
            ->latest()
            ->limit(20)
            ->get(['id', 'job_number', 'title'])
            ->map(fn (MaintenanceJob $job): array => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'title' => $job->title,
            ]);

        return response()->json($jobs);
    }

    /**
     * Show the record-expense form, optionally prefilled from a job.
     */
    public function create(Request $request): Response
    {
        $job = $request->filled('job')
            ? MaintenanceJob::query()->findOrFail($request->integer('job'))
            : null;

        return Inertia::render('admin/expenses/Create', [
            'categoryAccounts' => $this->categoryOptions(),
            'paidFromAccounts' => $this->paidFromOptions(),
            'job' => $job === null ? null : [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'title' => $job->title,
            ],
        ]);
    }

    /**
     * Record the expense and post its journal, then land on its detail page.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $category = SystemLedgerAccount::query()->findOrFail($request->integer('category_account_id'));
        $paidFrom = SystemLedgerAccount::query()->findOrFail($request->integer('paid_from_account_id'));

        $expense = $this->expenses->record(
            Carbon::parse($request->validated('expense_date')),
            $request->float('amount'),
            $category,
            $paidFrom,
            $request->validated('description'),
            $request->user(),
            $request->integer('maintenance_job_id') ?: null,
            $request->file('reference_image'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':number recorded.', ['number' => $expense->expense_number]),
        ]);

        return to_route('admin.expenses.show', $expense);
    }

    /**
     * Show an expense with its accounts, journal, and optional job link.
     */
    public function show(Expense $expense): Response
    {
        $expense->load([
            'categoryAccount:id,code,name',
            'paidFromAccount:id,code,name',
            'maintenanceJob:id,job_number,title',
            'recorder:id,first_name,last_name',
            'journalEntry:id,reference_number',
            'referenceImage',
        ]);

        return Inertia::render('admin/expenses/Show', [
            'expense' => [
                'id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'expense_date' => $expense->expense_date->format('d M Y'),
                'amount' => (float) $expense->amount,
                'category' => $expense->categoryAccount->only(['code', 'name']),
                'paid_from' => $expense->paidFromAccount->only(['code', 'name']),
                'description' => $expense->description,
                'recorded_by' => $expense->recorder?->name,
                'recorded_at' => $expense->created_at?->format('d M Y H:i'),
                'job' => $expense->maintenanceJob === null ? null : [
                    'id' => $expense->maintenanceJob->id,
                    'job_number' => $expense->maintenanceJob->job_number,
                    'title' => $expense->maintenanceJob->title,
                ],
                'journal' => $expense->journalEntry === null ? null : [
                    'id' => $expense->journalEntry->id,
                    'reference_number' => $expense->journalEntry->reference_number,
                ],
                'reference_image' => $expense->referenceImage === null ? null : [
                    'name' => $expense->referenceImage->original_name,
                    'is_image' => $expense->referenceImage->isImage(),
                    'url' => route('attachments.download', $expense->referenceImage->id),
                ],
            ],
        ]);
    }

    /**
     * The active expense-category accounts an expense can be booked against.
     *
     * @return Collection<int, array{id: int, code: string, name: string}>
     */
    private function categoryOptions(): Collection
    {
        return SystemLedgerAccount::query()
            ->where('is_active', true)
            ->where('type', SystemLedgerAccountType::Expense)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (SystemLedgerAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->toBase();
    }

    /**
     * The active asset accounts money can be paid from, each with its current
     * natural balance so the form can warn on an overspend without blocking.
     *
     * @return Collection<int, array{id: int, code: string, name: string, balance: float}>
     */
    private function paidFromOptions(): Collection
    {
        return SystemLedgerAccount::query()
            ->withSum('lines as lines_sum', 'amount')
            ->where('is_active', true)
            ->where('type', SystemLedgerAccountType::Asset)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (SystemLedgerAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $account->naturalBalance((float) ($account->lines_sum ?? 0)),
            ])
            ->toBase();
    }
}
