<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\WaterAccountStatus;
use App\Models\Payment;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use App\Services\PayHereService;
use App\Services\PaymentService;
use App\Services\ReceiptPdfService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The public pay flow (D-36): reached from the bill's QR code (plain
 * account + meter query parameters — both figures are printed on the bill,
 * so possession identifies the account) or by manual lookup (account
 * number + email or phone). No login; the pages show only the account
 * number, customer name, address, and balance — never contact details.
 */
class PublicPaymentController extends Controller
{
    public function __construct(
        protected AccountLedgerService $accountLedger,
        protected PayHereService $payHere,
    ) {}

    /**
     * The entry point: with matching ?account=&meter= parameters (the QR
     * path) render the payment page, otherwise the lookup form.
     */
    public function show(Request $request): Response
    {
        $waterAccount = $this->matchFromQuery($request);

        if ($waterAccount === null) {
            return Inertia::render('pay/Lookup', [
                'notFound' => $request->filled('account') || $request->filled('meter'),
            ]);
        }

        return $this->payPage($waterAccount);
    }

    /**
     * Manual validation: account number plus the owner's email or phone.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:50'],
            'contact' => ['required', 'string', 'max:100'],
        ]);

        $waterAccount = $this->accountCandidates($validated['account_number'])
            ->first(fn (WaterAccount $candidate): bool => $this->contactMatches($candidate, $validated['contact']));

        if ($waterAccount === null) {
            throw ValidationException::withMessages([
                'contact' => __('No active account matches that account number and contact.'),
            ]);
        }

        return redirect()->route('pay.show', [
            'account' => $waterAccount->account_number,
            'meter' => $waterAccount->meter_number,
        ]);
    }

    /**
     * Create the pending intent and hand the customer to PayHere via an
     * auto-submitting form (hosted checkout).
     */
    public function checkout(Request $request, PaymentService $payments): Response|RedirectResponse
    {
        $waterAccount = $this->matchFromQuery($request);

        if ($waterAccount === null) {
            return to_route('pay.show');
        }

        if (! $this->payHere->configured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Online payments are not available right now.')]);

            return redirect()->route('pay.show', [
                'account' => $waterAccount->account_number,
                'meter' => $waterAccount->meter_number,
            ]);
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

    /**
     * The return/cancel landing page: shows live status and polls while the
     * webhook confirmation is still in flight.
     */
    public function result(Payment $payment): Response
    {
        $payment->load('waterAccount.owner');

        return Inertia::render('pay/Result', [
            'payment' => [
                'public_token' => $payment->public_token,
                'status' => $payment->status->value,
                'receipt_number' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'paid_at' => $payment->paid_at->format('d M Y'),
                'receipt_pdf_url' => $payment->status === PaymentStatus::Completed
                    ? route('pay.receipt-pdf', $payment->public_token)
                    : null,
            ],
            'account' => [
                'account_number' => $payment->waterAccount->account_number,
                'owner_name' => $payment->waterAccount->owner->name,
                'balance' => $this->accountLedger->balanceFor($payment->waterAccount),
                'pay_again_url' => route('pay.show', [
                    'account' => $payment->waterAccount->account_number,
                    'meter' => $payment->waterAccount->meter_number,
                ]),
            ],
        ]);
    }

    /**
     * Download the completed payment's receipt as a PDF — the same ticket
     * document WhatsApp delivery attaches.
     */
    public function receiptPdf(Payment $payment, ReceiptPdfService $receiptPdf): SymfonyResponse
    {
        abort_unless($payment->status === PaymentStatus::Completed, 404);

        return response($receiptPdf->render($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$payment->receipt_number}.pdf\"",
        ]);
    }

    /**
     * Render the payment page for a matched account: balance, last bill,
     * and the amount form. Only non-sensitive fields are exposed (D-36).
     */
    protected function payPage(WaterAccount $waterAccount): Response
    {
        $lastBill = $waterAccount->bills()
            ->where('is_current', true)
            ->orderByDesc('account_ledger_entry_id')
            ->first();

        $balance = $this->accountLedger->balanceFor($waterAccount);

        return Inertia::render('pay/Pay', [
            'account' => [
                'account_number' => $waterAccount->account_number,
                'meter_number' => $waterAccount->meter_number,
                'owner_name' => $waterAccount->owner->name,
                'address' => $waterAccount->connection_address ?? $waterAccount->owner->address,
                'balance' => $balance,
            ],
            'lastBill' => $lastBill === null ? null : [
                'bill_number' => $lastBill->bill_number,
                'month_label' => Carbon::createFromFormat('Y-m', $lastBill->billing_month)->format('F Y'),
                'total_due' => (float) $lastBill->total_due,
                'status' => $lastBill->status->value,
                'due_date' => $lastBill->due_date->format('d M Y'),
            ],
            'checkoutUrl' => route('pay.checkout', [
                'account' => $waterAccount->account_number,
                'meter' => $waterAccount->meter_number,
            ]),
            'gatewayReady' => $this->payHere->configured(),
        ]);
    }

    /**
     * Match an active account from the QR parameters — both printed
     * figures must agree.
     */
    protected function matchFromQuery(Request $request): ?WaterAccount
    {
        $account = $request->string('account')->trim()->value();
        $meter = $request->string('meter')->trim()->value();

        if ($account === '' || $meter === '') {
            return null;
        }

        return WaterAccount::query()
            ->where('account_number', $account)
            ->where('meter_number', $meter)
            ->where('status', WaterAccountStatus::Active)
            ->with('owner')
            ->first();
    }

    /**
     * Active accounts matching the typed account number. Most people leave
     * out the prefix and the leading zeros ("1" instead of "ACC-001"), so
     * matching relaxes in steps: exact (case-insensitive) → numeric value
     * of the part after the prefix dash → bare alphanumeric suffix. The
     * contact check picks the right one if a relaxed match ever hits more
     * than a single account.
     *
     * @return Collection<int, WaterAccount>
     */
    protected function accountCandidates(string $input): Collection
    {
        $input = strtoupper(trim($input));

        $base = WaterAccount::query()
            ->where('status', WaterAccountStatus::Active)
            ->with('owner');

        $exact = (clone $base)->where('account_number', $input)->get();

        if ($exact->isNotEmpty() || str_contains($input, '-')) {
            return $exact;
        }

        if (ctype_digit($input)) {
            $numericValue = (int) $input;

            return (clone $base)->get()
                ->filter(function (WaterAccount $candidate) use ($numericValue): bool {
                    $suffix = Str::afterLast($candidate->account_number, '-');

                    return ctype_digit($suffix) && (int) $suffix === $numericValue;
                })
                ->values();
        }

        return (clone $base)->where('account_number', 'like', "%-{$input}")->get();
    }

    /**
     * The manual-lookup contact matches when it equals the owner's email
     * (case-insensitive) or phone/WhatsApp number (compared by digits, so
     * 0771234567 and +94 77 123 4567 both match).
     */
    protected function contactMatches(WaterAccount $waterAccount, string $contact): bool
    {
        $owner = $waterAccount->owner;

        if ($owner->email !== null && strcasecmp(trim($contact), $owner->email) === 0) {
            return true;
        }

        $given = $this->normalizePhone($contact);

        if ($given === '') {
            return false;
        }

        foreach ([$owner->phone, $owner->wa_number] as $phone) {
            if ($phone !== null && $this->normalizePhone($phone) === $given) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduce a phone number to its last nine digits — the local subscriber
     * part, stable across 07x / +947x / 947x formats.
     */
    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return substr($digits, -9);
    }
}
