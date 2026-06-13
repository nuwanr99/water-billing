<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Libraries\Datatable;
use App\Libraries\ReportPeriod;
use App\Models\Bill;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R2 Collection / Payment Status (docs/management-reports.md): what was
 * collected in the period, split by method, with a collection-efficiency
 * figure against the period's billed total and a sub-total series.
 */
class CollectionReportController extends ReportController
{
    protected function title(): string
    {
        return 'Collection / Payment Status';
    }

    public function index(Request $request): Response
    {
        $period = $this->period($request);
        $method = PaymentMethod::tryFrom($request->string('method')->value());

        $datatable = new Datatable(
            $request,
            searchColumns: ['receipt_number', 'waterAccount.account_number'],
            orderColumns: ['receipt_number', 'amount', 'paid_at'],
            defaultSort: 'paid_at',
            defaultDirection: 'desc',
        );

        $payments = $datatable->paginate($this->paymentQuery($period, $method))
            ->through(fn (Payment $payment): array => $this->paymentLine($payment));

        return Inertia::render('admin/reports/Collections', [
            'period' => $period->filters(),
            'reportFilters' => [
                'method' => $method?->value,
            ],
            'methods' => array_column(PaymentMethod::cases(), 'value'),
            'summary' => $this->summary($period, $method),
            'series' => $this->series($period, $method),
            'payments' => $payments,
            'filters' => $datatable->filters(),
            'exportParams' => $this->exportParams($period, $method),
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $period = $this->period($request);
        $method = PaymentMethod::tryFrom($request->string('method')->value());

        return $this->pdfResponse('pdf.reports.collections', $period, [
            'summary' => $this->summary($period, $method),
            'series' => $this->series($period, $method),
            'payments' => $this->paymentQuery($period, $method)
                ->orderBy('paid_at')
                ->get()
                ->map(fn (Payment $payment): array => $this->paymentLine($payment))
                ->all(),
            'methodFilter' => $method?->value,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $method = PaymentMethod::tryFrom($request->string('method')->value());

        $rows = $this->paymentQuery($period, $method)
            ->orderBy('paid_at')
            ->get()
            ->map(fn (Payment $payment): array => [
                $payment->receipt_number,
                $payment->waterAccount->account_number,
                $payment->waterAccount->owner->name,
                $payment->method->value,
                number_format((float) $payment->amount, 2, '.', ''),
                $payment->paid_at->toIso8601String(),
            ]);

        return $this->csvResponse($period, [
            'Receipt number', 'Account', 'Owner', 'Method', 'Amount', 'Paid at',
        ], $rows);
    }

    /**
     * The completed payments within the period, narrowed by the optional
     * method filter.
     *
     * @return Builder<Payment>
     */
    protected function paymentQuery(ReportPeriod $period, ?PaymentMethod $method): Builder
    {
        return Payment::query()
            ->with(['waterAccount.owner:id,first_name,last_name'])
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->when($method !== null, fn (Builder $query) => $query->where('method', $method));
    }

    /**
     * The receipt row shape shared by the page and the PDF.
     *
     * @return array{id: int, receipt_number: string, account_number: string, owner: string, method: string, amount: float, paid_at: string}
     */
    protected function paymentLine(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'receipt_number' => (string) $payment->receipt_number,
            'account_number' => $payment->waterAccount->account_number,
            'owner' => $payment->waterAccount->owner->name,
            'method' => $payment->method->value,
            'amount' => (float) $payment->amount,
            'paid_at' => $payment->paid_at->format('d M Y'),
        ];
    }

    /**
     * The headline tiles: total collected, count, split by method, and
     * collection efficiency against the period's billed total.
     *
     * @return array{total_collected: float, payment_count: int, manual_total: float, payhere_total: float, total_billed: float, collection_efficiency: float|null}
     */
    protected function summary(ReportPeriod $period, ?PaymentMethod $method): array
    {
        $base = fn (): Builder => Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->when($method !== null, fn (Builder $query) => $query->where('method', $method));

        $byMethod = $base()
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $totalCollected = (float) $byMethod->sum();

        $totalBilled = (float) Bill::query()
            ->where('is_current', true)
            ->whereIn('billing_month', $period->months())
            ->sum('total_due');

        return [
            'total_collected' => $totalCollected,
            'payment_count' => (int) $base()->count(),
            'manual_total' => (float) $byMethod->get(PaymentMethod::Manual->value, 0),
            'payhere_total' => (float) $byMethod->get(PaymentMethod::Payhere->value, 0),
            'total_billed' => $totalBilled,
            'collection_efficiency' => $totalBilled > 0
                ? round($totalCollected / $totalBilled * 100, 1)
                : null,
        ];
    }

    /**
     * The sub-total series: by day for a month period, by month otherwise.
     *
     * @return list<array{bucket_label: string, count: int, total: float}>
     */
    protected function series(ReportPeriod $period, ?PaymentMethod $method): array
    {
        $payments = $this->paymentQuery($period, $method)->get(['id', 'amount', 'paid_at']);

        $byDay = $period->type === 'month';
        $key = fn (Payment $payment): string => $payment->paid_at->format($byDay ? 'Y-m-d' : 'Y-m');
        $label = fn (string $bucket): string => $byDay
            ? CarbonImmutable::createFromFormat('Y-m-d', $bucket)->format('d M')
            : CarbonImmutable::createFromFormat('Y-m', $bucket)->format('M Y');

        $series = $payments
            ->groupBy($key)
            ->sortKeys()
            ->map(fn (Collection $group, string $bucket): array => [
                'bucket_label' => $label($bucket),
                'count' => $group->count(),
                'total' => round((float) $group->sum('amount'), 2),
            ])
            ->all();

        return array_values($series);
    }

    /**
     * The query string the export links reproduce this view with.
     *
     * @return array<string, string|int>
     */
    protected function exportParams(ReportPeriod $period, ?PaymentMethod $method): array
    {
        return array_filter([
            ...$period->queryParameters(),
            'method' => $method?->value,
        ], fn (string|int|null $value): bool => $value !== null && $value !== '');
    }
}
