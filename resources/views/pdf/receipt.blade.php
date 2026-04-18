<!DOCTYPE html>
<html lang="si">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_number }}</title>
    <style>
        body { font-family: notoserifsinhala; font-size: 11px; line-height: 1.1; color: #000; margin: 0; padding: 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .tiny { font-size: 9px; }
        .sin { font-size: 11px; }
        .topic { font-size: 12px; font-weight: bold; }
        .org { font-size: 18px; font-weight: bold; }
        .hr { border-top: 1px dashed #000; margin: 2.5mm 0; }
        .mt-1 { margin-top: 1mm; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        table.row td.value { text-align: right; }
        .amounts td:first-child { width: 60%; }
        .kv-value { padding-left: 5mm; }
    </style>
</head>
<body>
@php
    $formatLkr = fn (float $value): string => ($value < 0 ? 'CR ' : '').'රු. '.number_format(abs($value), 2);
@endphp

    <div class="center org">{{ $orgName }}</div>
    <div class="center" style="font-size: 12px">මුදල් ලදුපත</div>
    <div class="hr"></div>

    <table class="row">
        <tr>
            <td class="sin">ලදුපත් අංකය:</td>
            <td class="value bold">{{ $payment->receipt_number }}</td>
        </tr>
        <tr>
            <td class="sin">දිනය:</td>
            <td class="value">{{ $payment->paid_at->format('d M Y') }}</td>
        </tr>
        @if ($payment->method === App\Enums\PaymentMethod::Payhere)
            <tr>
                <td class="sin">ක්‍රමය:</td>
                <td class="value">Online (PayHere)</td>
            </tr>
        @endif
    </table>
    <div class="hr"></div>

    <table class="row">
        <tr>
            <td class="sin">ගිණුම් අංකය:</td>
            <td class="value bold">{{ $account->account_number }}</td>
        </tr>
    </table>
    <div class="bold">නම:</div>
    <div class="kv-value">{{ $account->owner->name }}</div>
    <div class="hr"></div>

    <table class="amounts">
        <tr>
            <td class="topic">ගෙවූ මුදල</td>
            <td class="right topic">{{ $formatLkr((float) $payment->amount) }}</td>
        </tr>
        <tr>
            <td>ඉතිරි හිග මුදල</td>
            <td class="right">{{ $formatLkr($balanceAfter) }}</td>
        </tr>
    </table>
    @if ($payment->reference !== null)
        <table class="row mt-1">
            <tr>
                <td class="sin">විස්තර:</td>
                <td class="value">{{ $payment->reference }}</td>
            </tr>
        </table>
    @endif
    <div class="hr"></div>

    <table class="row tiny">
        <tr>
            <td class="tiny">
                @if ($payment->recorder !== null)
                    Received by: {{ $payment->recorder->name }}
                @elseif ($payment->payhere_reference !== null)
                    PayHere ref: {{ $payment->payhere_reference }}
                @endif
            </td>
            <td class="value tiny">{{ $payment->paid_at->format('d M Y H:i') }}</td>
        </tr>
    </table>
    <div class="center tiny mt-1">Thank you! / ස්තූතියි!</div>
</body>
</html>
