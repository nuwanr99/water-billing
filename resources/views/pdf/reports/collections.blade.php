@extends('pdf.reports.layout')

@section('content')
    @if ($methodFilter !== null)
        <p class="muted" style="margin-bottom: 10px;">Filtered by method: {{ $methodFilter }}</p>
    @endif

    <table class="tiles">
        <tr>
            <td>
                <div class="label">Total collected</div>
                <div class="value">Rs {{ number_format($summary['total_collected'], 2) }}</div>
            </td>
            <td>
                <div class="label">Payments</div>
                <div class="value">{{ $summary['payment_count'] }}</div>
            </td>
            <td>
                <div class="label">Manual</div>
                <div class="value">Rs {{ number_format($summary['manual_total'], 2) }}</div>
            </td>
            <td>
                <div class="label">PayHere</div>
                <div class="value">Rs {{ number_format($summary['payhere_total'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Total billed</div>
                <div class="value">Rs {{ number_format($summary['total_billed'], 2) }}</div>
            </td>
            <td>
                <div class="label">Collection efficiency</div>
                <div class="value">{{ $summary['collection_efficiency'] === null ? 'N/A' : number_format($summary['collection_efficiency'], 1).'%' }}</div>
            </td>
            <td></td>
            <td></td>
        </tr>
    </table>

    @if ($series !== [])
        <h2>Sub-totals</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="num">Payments</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($series as $row)
                    <tr>
                        <td>{{ $row['bucket_label'] }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Receipts in period</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Receipt</th>
                <th>Account</th>
                <th>Owner</th>
                <th>Method</th>
                <th class="num">Amount</th>
                <th>Paid at</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment['receipt_number'] }}</td>
                    <td>{{ $payment['account_number'] }}</td>
                    <td>{{ $payment['owner'] }}</td>
                    <td>{{ ucfirst($payment['method']) }}</td>
                    <td class="num">{{ number_format($payment['amount'], 2) }}</td>
                    <td>{{ $payment['paid_at'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No payments in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
