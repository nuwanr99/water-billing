<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\SystemLedgerAccountType;
use App\Libraries\ReportPeriod;
use App\Models\SystemLedgerAccount;
use App\Models\SystemLedgerLine;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R6 Financial Position (docs/management-reports.md): the society's income
 * and expense position for the period, drawn straight from the double-entry
 * system ledger, plus a trial-balance style chart-of-accounts statement that
 * must always balance (total debits = total credits).
 */
class FinancialPositionReportController extends ReportController
{
    protected function title(): string
    {
        return 'Financial Position';
    }

    /**
     * Show the financial position for the requested period.
     */
    public function index(Request $request): Response
    {
        $period = $this->period($request);
        $trialBalance = $this->trialBalance($period);

        return Inertia::render('admin/reports/FinancialPosition', [
            'period' => $period->filters(),
            'summary' => $this->summary($trialBalance),
            'trialBalance' => $trialBalance,
            'exportParams' => $period->queryParameters(),
        ]);
    }

    /**
     * Download the financial position as an A4 PDF.
     */
    public function pdf(Request $request): HttpResponse
    {
        $period = $this->period($request);
        $trialBalance = $this->trialBalance($period);

        return $this->pdfResponse('pdf.reports.financial-position', $period, [
            'summary' => $this->summary($trialBalance),
            'trialBalance' => $trialBalance,
        ]);
    }

    /**
     * Export the trial balance rows as CSV.
     */
    public function csv(Request $request): StreamedResponse
    {
        $period = $this->period($request);
        $trialBalance = $this->trialBalance($period);

        $rows = collect($trialBalance)->map(fn (array $row): array => [
            $row['code'],
            $row['name'],
            $row['type'],
            number_format($row['debit'], 2, '.', ''),
            number_format($row['credit'], 2, '.', ''),
            number_format($row['balance'], 2, '.', ''),
        ]);

        return $this->csvResponse($period, ['Code', 'Name', 'Type', 'Debit', 'Credit', 'Balance'], $rows);
    }

    /**
     * The period's debit/credit totals per chart account, keyed by account
     * id. Only entries that are posted and balanced are considered.
     *
     * @return Collection<int, array{account_id: int, debit: float, credit: float}>
     */
    protected function periodLines(ReportPeriod $period): Collection
    {
        return SystemLedgerLine::query()
            ->join('system_ledger_entries', 'system_ledger_entries.id', '=', 'system_ledger_lines.system_ledger_entry_id')
            ->where('system_ledger_entries.is_posted', true)
            ->where('system_ledger_entries.is_balanced', true)
            ->whereBetween('system_ledger_entries.entry_date', [$period->start, $period->end])
            ->groupBy('system_ledger_lines.system_ledger_account_id')
            ->selectRaw(
                'system_ledger_lines.system_ledger_account_id as account_id,
                sum(case when system_ledger_lines.amount > 0 then system_ledger_lines.amount else 0 end) as debit,
                sum(case when system_ledger_lines.amount < 0 then -system_ledger_lines.amount else 0 end) as credit'
            )
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'account_id' => (int) $row->account_id,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ])
            ->keyBy(fn (array $row): int => $row['account_id']);
    }

    /**
     * The trial-balance style chart-of-accounts list: every account grouped
     * by type (asset, liability, equity, income, expense), with its period
     * debit total, credit total, and natural balance.
     *
     * @return list<array{code: string, name: string, type: string, debit: float, credit: float, balance: float}>
     */
    protected function trialBalance(ReportPeriod $period): array
    {
        $lines = $this->periodLines($period);

        $accounts = SystemLedgerAccount::query()->orderBy('code')->get();

        $rows = collect(SystemLedgerAccountType::cases())
            ->flatMap(fn (SystemLedgerAccountType $type): Collection => $accounts->where('type', $type))
            ->map(function (SystemLedgerAccount $account) use ($lines): array {
                $line = $lines->get($account->id);
                $debit = $line !== null ? round($line['debit'], 2) : 0.0;
                $credit = $line !== null ? round($line['credit'], 2) : 0.0;

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $account->naturalBalance($debit - $credit),
                ];
            })
            ->all();

        return array_values($rows);
    }

    /**
     * The headline tiles: total income (credits on income accounts), total
     * expense (debits on expense accounts), the net, and the total
     * debits/credits across every account, which must be equal.
     *
     * @param  list<array{code: string, name: string, type: string, debit: float, credit: float, balance: float}>  $trialBalance
     * @return array{total_income: float, total_expense: float, net: float, total_debits: float, total_credits: float, is_balanced: bool}
     */
    protected function summary(array $trialBalance): array
    {
        $totalDebits = round(array_sum(array_column($trialBalance, 'debit')), 2);
        $totalCredits = round(array_sum(array_column($trialBalance, 'credit')), 2);

        $totalIncome = round(array_sum(array_map(
            fn (array $row): float => $row['type'] === SystemLedgerAccountType::Income->value ? $row['credit'] : 0.0,
            $trialBalance,
        )), 2);

        $totalExpense = round(array_sum(array_map(
            fn (array $row): float => $row['type'] === SystemLedgerAccountType::Expense->value ? $row['debit'] : 0.0,
            $trialBalance,
        )), 2);

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => round($totalIncome - $totalExpense, 2),
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.005,
        ];
    }
}
