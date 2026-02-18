<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\MeterReading;
use App\Models\WaterAccount;
use App\Services\BillGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The on-site billing flow (D-14): the Water Controller reviews the bill
 * preview after saving a reading, confirms to generate, and prints the
 * 80mm ticket from the bill page.
 */
class BillController extends Controller
{
    public function __construct(protected BillGenerationService $billGeneration) {}

    /**
     * Show the bill exactly as it will be generated — nothing persists
     * until the controller confirms.
     */
    public function preview(MeterReading $meterReading): Response|RedirectResponse
    {
        try {
            $preview = $this->billGeneration->preview($meterReading);
        } catch (ValidationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => collect($exception->errors())->flatten()->first()]);

            return to_route('meter-readings.index');
        }

        return Inertia::render('bills/Preview', [
            'account' => $this->accountSummary($meterReading->waterAccount),
            'reading' => [
                'id' => $meterReading->id,
                'is_editable' => ! $meterReading->isBilled(),
            ],
            'preview' => $preview,
            'monthLabel' => Carbon::createFromFormat('Y-m', $meterReading->billing_month)->format('F Y'),
        ]);
    }

    /**
     * Confirm the reading: generate the bill and land on its printable page.
     */
    public function store(Request $request, MeterReading $meterReading): RedirectResponse
    {
        $bill = $this->billGeneration->generate($meterReading, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':bill generated — total due :amount.', [
                'bill' => $bill->bill_number,
                'amount' => number_format((float) $bill->total_due, 2),
            ]),
        ]);

        return to_route('bills.show', $bill);
    }

    /**
     * The bill document: statement figures plus the 80mm ticket print view.
     */
    public function show(Bill $bill): Response
    {
        $bill->load(['waterAccount.owner', 'generator:id,first_name,last_name', 'supersededBill:id,bill_number']);

        return Inertia::render('bills/Show', [
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
                'due_date' => $bill->due_date->format('d M Y'),
                'issued_at' => $bill->approved_at->format('d M Y H:i'),
                'is_reissue' => $bill->is_reissue,
                'supersedes_bill_number' => $bill->supersededBill?->bill_number,
                'generated_by' => $bill->generator->name,
            ],
            'account' => $this->accountSummary($bill->waterAccount),
            'org' => [
                'name' => config('app.name'),
            ],
        ]);
    }

    /**
     * @return array{id: int, owner_name: string, account_number: string, meter_number: string, connection_address: string|null}
     */
    private function accountSummary(WaterAccount $waterAccount): array
    {
        return [
            'id' => $waterAccount->id,
            'owner_name' => $waterAccount->owner->name,
            'account_number' => $waterAccount->account_number,
            'meter_number' => $waterAccount->meter_number,
            'connection_address' => $waterAccount->connection_address,
        ];
    }
}
