<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        .report-header { border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; margin-bottom: 16px; }
        .report-header .org { font-size: 16px; font-weight: bold; }
        .report-header .title { font-size: 13px; margin-top: 2px; }
        .report-header .meta { margin-top: 4px; color: #555; font-size: 10px; }
        h2 { font-size: 12px; margin: 18px 0 6px; }
        table.tiles { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
        table.tiles td { border: 1px solid #ccc; border-radius: 4px; padding: 8px 10px; width: 25%; vertical-align: top; }
        table.tiles .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; }
        table.tiles .value { font-size: 14px; font-weight: bold; margin-top: 3px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 5px 7px; text-align: left; }
        table.data th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px; }
        table.data td.num, table.data th.num { text-align: right; }
        table.data tr.total td { font-weight: bold; background: #fafafa; }
        .muted { color: #777; }
        .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 9px; color: #777; }
    </style>
</head>
<body>
    <div class="report-header">
        <div class="org">{{ $orgName }}</div>
        <div class="title">{{ $title }} Report</div>
        <div class="meta">
            Period: {{ $period->label() }} &nbsp;•&nbsp; Generated {{ $generatedAt->format('d M Y H:i') }}
        </div>
    </div>

    @yield('content')

    <div class="footer">
        {{ $orgName }} — {{ $title }} Report — {{ $period->label() }}
    </div>
</body>
</html>
