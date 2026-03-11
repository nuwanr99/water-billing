<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\WaterAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Libraries\Datatable;
use App\Models\Payment;
use App\Models\SystemLedgerAccount;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payments in the admin dashboard: the oversight list plus the collection
 * flow (D-34) — search an account, review its snapshot (total due, last
 * bill, last payment, recent entries), record the payment, print the 80mm
 * receipt. The pages are responsive; mobile uses the same layout.
 */
class PaymentController extends Controller
{
    public function __construct(protected AccountLedgerService $accountLedger) {}

    /**
     * Show the payments list — oversight over every receipt.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['receipt_number', 'reference', 'waterAccount.account_number', 'waterAccount.owner.first_name', 'waterAccount.owner.last_name'],
            orderColumns: ['receipt_number', 'amount', 'method', 'status', 'paid_at', 'created_at'],
            defaultSort: 'paid_at',
            defaultDirection: 'desc',
        );

        $payments = $datatable
            ->paginate(Payment::query()->with(['waterAccount.owner:id,first_name,last_name', 'destinationAccount:id,name', 'recorder:id,first_name,last_name']))
            ->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'account_number' => $payment->waterAccount->account_number,
                'owner_name' => $payment->waterAccount->owner->name,
                'amount' => (float) $payment->amount,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'destination' => $payment->destinationAccount?->name,
                'reference' => $payment->reference,
                'has_attachment' => $payment->attachment_path !== null,
                'recorded_by' => $payment->recorder?->name,
                'paid_at' => $payment->paid_at->format('d M Y'),
            ]);

        return Inertia::render('admin/payments/Index', [
            'payments' => $payments,
            'filters' => $datatable->filters(),
        ]);
    }

    /**
     * Show the collection search: account cards with balances, searchable
     * by owner name, account number, or meter number.
     */
    public function collect(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $accounts = WaterAccount::query()
            ->where('status', WaterAccountStatus::Active)
            ->with(['owner:id,first_name,last_name', 'balanceRecord'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = "%{$search}%";

                $query->where(fn (Builder $subQuery) => $subQuery
                    ->where('account_number', 'like', $term)
                    ->orWhere('meter_number', 'like', $term)
                    ->orWhereHas('owner', fn (Builder $ownerQuery) => $ownerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)));
            })
            ->orderBy('account_number')
            ->get()
            ->map(fn (WaterAccount $waterAccount): array => [
                ...$this->accountSummary($waterAccount),
                'balance' => (float) ($waterAccount->balanceRecord->balance ?? 0),
            ]);

