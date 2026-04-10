<!DOCTYPE html>
<html lang="si">
<head>
    <meta charset="utf-8">
    <title>{{ $bill->bill_number }}</title>
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
        .border-bor { border-top: 1px solid #000; }
        .kv-value { padding-left: 5mm; }
        a { color: #000; }
    </style>
</head>
<body>
@php
    /** @var array<string, mixed> $breakdown */
    $breakdown = $bill->breakdown;
    $summary = $breakdown['summary'];
    $reading = $breakdown['reading'];

    $formatAmount = fn (float $value): string => number_format(abs($value), 2);
    $formatLkr = fn (float $value): string => ($value < 0 ? 'CR ' : '').'රු. '.$formatAmount($value);
    $formatUnits = fn (float $value): string => fmod($value, 1.0) === 0.0 ? number_format($value) : number_format($value, 2);

    $entryTypeLabels = [
        'water_charge' => 'Water charge',
        'charge' => 'Charge',
        'penalty' => 'Penalty',
        'adjustment' => 'Adjustment',
        'payment' => 'Payment',
        'reversal' => 'Reversal',
    ];

    $debitEntries = collect($breakdown['presented_entries'] ?? [])
        ->filter(fn (array $entry): bool => (float) $entry['amount'] > 0);

    $notices = collect($breakdown['notices'] ?? []);
@endphp

    <div class="center org">{{ $orgName }}</div>
    <div class="center" style="font-size: 12px">මාසික ජල බිල්පත</div>
    <div class="hr"></div>

    <table class="row">
        <tr>
            <td class="sin">බිල් අංකය:</td>
            <td class="value bold">{{ $bill->bill_number }}</td>
        </tr>
        <tr>
            <td class="sin">මාසය:</td>
            <td class="value">{{ $monthLabel }}</td>
        </tr>
        <tr>
            <td class="sin">නිකුත් කල දිනය:</td>
            <td class="value">{{ $bill->approved_at->format('d M Y H:i') }}</td>
        </tr>
        <tr>
            <td class="sin">ගෙවිය යුතු දිනය:</td>
            <td class="value bold">{{ $bill->due_date->format('d M Y') }}</td>
        </tr>
    </table>
    @if ($bill->is_reissue && $bill->supersededBill !== null)
        <div class="center bold mt-1">නැවත නිකුත් කළ බිල්පතකි — {{ $bill->supersededBill->bill_number }}</div>
    @endif
    <div class="hr"></div>

    <table class="row">
        <tr>
            <td class="sin">ගිණුම් අංකය:</td>
            <td class="value bold">{{ $account->account_number }}</td>
        </tr>
        <tr>
            <td class="sin">මීටර් අංකය:</td>
            <td class="value">{{ $account->meter_number }}</td>
        </tr>
        @if (! empty($breakdown['billing_category']))
            <tr>
                <td class="sin">ගාස්තු කාණ්ඩය:</td>
                <td class="value">{{ $breakdown['billing_category'] }}</td>
            </tr>
        @endif
    </table>
    <div class="bold">නම:</div>
    <div class="kv-value">{{ $account->owner->name }}</div>
    @if ($account->connection_address !== null)
        <div class="bold">ලිපිනය:</div>
        <div class="kv-value">{{ $account->connection_address }}</div>
    @endif
    <div class="hr"></div>

    <div class="topic">මීටර් කියවීම</div>
    <table class="row">
        <tr>
            <td class="sin">පෙර කියවීම:</td>
            <td class="value">{{ $formatUnits((float) $reading['previous_value']) }}</td>
        </tr>
        <tr>
            <td class="sin">වත්මන් කියවීම:</td>
            <td class="value">{{ $formatUnits((float) $reading['current_value']) }}</td>
        </tr>
        <tr>
            <td class="sin">කියවූ දිනය:</td>
            <td class="value">{{ $reading['reading_date'] }}</td>
        </tr>
        <tr>
            <td class="sin bold">පරිභෝජනය (ඒකක):</td>
            <td class="value bold">{{ $formatUnits((float) $reading['consumption']) }}</td>
        </tr>
    </table>
    <div class="hr"></div>

    <div class="topic">පෙර බිල් හා ගෙවීම්</div>
    <table class="amounts">
        <tr>
            <td>පෙර ගෙවිය යුතු මුදල</td>
            <td class="right">{{ $formatLkr((float) $summary['previous_due']) }}</td>
        </tr>
        <tr>
            <td>ගෙවීම්</td>
            <td class="right">- {{ $formatLkr((float) $summary['payments']) }}</td>
        </tr>
        @if ((float) $summary['credits'] > 0)
            <tr>
                <td>බැර</td>
                <td class="right">- {{ $formatLkr((float) $summary['credits']) }}</td>
            </tr>
        @endif
        @foreach ($debitEntries as $entry)
            <tr>
                <td>{{ $entry['description'] ?? $entryTypeLabels[$entry['type']] ?? $entry['type'] }} <span class="tiny">({{ $entry['date'] }})</span></td>
                <td class="right">+ {{ $formatLkr((float) $entry['amount']) }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="bold">හිග මුදල</td>
            <td class="right bold border-bor">{{ $formatLkr((float) $summary['previous_balance']) }}</td>
        </tr>
    </table>
    <div class="hr"></div>

    <div class="topic">මෙම මාසයේ</div>
    <table class="amounts">
        <tr>
            <td>ජල ගාස්තුව</td>
            <td class="right">{{ $formatLkr((float) $bill->usage_charge) }}</td>
        </tr>
        @if ((float) $bill->service_charge > 0)
            <tr>
                <td>සේවා ගාස්තුව</td>
                <td class="right">{{ $formatLkr((float) $bill->service_charge) }}</td>
            </tr>
        @endif
        <tr>
            <td class="bold">මේ මස එකතුව</td>
            <td class="right bold">{{ $formatLkr((float) $bill->monthly_charge) }}</td>
        </tr>
    </table>
    <div class="hr"></div>

    <table class="amounts">
        <tr>
            <td class="topic">ගෙවිය යුතු මුළු මුදල</td>
            <td class="right topic">{{ $formatLkr((float) $summary['total_due']) }}</td>
        </tr>
    </table>
    <div class="hr"></div>

    <div class="topic">ගෙවීම් සහතික කිරීම</div>
    <table class="row">
        <tr>
            <td class="sin">මුදල</td>
            <td class="value sin">.....................................</td>
        </tr>
        <tr>
            <td class="sin">දිනය</td>
            <td class="value sin">.....................................</td>
        </tr>
        <tr>
            <td class="sin">භාණ්ඩාගාරික</td>
            <td class="value sin">.....................................</td>
        </tr>
    </table>
    <div class="hr"></div>

    @if ($notices->isNotEmpty())
        <div class="topic">නිවේදන:</div>
        @foreach ($notices as $notice)
            <div class="mt-1">{{ $notice }}</div>
        @endforeach
        <div class="hr"></div>
    @endif

    <div class="center">
        <barcode code="{{ $payUrl }}" type="QR" size="0.9" error="M" disableborder="1" />
    </div>
    <div class="center tiny mt-1">ස්කෑන් කර ඔන්ලයින් ගෙවන්න</div>
    <div class="center tiny mt-1"><a href="{{ $payUrl }}">{{ $payUrl }}</a></div>
    <div class="hr"></div>

    <table class="row tiny">
        <tr>
            <td class="tiny">Served by: {{ $bill->generator->name }}</td>
            <td class="value tiny">{{ $bill->approved_at->format('d M Y H:i') }}</td>
        </tr>
    </table>
    <div class="center tiny mt-1">Thank you!</div>
</body>
</html>
