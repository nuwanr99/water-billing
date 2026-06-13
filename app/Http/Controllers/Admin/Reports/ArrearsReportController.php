<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Libraries\Datatable;
use App\Models\WaterAccount;
use App\Models\WaterAccountBalance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R3 Arrears / Outstanding Balances (docs/management-reports.md): a
 * point-in-time snapshot of who owes the society money, with each account's
 * outstanding balance aged against its debit ledger entries. The requested
 * period only labels the header and export filename — the balances
 * themselves are always "as at now".
 */
class ArrearsReportController extends ReportController
{
    /** @var list<string> */
    protected const array BUCKETS = ['current', '30_59', '60_89', '90_plus'];

    protected function title(): string
    {
        return 'Arrears / Outstanding Balances';
    }

    /**
     * Show the current arrears position with an aging breakdown.
     */
    public function index(Request $request): Response
    {
        $period = $this->period($request);
        $balances = $this->arrearsCollection();

        $datatable = new Datatable(
            $request,
            searchColumns: ['waterAccount.account_number', 'waterAccount.owner.first_name', 'waterAccount.owner.last_name'],
            orderColumns: ['balance'],
            defaultSort: 'balance',
            defaultDirection: 'desc',
        );

        $accounts = $datatable->paginate($this->arrearsQuery())
            ->through(fn (WaterAccountBalance $balance): array => $this->accountRow($balance));

        return Inertia::render('admin/reports/Arrears', [
            'period' => $period->filters(),
            'asOf' => now()->format('d M Y'),
            'summary' => $this->summary($balances),
            'aging' => $this->agingTotals($balances),
            'accounts' => $accounts,
            'filters' => $datatable->filters(),
            'exportParams' => $period->queryParameters(),
        ]);
    }

    /**
     * Download the arrears position as an A4 PDF.
     */
    public function pdf(Request $request): HttpResponse
    {
        $period = $this->period($request);
        $balances = $this->orderedArrearsCollection();

        return $this->pdfResponse('pdf.reports.arrears', $period, [
            'asOf' => now()->format('d M Y'),
            'summary' => $this->summary($balances),
            'aging' => $this->agingTotals($balances),
            'accounts' => $balances->map(fn (WaterAccountBalance $balance): array => $this->accountRow($balance))->all(),
        ]);
    }

    /**
     * Export the arrears position (with aging columns) as CSV.
     */
    public function csv(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $balances = $this->orderedArrearsCollection();

        $rows = $balances->map(function (WaterAccountBalance $balance): array {
            $row = $this->accountRow($balance);

            return [
                $row['account_number'],
                $row['owner'],
                $row['connection_address'],
                number_format($row['balance'], 2, '.', ''),
                number_format($row['aging']['current'], 2, '.', ''),
                number_format($row['aging']['30_59'], 2, '.', ''),
                number_format($row['aging']['60_89'], 2, '.', ''),
                number_format($row['aging']['90_plus'], 2, '.', ''),
            ];
        });

        return $this->csvResponse($period, [
            'Account number', 'Owner', 'Connection address', 'Balance',
            'Current (<30d)', '30-59 days', '60-89 days', '90+ days',
        ], $rows);
    }

    /**
     * Water account balances currently in arrears, with the owner and the
     * debit (charge) ledger entries needed to age each balance, eager-loaded
     * in one query so aging never triggers per-row queries.
     *
     * @return Builder<WaterAccountBalance>
     */
    protected function arrearsQuery(): Builder
    {
        return WaterAccountBalance::query()
            ->where('balance', '>', 0)
            ->with([
                'waterAccount.owner:id,first_name,last_name',
                'waterAccount.ledgerEntries' => fn ($query) => $query
                    ->where('amount', '>', 0)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id'),
            ]);
    }

    /**
     * @return Collection<int, WaterAccountBalance>
     */
    protected function arrearsCollection(): Collection
    {
        return $this->arrearsQuery()->get();
    }

    /**
     * The full arrears collection, sorted balance descending — used for the
     * exports, which are not paginated.
     *
     * @return Collection<int, WaterAccountBalance>
     */
    protected function orderedArrearsCollection(): Collection
    {
        return $this->arrearsCollection()
            ->sortByDesc(fn (WaterAccountBalance $balance): float => (float) $balance->balance)
            ->values();
    }

    /**
     * The headline tiles: total arrears and the number of accounts in
     * arrears.
     *
     * @param  iterable<int, WaterAccountBalance>  $balances
     * @return array{total_arrears: float, account_count: int}
     */
    protected function summary(iterable $balances): array
    {
        $balances = collect($balances);

        return [
            'total_arrears' => round((float) $balances->sum(fn (WaterAccountBalance $balance): float => (float) $balance->balance), 2),
            'account_count' => $balances->count(),
        ];
    }

    /**
     * The society-wide aging totals across every account in arrears.
     *
     * @param  iterable<int, WaterAccountBalance>  $balances
     * @return array{current: float, '30_59': float, '60_89': float, '90_plus': float}
     */
    protected function agingTotals(iterable $balances): array
    {
        $totals = array_fill_keys(self::BUCKETS, 0.0);

        foreach ($balances as $balance) {
            foreach ($this->accountAging($balance->waterAccount, (float) $balance->balance) as $bucket => $amount) {
                $totals[$bucket] += $amount;
            }
        }

        foreach ($totals as $bucket => $amount) {
            $totals[$bucket] = round($amount, 2);
        }

        return $totals;
    }

    /**
     * The account row shape shared by the page, PDF, and CSV: the account's
     * details, its balance, and that balance's aging breakdown.
     *
     * @return array{id: int, account_number: string, owner: string, connection_address: string, balance: float, aging: array{current: float, '30_59': float, '60_89': float, '90_plus': float}}
     */
    protected function accountRow(WaterAccountBalance $balance): array
    {
        $account = $balance->waterAccount;

        return [
            'id' => $account->id,
            'account_number' => $account->account_number,
            'owner' => $account->owner->name,
            'connection_address' => $account->connection_address ?? '',
            'balance' => (float) $balance->balance,
            'aging' => $this->accountAging($account, (float) $balance->balance),
        ];
    }

    /**
     * Age an account's outstanding balance against its debit ledger entries.
     * Entries are walked newest to oldest, assigning min(remaining, entry
     * amount) to the bucket for that entry's age until the balance is
     * exhausted. This is equivalent to payments being applied oldest-first:
     * whatever remains unpaid lands on the most recent charges.
     *
     * @return array{current: float, '30_59': float, '60_89': float, '90_plus': float}
     */
    protected function accountAging(WaterAccount $account, float $balance): array
    {
        $buckets = [
            'current' => 0.0,
            '30_59' => 0.0,
            '60_89' => 0.0,
            '90_plus' => 0.0,
        ];
        $remaining = $balance;

        foreach ($account->ledgerEntries as $entry) {
            if ($remaining <= 0) {
                break;
            }

            $allocated = min($remaining, (float) $entry->amount);
            $buckets[$this->ageBucket($entry->created_at)] += $allocated;
            $remaining -= $allocated;
        }

        foreach ($buckets as $bucket => $amount) {
            $buckets[$bucket] = round($amount, 2);
        }

        return $buckets;
    }

    /**
     * The aging bucket a ledger entry falls into, based on its age today.
     *
     * @return 'current'|'30_59'|'60_89'|'90_plus'
     */
    protected function ageBucket(CarbonInterface $createdAt): string
    {
        $days = $createdAt->diffInDays(now());

        return match (true) {
            $days < 30 => 'current',
            $days < 60 => '30_59',
            $days < 90 => '60_89',
            default => '90_plus',
        };
    }
}
