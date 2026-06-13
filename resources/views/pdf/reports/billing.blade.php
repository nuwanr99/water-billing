@extends('pdf.reports.layout')

@section('content')
    @if ($statementAccount !== null)
        <h2>Billing statement — {{ $statementAccount['account_number'] }}</h2>
        <table class="data">
            <tr>
                <th>Owner</th>
                <td>{{ $statementAccount['owner'] }}</td>
                <th>Category</th>
                <td>{{ $statementAccount['category'] }}</td>
            </tr>
            <tr>
                <th>Connection address</th>
                <td colspan="3">{{ $statementAccount['connection_address'] }}</td>
            </tr>
        </table>
    @elseif ($categoryFilter !== null || $statusFilter !== null)
        <p class="muted" style="margin-bottom: 10px;">
            Filtered by{{ $categoryFilter !== null ? " category: {$categoryFilter}" : '' }}{{ $statusFilter !== null ? " status: {$statusFilter}" : '' }}
        </p>
    @endif

    <table class="tiles">
        <tr>
            <td>
                <div class="label">Total billed</div>
                <div class="value">Rs {{ number_format($summary['total_billed'], 2) }}</div>
            </td>
            <td>
                <div class="label">Bills</div>
                <div class="value">{{ $summary['bill_count'] }}</div>
            </td>
            <td>
                <div class="label">Paid</div>
                <div class="value">{{ $summary['status_counts']['paid'] }}</div>
            </td>
            <td>
                <div class="label">Overdue</div>
                <div class="value">{{ $summary['status_counts']['overdue'] }}</div>
            </td>
        </tr>
    </table>

    @if ($byCategory !== [])
        <h2>By billing category</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="num">Bills</th>
                    <th class="num">Total billed</th>
                    <th class="num">Total paid</th>
                    <th class="num">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byCategory as $row)
                    <tr>
                        <td>{{ $row['category'] }}</td>
                        <td class="num">{{ $row['bill_count'] }}</td>
                        <td class="num">{{ number_format($row['total_billed'], 2) }}</td>
                        <td class="num">{{ number_format($row['total_paid'], 2) }}</td>
                        <td class="num">{{ number_format($row['total_outstanding'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    <td class="num">{{ array_sum(array_column($byCategory, 'bill_count')) }}</td>
                    <td class="num">{{ number_format(array_sum(array_column($byCategory, 'total_billed')), 2) }}</td>
                    <td class="num">{{ number_format(array_sum(array_column($byCategory, 'total_paid')), 2) }}</td>
                    <td class="num">{{ number_format(array_sum(array_column($byCategory, 'total_outstanding')), 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>{{ $statementAccount !== null ? 'Bills' : 'Bills in period' }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Bill</th>
                <th>Month</th>
                <th>Account</th>
                <th>Owner</th>
                <th class="num">Usage</th>
                <th class="num">Service</th>
                <th class="num">Monthly</th>
                <th class="num">Prev. balance</th>
                <th class="num">Total due</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bills as $bill)
                <tr>
                    <td>{{ $bill['bill_number'] }}</td>
                    <td>{{ $bill['billing_month'] }}</td>
                    <td>{{ $bill['account_number'] }}</td>
                    <td>{{ $bill['owner'] }}</td>
                    <td class="num">{{ number_format($bill['usage_charge'], 2) }}</td>
                    <td class="num">{{ number_format($bill['service_charge'], 2) }}</td>
                    <td class="num">{{ number_format($bill['monthly_charge'], 2) }}</td>
                    <td class="num">{{ number_format($bill['previous_balance'], 2) }}</td>
                    <td class="num">{{ number_format($bill['total_due'], 2) }}</td>
                    <td>{{ ucfirst($bill['status']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="muted">No bills in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