        return Inertia::render('admin/payments/Collect', [
            'accounts' => $accounts,
            'filters' => ['search' => $search === '' ? null : $search],
        ]);
    }

    /**
     * Show the account snapshot and the payment form — everything the
     * Treasurer needs to decide what to collect.
     */
    public function show(WaterAccount $waterAccount): Response
    {
        $waterAccount->load('owner:id,first_name,last_name');

        $lastBill = $waterAccount->bills()
            ->where('is_current', true)
            ->orderByDesc('account_ledger_entry_id')
            ->first();

        $lastPayment = Payment::query()
            ->where('water_account_id', $waterAccount->id)
            ->where('status', PaymentStatus::Completed)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('admin/payments/Record', [
            'account' => [
                ...$this->accountSummary($waterAccount),
                'balance' => $this->accountLedger->balanceFor($waterAccount),
            ],
            'lastBill' => $lastBill === null ? null : [
                'bill_number' => $lastBill->bill_number,
                'month_label' => Carbon::createFromFormat('Y-m', $lastBill->billing_month)->format('F Y'),
                'monthly_charge' => (float) $lastBill->monthly_charge,
                'total_due' => (float) $lastBill->total_due,
                'status' => $lastBill->status->value,
                'due_date' => $lastBill->due_date->format('d M Y'),
            ],
            'lastPayment' => $lastPayment === null ? null : [
                'receipt_number' => $lastPayment->receipt_number,
                'amount' => (float) $lastPayment->amount,
                'paid_at' => $lastPayment->paid_at->format('d M Y'),
            ],
            'recentEntries' => $waterAccount->ledgerEntries()
                ->reorder()
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn ($entry): array => [
                    'id' => $entry->id,
                    'type' => $entry->entry_type->value,
                    'date' => $entry->entry_date->format('d M Y'),
                    'description' => $entry->description,
                    'amount' => (float) $entry->amount,
                    'running_balance' => (float) $entry->running_balance,
                ]),
            'destinations' => SystemLedgerAccount::transferOptions(),
        ]);
    }

    /**
     * Record the payment and land on the printable receipt.
     */
    public function store(StorePaymentRequest $request, WaterAccount $waterAccount, PaymentService $payments): RedirectResponse
    {
        $payment = $payments->record(
            $waterAccount,
            $request->float('amount'),
            SystemLedgerAccount::query()->findOrFail($request->validated('destination_account_id')),
            $request->user(),
            $request->validated('reference'),
            Carbon::parse($request->validated('paid_at')),
            $request->file('attachment')?->store('payment-attachments'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':receipt — :amount received from :account.', [
                'receipt' => $payment->receipt_number,
                'amount' => number_format((float) $payment->amount, 2),
                'account' => $waterAccount->account_number,
            ]),
        ]);

        return to_route('admin.payments.receipt', $payment);
    }

    /**
     * The receipt screen: figures plus a link that opens the print sheet.
     */
    public function receipt(Request $request, Payment $payment): Response
    {
        abort_unless(
            $request->user()->can('payments.view-all') || $request->user()->can('payments.record-manual'),
            403,
        );

        return Inertia::render('admin/payments/Receipt', $this->receiptProps($payment));
    }

    /**
     * The bare 80mm print sheet (D-31): opened in a new tab, no layout,
     * auto-triggers the print dialog — close the tab when done.
     */
    public function receiptPrint(Request $request, Payment $payment): Response
    {
        abort_unless(
            $request->user()->can('payments.view-all') || $request->user()->can('payments.record-manual'),
            403,
        );

        return Inertia::render('print/PaymentReceipt', $this->receiptProps($payment));
    }

    /**
     * @return array{payment: array<string, mixed>, account: array<string, mixed>, org: array{name: string}}
     */
    private function receiptProps(Payment $payment): array
    {
        $payment->load(['waterAccount.owner', 'ledgerEntry', 'destinationAccount:id,code,name', 'recorder:id,first_name,last_name']);

        return [
            'payment' => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->method->value,
                'reference' => $payment->reference,
                'destination' => $payment->destinationAccount?->name,
                'paid_at' => $payment->paid_at->format('d M Y'),
                'balance_after' => (float) ($payment->ledgerEntry->running_balance ?? 0),
                'recorded_by' => $payment->recorder?->name,
                'attachment_url' => $payment->attachment_path === null
                    ? null
                    : route('admin.payments.attachment', $payment),
            ],
            'account' => $this->accountSummary($payment->waterAccount),
            'org' => [
                'name' => config('app.name'),
            ],
        ];
    }

    /**
     * Stream the payment's slip attachment (private disk — never public).
     */
    public function attachment(Request $request, Payment $payment): BinaryFileResponse
    {
        abort_unless(
            $request->user()->can('payments.view-all') || $request->user()->can('payments.record-manual'),
            403,
        );

        abort_if($payment->attachment_path === null, 404);

        return response()->file(Storage::path($payment->attachment_path));
    }

    /**
     * @return array{id: int, owner_name: string, account_number: string, meter_number: string}
     */
    private function accountSummary(WaterAccount $waterAccount): array
    {
        return [
            'id' => $waterAccount->id,
            'owner_name' => $waterAccount->owner->name,
            'account_number' => $waterAccount->account_number,
            'meter_number' => $waterAccount->meter_number,
        ];
    }
}
