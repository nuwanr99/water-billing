@extends('pdf.reports.layout')

@section('content')
    <table class="tiles">
        <tr>
            <td>
                <div class="label">Total consumption</div>
                <div class="value">{{ number_format($summary['total_consumption'], 2) }} units</div>
            </td>
            <td>
                <div class="label">Average per account</div>
                <div class="value">{{ number_format($summary['average_per_account'], 1) }} units</div>
            </td>
            <td>
                <div class="label">Accounts read</div>
                <div class="value">{{ $summary['accounts_read'] }} / {{ $summary['active_accounts'] }}</div>
            </td>
            <td>
                <div class="label">Reading coverage</div>
                <div class="value">{{ number_format($summary['coverage_percent'], 1) }}%</div>
            </td>
        </tr>
    </table>
    <p class="muted" style="margin-top: -10px; margin-bottom: 14px;">
        Reading coverage = accounts read at least once in the period ÷ currently active accounts.
    </p>

    <h2>By billing category</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Accounts read</th>
                <th class="num">Total consumption</th>
                <th class="num">Average per account</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byCategory as $row)
                <tr>
                    <td>{{ $row['category'] }}</td>
                    <td class="num">{{ $row['accounts_read'] }}</td>
                    <td class="num">{{ number_format($row['total_consumption'], 2) }}</td>
                    <td class="num">{{ number_format($row['average_consumption'], 1) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">No meter readings in this period.</td>
                </tr>
            @endforelse
            @if ($byCategory !== [])
                <tr class="total">
                    <td>Total</td>
                    <td class="num">{{ array_sum(array_column($byCategory, 'accounts_read')) }}</td>
                    <td class="num">{{ number_format(array_sum(array_column($byCategory, 'total_consumption')), 2) }}</td>
                    <td class="num"></td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2>Meter readings in period</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Account</th>
                <th>Owner</th>
                <th>Category</th>
                <th>Month</th>
                <th class="num">Reading</th>
                <th class="num">Consumption</th>
                <th>Reading date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($readings as $reading)
                <tr>
                    <td>{{ $reading['account_number'] }}</td>
                    <td>{{ $reading['owner'] }}</td>
                    <td>{{ $reading['category'] }}</td>
                    <td>{{ $reading['billing_month'] }}</td>
                    <td class="num">{{ number_format($reading['reading_value'], 2) }}</td>
                    <td class="num">{{ number_format($reading['consumption'], 2) }}</td>
                    <td>{{ $reading['reading_date'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No meter readings in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
