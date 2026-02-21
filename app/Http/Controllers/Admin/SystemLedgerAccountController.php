<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSystemLedgerAccountRequest;
use App\Http\Requests\Admin\UpdateSystemLedgerAccountRequest;
use App\Libraries\Datatable;
use App\Models\SystemLedgerAccount;
use App\Models\SystemLedgerLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Chart-of-accounts management for the system ledger (D-19). Accounts are
 * category buckets: asset accounts say where money is, income/expense
 * accounts say why it moved. An account referenced by journal lines can be
 * deactivated but never deleted, and its code/type are frozen.
 */
class SystemLedgerAccountController extends Controller
{
    /**
     * Show the chart of accounts with each account's natural balance.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['code', 'name'],
            orderColumns: ['code', 'name', 'type', 'is_active'],
            defaultSort: 'code',
            defaultDirection: 'asc',
        );

        $accounts = $datatable
            ->paginate(SystemLedgerAccount::query()->withSum('lines as lines_sum', 'amount')->withCount('lines'))
            ->through(fn (SystemLedgerAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'is_active' => $account->is_active,
                'lines_count' => $account->lines_count,
                'balance' => $account->naturalBalance((float) ($account->lines_sum ?? 0)),
            ]);

        return Inertia::render('admin/ledger-accounts/Index', [
            'accounts' => $accounts,
            'filters' => $datatable->filters(),
            'transferAccounts' => SystemLedgerAccount::transferOptions(),
        ]);
    }

    /**
     * Show the account's ledger: every journal line that touched it, day
     * order (newest first), with running balances computed over the same
     * date order so backdated journals slot in where they belong, plus
     * lifetime debit/credit totals. The table has no user sorting — a
     * running balance only reads in ledger order.
     */
    public function show(SystemLedgerAccount $systemLedgerAccount): Response
    {
        /** @var object{debits: string, credits: string}|null $totals */
        $totals = $systemLedgerAccount->lines()
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as debits')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) as credits')
            ->toBase()
            ->first();

        $lines = $systemLedgerAccount->lines()
            ->with('entry:id,reference_number,entry_date,description,source_type')
            ->select('system_ledger_lines.*')
            ->join('system_ledger_entries', 'system_ledger_entries.id', '=', 'system_ledger_lines.system_ledger_entry_id')
            ->selectRaw('SUM(system_ledger_lines.amount) OVER (ORDER BY system_ledger_entries.entry_date, system_ledger_lines.id) as running_amount')
            ->orderByDesc('system_ledger_entries.entry_date')
            ->orderByDesc('system_ledger_lines.id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (SystemLedgerLine $line): array => [
                'id' => $line->id,
                'entry_id' => $line->entry->id,
                'reference_number' => $line->entry->reference_number,
                'date' => $line->entry->entry_date->format('d M Y'),
                'description' => $line->description ?? $line->entry->description,
                'source_type' => $line->entry->source_type,
                'debit' => (float) $line->amount > 0 ? (float) $line->amount : null,
                'credit' => (float) $line->amount < 0 ? abs((float) $line->amount) : null,
                'balance' => $systemLedgerAccount->naturalBalance((float) $line->running_amount),
            ]);

        return Inertia::render('admin/ledger-accounts/Show', [
            'account' => [
                'id' => $systemLedgerAccount->id,
                'code' => $systemLedgerAccount->code,
                'name' => $systemLedgerAccount->name,
                'type' => $systemLedgerAccount->type->value,
                'is_active' => $systemLedgerAccount->is_active,
                'balance' => $systemLedgerAccount->naturalBalance((float) ($totals->debits ?? 0) - (float) ($totals->credits ?? 0)),
                'total_debits' => (float) ($totals->debits ?? 0),
                'total_credits' => (float) ($totals->credits ?? 0),
            ],
            'lines' => $lines,
            'transferAccounts' => SystemLedgerAccount::transferOptions(),
        ]);
    }

    /**
     * Show the create account page.
     */
    public function create(): Response
    {
        return Inertia::render('admin/ledger-accounts/Create');
    }

    /**
     * Store a new chart account.
     */
    public function store(StoreSystemLedgerAccountRequest $request): RedirectResponse
    {
        SystemLedgerAccount::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ledger account created.')]);

        return to_route('admin.ledger-accounts.index');
    }

    /**
     * Show the edit account page.
     */
    public function edit(SystemLedgerAccount $systemLedgerAccount): Response
    {
        $systemLedgerAccount->loadCount('lines');

        return Inertia::render('admin/ledger-accounts/Edit', [
            'account' => [
                'id' => $systemLedgerAccount->id,
                'code' => $systemLedgerAccount->code,
                'name' => $systemLedgerAccount->name,
                'type' => $systemLedgerAccount->type->value,
                'is_active' => $systemLedgerAccount->is_active,
                'lines_count' => $systemLedgerAccount->lines_count,
            ],
        ]);
    }

    /**
     * Update the given chart account.
     */
    public function update(UpdateSystemLedgerAccountRequest $request, SystemLedgerAccount $systemLedgerAccount): RedirectResponse
    {
        $systemLedgerAccount->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ledger account updated.')]);

        return to_route('admin.ledger-accounts.index');
    }

    /**
     * Delete the given chart account, unless journal lines reference it.
     */
    public function destroy(SystemLedgerAccount $systemLedgerAccount): RedirectResponse
    {
        if ($systemLedgerAccount->lines()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This account has journal entries and cannot be deleted. Deactivate it instead.'),
            ]);

            return back();
        }

        $systemLedgerAccount->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ledger account deleted.')]);

        return to_route('admin.ledger-accounts.index');
    }
}
