<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member-facing billing history: every current bill issued against
 * the member's own water accounts, newest first.
 */
class BillController extends Controller
{
    /**
     * List the member's own bills across all of their water accounts.
     */
    public function index(Request $request): Response
    {
        $bills = Bill::query()
            ->whereIn('water_account_id', $request->user()->waterAccounts()->select('id'))
            ->where('is_current', true)
            ->with('waterAccount:id,account_number')
            ->orderByDesc('billing_month')
            ->orderByDesc('account_ledger_entry_id')
            ->paginate(12)
            ->through(fn (Bill $bill): array => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'month_label' => Carbon::createFromFormat('Y-m', $bill->billing_month)->format('F Y'),
                'account_number' => $bill->waterAccount->account_number,
                'total_due' => (float) $bill->total_due,
                'status' => $bill->status->value,
                'due_date' => $bill->due_date->format('d M Y'),
                'is_reissue' => $bill->is_reissue,
            ]);

        return Inertia::render('my/bills/Index', [
            'bills' => $bills,
        ]);
    }
}
