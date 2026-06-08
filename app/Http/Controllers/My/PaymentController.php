<?php

namespace App\Http\Controllers\My;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ReceiptPdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The member-facing payment history: completed payments across the
 * member's own water accounts, each with a downloadable receipt.
 */
class PaymentController extends Controller
{
    /**
     * List the member's own payments, most recent first.
     */
    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->whereIn('water_account_id', $request->user()->waterAccounts()->select('id'))
            ->where('status', PaymentStatus::Completed)
            ->with('waterAccount:id,account_number')
            ->orderByDesc('paid_at')
            ->paginate(12)
            ->through(fn (Payment $payment): array => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'account_number' => $payment->waterAccount->account_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->method->value,
                'paid_at' => $payment->paid_at->format('d M Y'),
                'receipt_url' => route('my.payments.receipt', $payment),
            ]);

        return Inertia::render('my/payments/Index', [
            'payments' => $payments,
        ]);
    }

    /**
     * Download the receipt for the member's own completed payment.
     */
    public function receipt(Request $request, Payment $payment, ReceiptPdfService $receiptPdf): SymfonyResponse
    {
        abort_unless($payment->waterAccount->user_id === $request->user()->id, 403);
        abort_unless($payment->status === PaymentStatus::Completed, 404);

        return response($receiptPdf->render($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$payment->receipt_number}.pdf\"",
        ]);
    }
}
