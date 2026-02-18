<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillStatus;
use App\Http\Controllers\Controller;
use App\Libraries\Datatable;
use App\Models\Bill;
use App\Services\BillGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    /**
     * Show the bills list — current bills only; superseded ones surface on
     * the bill detail as history.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['bill_number', 'billing_month', 'waterAccount.account_number', 'waterAccount.owner.first_name', 'waterAccount.owner.last_name'],
            orderColumns: [
                'bill_number',
                'billing_month',
                'total_due',
                'status',
                'due_date',
                'created_at',
            ],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $bills = $datatable
            ->paginate(Bill::query()->where('is_current', true)->with('waterAccount.owner:id,first_name,last_name'))
            ->through(fn (Bill $bill): array => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'billing_month' => $bill->billing_month,
                'account_number' => $bill->waterAccount->account_number,
                'owner_name' => $bill->waterAccount->owner->name,
                'monthly_charge' => (float) $bill->monthly_charge,
                'total_due' => (float) $bill->total_due,
                'status' => $bill->status->value,
                'due_date' => $bill->due_date->toFormattedDateString(),
                'is_reissue' => $bill->is_reissue,
            ]);

        return Inertia::render('admin/bills/Index', [
            'bills' => $bills,
            'filters' => $datatable->filters(),
        ]);
    }

    /**
     * Show the bill detail: statement figures, slab breakdown, presented
     * entries, and the reissue action for current unpaid bills.
     */
    public function show(Request $request, Bill $bill): Response
    {
        $bill->load(['waterAccount.owner', 'meterReading', 'generator:id,first_name,last_name', 'supersededBill:id,bill_number']);

        $reissuedBy = Bill::query()
            ->where('supersedes_bill_id', $bill->id)
            ->first(['id', 'bill_number']);

        return Inertia::render('admin/bills/Show', [
            'bill' => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'billing_month' => $bill->billing_month,
                'month_label' => Carbon::createFromFormat('Y-m', $bill->billing_month)->format('F Y'),
                'is_current' => $bill->is_current === true,
                'usage_charge' => (float) $bill->usage_charge,
                'service_charge' => (float) $bill->service_charge,
                'monthly_charge' => (float) $bill->monthly_charge,
                'previous_balance' => (float) $bill->previous_balance,
                'total_due' => (float) $bill->total_due,
                'breakdown' => $bill->breakdown,
                'status' => $bill->status->value,
                'due_date' => $bill->due_date->toFormattedDateString(),
                'issued_at' => $bill->approved_at->format('d M Y H:i'),
                'is_reissue' => $bill->is_reissue,
                'supersedes_bill_id' => $bill->supersedes_bill_id,
                'supersedes_bill_number' => $bill->supersededBill?->bill_number,
                'reissued_by_bill_id' => $reissuedBy?->id,
                'reissued_by_bill_number' => $reissuedBy?->bill_number,
                'generated_by' => $bill->generator->name,
                'reading_value' => (float) $bill->meterReading->reading_value,
            ],
            'account' => [
                'id' => $bill->waterAccount->id,
                'owner_name' => $bill->waterAccount->owner->name,
                'account_number' => $bill->waterAccount->account_number,
                'meter_number' => $bill->waterAccount->meter_number,
            ],
            'canReissue' => $request->user()->can('bills.reissue')
                && $bill->is_current === true
                && $bill->status !== BillStatus::Paid
                && $bill->meterReading->id === $bill->waterAccount->latestReading?->id,
        ]);
    }

    /**
     * Reissue the bill from a corrected reading value (D-20).
     */
    public function reissue(Request $request, Bill $bill, BillGenerationService $billGeneration): RedirectResponse
    {
        $validated = $request->validate([
            'reading_value' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ]);

        $newBill = $billGeneration->reissue($bill, (float) $validated['reading_value'], $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':old superseded — reissued as :new.', [
                'old' => $bill->bill_number,
                'new' => $newBill->bill_number,
            ]),
        ]);

        return to_route('admin.bills.show', $newBill);
    }
}
