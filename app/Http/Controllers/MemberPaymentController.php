<?php

namespace App\Http\Controllers;

use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use App\Services\PayHereService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member's own online-payment flow (D-36): reached from the dashboard,
 * scoped to accounts the member owns, and sharing the public pay/checkout
 * pages and the same gateway plumbing.
 */
class MemberPaymentController extends Controller
{
    public function __construct(
        protected AccountLedgerService $accountLedger,
        protected PayHereService $payHere,
    ) {}

    /**
     * Show the payment page for the member's own account.
     */
    public function show(Request $request, WaterAccount $waterAccount): Response
    {
        abort_unless($waterAccount->user_id === $request->user()->id, 403);

        $lastBill = $waterAccount->bills()
            ->where('is_current', true)
            ->orderByDesc('account_ledger_entry_id')
            ->first();

        return Inertia::render('pay/Pay', [
            'account' => [
                'account_number' => $waterAccount->account_number,
                'meter_number' => $waterAccount->meter_number,
                'owner_name' => $waterAccount->owner->name,
                'address' => $waterAccount->connection_address ?? $waterAccount->owner->address,
                'balance' => $this->accountLedger->balanceFor($waterAccount),
            ],
            'lastBill' => $lastBill === null ? null : [
                'bill_number' => $lastBill->bill_number,
                'month_label' => Carbon::createFromFormat('Y-m', $lastBill->billing_month)->format('F Y'),
                'total_due' => (float) $lastBill->total_due,
                'status' => $lastBill->status->value,
                'due_date' => $lastBill->due_date->format('d M Y'),
            ],
            'checkoutUrl' => route('my.pay.checkout', $waterAccount),
            'gatewayReady' => $this->payHere->configured(),
            'backUrl' => route('dashboard'),
        ]);
    }

    /**
     * Create the intent and hand off to PayHere — same checkout page as
     * the public flow.
     */
    public function checkout(Request $request, WaterAccount $waterAccount, PaymentService $payments): Response|RedirectResponse
    {
        abort_unless($waterAccount->user_id === $request->user()->id, 403);

        if (! $this->payHere->configured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Online payments are not available right now.')]);

            return to_route('dashboard');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
        ]);

        $payment = $payments->createGatewayIntent($waterAccount, (float) $validated['amount']);

        return Inertia::render('pay/Checkout', [
            'checkoutUrl' => $this->payHere->checkoutUrl(),
            'fields' => $this->payHere->checkoutFields($payment, $waterAccount),
        ]);
    }
}
