@extends('pdf.reports.layout')

@section('content')
    @if ($categoryFilter !== null || $jobFilter !== null)
        <p class="muted" style="margin-bottom: 10px;">
            Filtered by{{ $categoryFilter !== null ? " category: {$categoryFilter}" : '' }}{{ $jobFilter !== null ? " job: {$jobFilter}" : '' }}
        </p>
    @endif

    <table class="tiles">
        <tr>
            <td>
                <div class="label">Total expenditure</div>
                <div class="value">Rs {{ number_format($summary['total_expenditure'], 2) }}</div>
            </td>
            <td>
                <div class="label">Expenses</div>
                <div class="value">{{ $summary['expense_count'] }}</div>
            </td>
            <td>
                <div class="label">Categories used</div>
                <div class="value">{{ $summary['category_count'] }}</div>
            </td>
        </tr>
    </table>

    <h2>By expense category</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Code</th>
                <th>Category</th>
                <th class="num">Count</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($byCategory as $row)
                <tr>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ $row['expense_count'] }}</td>
                    <td class="num">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="num">{{ array_sum(array_column($byCategory, 'expense_count')) }}</td>
                <td class="num">{{ number_format(array_sum(array_column($byCategory, 'total')), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Expenses in period</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Expense</th>
                <th>Date</th>
                <th>Category</th>
                <th>Paid from</th>
                <th class="num">Amount</th>
                <th>Description</th>
                <th>Job</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ $expense['expense_number'] }}</td>
                    <td>{{ $expense['expense_date'] }}</td>
                    <td>{{ $expense['category'] }}</td>
                    <td>{{ $expense['paid_from'] }}</td>
                    <td class="num">{{ number_format($expense['amount'], 2) }}</td>
                    <td>{{ $expense['description'] }}</td>
                    <td>{{ $expense['job_number'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No expenses in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
