<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c1917; padding: 28px 32px; }
        .header { text-align: center; border-bottom: 2px solid #1c1917; padding-bottom: 12px; margin-bottom: 16px; }
        .org { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: #57534e; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .meta .label { color: #57534e; width: 40%; }
        .meta .value { font-weight: bold; text-align: right; }
        .amount-box { margin: 18px 0; padding: 14px 16px; border: 1px solid #d6d3d1; }
        .amount-box .row td { padding: 5px 0; }
        .amount-label { color: #57534e; }
        .amount-value { text-align: right; font-weight: bold; }
        .total td { border-top: 1px solid #d6d3d1; padding-top: 8px !important; font-size: 15px; }
        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px dashed #a8a29e; color: #78716c; font-size: 10px; text-align: center; }
        .badge { display: inline-block; padding: 2px 8px; border: 1px solid #16a34a; color: #16a34a; border-radius: 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="org">{{ $orgName }}</div>
        <div class="doc-title">Payment Receipt</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Receipt number</td>
            <td class="value">{{ $payment->receipt_number }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td class="value">{{ $payment->paid_at->format('d M Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Account number</td>
            <td class="value">{{ $waterAccount->account_number }}</td>
        </tr>
        <tr>
            <td class="label">Customer</td>
            <td class="value">{{ $waterAccount->owner->name }}</td>
        </tr>
        <tr>
            <td class="label">Payment method</td>
            <td class="value">{{ $payment->method->value === 'payhere' ? 'Online (PayHere)' : 'Manual' }}</td>
        </tr>
        @if ($payment->payhere_reference)
            <tr>
                <td class="label">Gateway reference</td>
                <td class="value">{{ $payment->payhere_reference }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Status</td>
            <td class="value"><span class="badge">Paid</span></td>
        </tr>
    </table>

    <div class="amount-box">
        <table>
            <tr class="row total">
                <td class="amount-label">Amount paid</td>
                <td class="amount-value">Rs {{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
            <tr class="row">
                <td class="amount-label">Account balance after payment</td>
                <td class="amount-value">
                    @if ((float) ($payment->ledgerEntry->running_balance ?? 0) < 0)
                        Rs {{ number_format(abs((float) $payment->ledgerEntry->running_balance), 2) }} CR
                    @else
                        Rs {{ number_format((float) ($payment->ledgerEntry->running_balance ?? 0), 2) }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        This receipt was generated electronically by {{ $orgName }} and is valid without a signature.
    </div>
</body>
</html>
