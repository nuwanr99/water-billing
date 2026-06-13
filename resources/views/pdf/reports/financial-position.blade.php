@extends('pdf.reports.layout')

@section('content')
    <table class="tiles">
        <tr>
            <td>
                <div class="label">Total income</div>
                <div class="value">Rs {{ number_format($summary['total_income'], 2) }}</div>
            </td>
            <td>
                <div class="label">Total expense</div>
                <div class="value">Rs {{ number_format($summary['total_expense'], 2) }}</div>
            </td>
            <td>
                <div class="label">Net</div>
                <div class="value">Rs {{ number_format($summary['net'], 2) }}</div>
            </td>
            <td>
                <div class="label">Total debits / credits</div>
                <div class="value">Rs {{ number_format($summary['total_debits'], 2) }}</div>
            </td>
        </tr>
    </table>

    <p class="muted" style="margin-bottom: 10px;">
        Statement is {{ $summary['is_balanced'] ? 'balanced' : 'OUT OF BALANCE' }}
        (debits Rs {{ number_format($summary['total_debits'], 2) }}, credits Rs {{ number_format($summary['total_credits'], 2) }}).
    </p>

    <h2>Chart of accounts (trial balance)</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Code</th>
                <th>Account</th>
                <th>Type</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($trialBalance as $row)
                <tr>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ ucfirst($row['type']) }}</td>
                    <td class="num">{{ number_format($row['debit'], 2) }}</td>
                    <td class="num">{{ number_format($row['credit'], 2) }}</td>
                    <td class="num">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No accounts found.</td>
                </tr>
            @endforelse
            <tr class="total">
                <td colspan="3">Total</td>
                <td class="num">{{ number_format($summary['total_debits'], 2) }}</td>
                <td class="num">{{ number_format($summary['total_credits'], 2) }}</td>
                <td class="num"></td>
            </tr>
        </tbody>
    </table>
@endsection
