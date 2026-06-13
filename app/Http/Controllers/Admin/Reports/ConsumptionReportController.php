<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\WaterAccountStatus;
use App\Libraries\Datatable;
use App\Libraries\ReportPeriod;
use App\Models\MeterReading;
use App\Models\WaterAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R4 Consumption (docs/management-reports.md): metered consumption across
 * the society for the period, summarized by billing category, with the
 * per-account reading lines.
 */
class ConsumptionReportController extends ReportController
{
    protected function title(): string
    {
        return 'Consumption';
    }

    /**
     * Show the consumption report for the requested period.
     */
    public function index(Request $request): Response
    {
        $period = $this->period($request);

        $datatable = new Datatable(
            $request,
            searchColumns: ['waterAccount.account_number', 'waterAccount.owner.first_name', 'waterAccount.owner.last_name'],
            orderColumns: ['billing_month', 'consumption', 'reading_date'],
            defaultSort: 'billing_month',
            defaultDirection: 'desc',
        );

        $readings = $datatable->paginate($this->lineQuery($period))
            ->through(fn (MeterReading $reading): array => $this->readingLine($reading));

        return Inertia::render('admin/reports/Consumption', [
            'period' => $period->filters(),
            'summary' => $this->summary($period),
            'byCategory' => $this->byCategory($period),
            'readings' => $readings,
            'filters' => $datatable->filters(),
            'exportParams' => $period->queryParameters(),
        ]);
    }

    /**
     * Download the consumption report as an A4 PDF.
     */
    public function pdf(Request $request): HttpResponse
    {
        $period = $this->period($request);

        return $this->pdfResponse('pdf.reports.consumption', $period, [
            'summary' => $this->summary($period),
            'byCategory' => $this->byCategory($period),
            'readings' => $this->lineQuery($period)
                ->orderBy('billing_month')
                ->orderBy('water_account_id')
                ->get()
                ->map(fn (MeterReading $reading): array => $this->readingLine($reading))
                ->all(),
        ]);
    }

    /**
     * Export the period's meter reading lines as CSV.
     */
    public function csv(Request $request): StreamedResponse
    {
        $period = $this->period($request);

        $rows = $this->lineQuery($period)
            ->orderBy('billing_month')
            ->orderBy('water_account_id')
            ->get()
            ->map(fn (MeterReading $reading): array => [
                $reading->waterAccount->account_number,
                $reading->waterAccount->owner->name,
                $reading->waterAccount->billingCategory->name ?? '',
                $reading->billing_month,
                number_format((float) $reading->reading_value, 2, '.', ''),
                number_format((float) $reading->consumption, 2, '.', ''),
                $reading->reading_date->toDateString(),
            ]);

        return $this->csvResponse($period, [
            'Account', 'Owner', 'Category', 'Billing month', 'Reading value', 'Consumption', 'Reading date',
        ], $rows);
    }

    /**
     * The meter readings within the period, eager-loaded for display.
     *
     * @return Builder<MeterReading>
     */
    protected function lineQuery(ReportPeriod $period): Builder
    {
        return MeterReading::query()
            ->with(['waterAccount.owner:id,first_name,last_name', 'waterAccount.billingCategory:id,name'])
            ->whereIn('billing_month', $period->months());
    }

    /**
     * The reading row shape shared by the page, PDF, and CSV.
     *
     * @return array{id: int, account_number: string, owner: string, category: string, billing_month: string, reading_value: float, consumption: float, reading_date: string}
     */
    protected function readingLine(MeterReading $reading): array
    {
        return [
            'id' => $reading->id,
            'account_number' => $reading->waterAccount->account_number,
            'owner' => $reading->waterAccount->owner->name,
            'category' => $reading->waterAccount->billingCategory->name ?? '—',
            'billing_month' => $reading->billing_month,
            'reading_value' => (float) $reading->reading_value,
            'consumption' => (float) $reading->consumption,
            'reading_date' => $reading->reading_date->format('d M Y'),
        ];
    }

    /**
     * The headline tiles: total consumption, average per account, and
     * reading coverage against currently active accounts.
     *
     * @return array{total_consumption: float, accounts_read: int, average_per_account: float, active_accounts: int, coverage_percent: float}
     */
    protected function summary(ReportPeriod $period): array
    {
        $totals = MeterReading::query()
            ->whereIn('billing_month', $period->months())
            ->selectRaw('sum(consumption) as total_consumption, count(distinct water_account_id) as accounts_read')
            ->toBase()
            ->first();

        $totalConsumption = (float) ($totals->total_consumption ?? 0);
        $accountsRead = (int) ($totals->accounts_read ?? 0);
        $activeAccounts = WaterAccount::query()->where('status', WaterAccountStatus::Active)->count();

        return [
            'total_consumption' => round($totalConsumption, 2),
            'accounts_read' => $accountsRead,
            'average_per_account' => $accountsRead > 0 ? round($totalConsumption / $accountsRead, 1) : 0.0,
            'active_accounts' => $activeAccounts,
            'coverage_percent' => $activeAccounts > 0 ? round($accountsRead / $activeAccounts * 100, 1) : 0.0,
        ];
    }

    /**
     * The per-billing-category breakdown: accounts read, total and average
     * consumption.
     *
     * @return list<array{category: string, accounts_read: int, total_consumption: float, average_consumption: float}>
     */
    protected function byCategory(ReportPeriod $period): array
    {
        $rows = MeterReading::query()
            ->join('water_accounts', 'water_accounts.id', '=', 'meter_readings.water_account_id')
            ->join('billing_categories', 'billing_categories.id', '=', 'water_accounts.billing_category_id')
            ->whereIn('meter_readings.billing_month', $period->months())
            ->groupBy('billing_categories.id', 'billing_categories.name')
            ->orderBy('billing_categories.name')
            ->selectRaw(
                'billing_categories.name as category,
                count(distinct water_accounts.id) as accounts_read,
                sum(meter_readings.consumption) as total_consumption',
            )
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'category' => (string) $row->category,
                'accounts_read' => (int) $row->accounts_read,
                'total_consumption' => round((float) $row->total_consumption, 2),
                'average_consumption' => (int) $row->accounts_read > 0
                    ? round((float) $row->total_consumption / (int) $row->accounts_read, 1)
                    : 0.0,
            ])
            ->all();

        return array_values($rows);
    }
}
