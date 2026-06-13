@extends('pdf.reports.layout')

@section('content')
    <p class="muted" style="margin-bottom: 10px;">Balances as at {{ $asOf }} — a point-in-time snapshot, not scoped to the selected period.</p>

    <table class="tiles">
        <tr>
            <td>
                <div class="label">Total arrears</div>
                <div class="value">Rs {{ number_format($summary['total_arrears'], 2) }}</div>
            </td>
            <td>
                <div class="label">Accounts in arrears</div>
                <div class="value">{{ $summary['account_count'] }}</div>
            </td>
            <td>
                <div class="label">Current (&lt;30d)</div>
                <div class="value">Rs {{ number_format($aging['current'], 2) }}</div>
            </td>
            <td>
                <div class="label">90+ days</div>
                <div class="value">Rs {{ number_format($aging['90_plus'], 2) }}</div>
            </td>
        </tr>
    </table>

    <h2>Aging summary</h2>
    <table class="data">
        <thead>
            <tr>
                <th class="num">Current (&lt;30d)</th>
                <th class="num">30-59 days</th>
                <th class="num">60-89 days</th>
                <th class="num">90+ days</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr class="total">
                <td class="num">{{ number_format($aging['current'], 2) }}</td>
                <td class="num">{{ number_format($aging['30_59'], 2) }}</td>
                <td class="num">{{ number_format($aging['60_89'], 2) }}</td>
                <td class="num">{{ number_format($aging['90_plus'], 2) }}</td>
                <td class="num">{{ number_format($aging['current'] + $aging['30_59'] + $aging['60_89'] + $aging['90_plus'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Accounts in arrears</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Account</th>
                <th>Owner</th>
                <th>Connection address</th>
                <th class="num">Balance</th>
                <th class="num">Current</th>
                <th class="num">30-59d</th>
                <th class="num">60-89d</th>
                <th class="num">90+d</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>{{ $account['account_number'] }}</td>
                    <td>{{ $account['owner'] }}</td>
                    <td>{{ $account['connection_address'] ?: '—' }}</td>
                    <td class="num">{{ number_format($account['balance'], 2) }}</td>
                    <td class="num">{{ number_format($account['aging']['current'], 2) }}</td>
                    <td class="num">{{ number_format($account['aging']['30_59'], 2) }}</td>
                    <td class="num">{{ number_format($account['aging']['60_89'], 2) }}</td>
                    <td class="num">{{ number_format($account['aging']['90_plus'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">No accounts in arrears.</td>
                </tr>
            @endforelse
            <tr class="total">
                <td colspan="3">Total</td>
                <td class="num">{{ number_format($summary['total_arrears'], 2) }}</td>
                <td class="num">{{ number_format($aging['current'], 2) }}</td>
                <td class="num">{{ number_format($aging['30_59'], 2) }}</td>
                <td class="num">{{ number_format($aging['60_89'], 2) }}</td>
                <td class="num">{{ number_format($aging['90_plus'], 2) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
