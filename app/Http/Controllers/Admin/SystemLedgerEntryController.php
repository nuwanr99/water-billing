<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJournalEntryRequest;
use App\Http\Requests\Admin\StoreTransferRequest;
use App\Libraries\Datatable;
use App\Models\SystemLedgerAccount;
use App\Models\SystemLedgerEntry;
use App\Models\SystemLedgerLine;
use App\Services\AuditLogger;
use App\Services\SystemLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The society's journal (D-19). Entries are posted by payments (Phase 4)
 * and expenses (Phase 7), or recorded manually here by the Treasurer —
 * opening balances, corrections, donations. Posted journals are immutable:
 * a mistake is fixed by posting a reversing journal, never by editing.
 */
class SystemLedgerEntryController extends Controller
{
    /**
     * Show the journal: a chronological register, newest day first. Column
     * sorting is deliberately unavailable — a journal reads in date order.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['reference_number', 'description'],
            orderColumns: [
                'entry_date' => fn (Builder $query, string $direction) => $query
                    ->orderBy('entry_date', $direction === 'desc' ? 'desc' : 'asc')
                    ->orderBy('id', $direction === 'desc' ? 'desc' : 'asc'),
            ],
            defaultSort: 'entry_date',
            defaultDirection: 'desc',
        );

        $entries = $datatable
            ->paginate(SystemLedgerEntry::query())
            ->through(fn (SystemLedgerEntry $entry): array => [
                'id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'entry_date' => $entry->entry_date->toFormattedDateString(),
                'description' => $entry->description,
                'source_type' => $entry->source_type,
                'total_debit' => (float) $entry->total_debit,
                'total_credit' => (float) $entry->total_credit,
                'is_posted' => $entry->is_posted,
            ]);

        return Inertia::render('admin/system-ledger/Index', [
            'entries' => $entries,
            'filters' => $datatable->filters(),
            'transferAccounts' => SystemLedgerAccount::transferOptions(),
        ]);
    }

    /**
     * Show a journal entry with its lines.
     */
    public function show(SystemLedgerEntry $systemLedgerEntry): Response
    {
        $systemLedgerEntry->load('lines.account:id,code,name,type');

        return Inertia::render('admin/system-ledger/Show', [
            'entry' => [
                'id' => $systemLedgerEntry->id,
                'reference_number' => $systemLedgerEntry->reference_number,
                'entry_date' => $systemLedgerEntry->entry_date->toFormattedDateString(),
                'description' => $systemLedgerEntry->description,
                'source_type' => $systemLedgerEntry->source_type,
                'total_debit' => (float) $systemLedgerEntry->total_debit,
                'total_credit' => (float) $systemLedgerEntry->total_credit,
                'is_posted' => $systemLedgerEntry->is_posted,
                'created_at' => $systemLedgerEntry->created_at?->format('d M Y H:i'),
                'lines' => $systemLedgerEntry->lines->map(fn (SystemLedgerLine $line): array => [
                    'id' => $line->id,
                    'account_code' => $line->account->code,
                    'account_name' => $line->account->name,
                    'debit' => (float) $line->amount > 0 ? (float) $line->amount : null,
                    'credit' => (float) $line->amount < 0 ? abs((float) $line->amount) : null,
                    'description' => $line->description,
                ]),
            ],
        ]);
    }

    /**
     * Post a transfer journal: money moved between asset accounts (cash
     * box ↔ bank). Sugar over a two-line journal — debit the destination,
     * credit the source. Submitted from the transfer dialog; redirects
     * back so the hosting page refreshes in place.
     */
    public function storeTransfer(
        StoreTransferRequest $request,
        SystemLedgerService $systemLedger,
        AuditLogger $audit,
    ): RedirectResponse {
        $from = SystemLedgerAccount::query()->findOrFail($request->validated('from_account_id'));
        $to = SystemLedgerAccount::query()->findOrFail($request->validated('to_account_id'));
        $amount = (float) $request->validated('amount');

        $entry = $systemLedger->post(
            $request->validated('description') ?: __('Transfer from :from to :to', ['from' => $from->name, 'to' => $to->name]),
            [
                ['account' => $to->code, 'amount' => $amount, 'description' => __('Transfer in from :account', ['account' => $from->name])],
                ['account' => $from->code, 'amount' => -$amount, 'description' => __('Transfer out to :account', ['account' => $to->name])],
            ],
            Carbon::parse($request->validated('entry_date')),
            sourceType: 'transfer',
        );

        $audit->log('transfer.recorded', $entry, [
            'reference_number' => $entry->reference_number,
            'from' => $from->code,
            'to' => $to->code,
            'amount' => $amount,
        ], $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':reference posted — :amount moved from :from to :to.', [
                'reference' => $entry->reference_number,
                'amount' => number_format($amount, 2),
                'from' => $from->name,
                'to' => $to->name,
            ]),
        ]);

        return back();
    }

    /**
     * Show the manual journal entry page.
     */
    public function create(): Response
    {
        return Inertia::render('admin/system-ledger/Create', [
            'accounts' => SystemLedgerAccount::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type'])
                ->map(fn (SystemLedgerAccount $account): array => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type->value,
                ]),
        ]);
    }

    /**
     * Post a manual journal entry.
     */
    public function store(
        StoreJournalEntryRequest $request,
        SystemLedgerService $systemLedger,
        AuditLogger $audit,
    ): RedirectResponse {
        $accounts = SystemLedgerAccount::query()
            ->whereIn('id', collect($request->validated('lines'))->pluck('system_ledger_account_id'))
            ->get()
            ->keyBy('id');

        $lines = collect($request->validated('lines'))->map(fn (array $line): array => [
            'account' => $accounts[$line['system_ledger_account_id']]->code,
            'amount' => (float) ($line['debit'] ?? 0) > 0
                ? (float) $line['debit']
                : -(float) $line['credit'],
            'description' => $line['description'] ?? null,
        ])->all();

        $entry = $systemLedger->post(
            $request->validated('description'),
            $lines,
            Carbon::parse($request->validated('entry_date')),
            sourceType: 'manual',
        );

        $audit->log('journal.recorded', $entry, [
            'reference_number' => $entry->reference_number,
            'total_debit' => (float) $entry->total_debit,
        ], $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':reference posted.', ['reference' => $entry->reference_number]),
        ]);

        return to_route('admin.system-ledger.show', $entry);
    }
}
