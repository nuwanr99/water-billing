@extends('pdf.reports.layout')

@section('content')
    <table class="tiles">
        <tr>
            <td>
                <div class="label">Active items</div>
                <div class="value">{{ $summary['active_items'] }}</div>
            </td>
            <td>
                <div class="label">Low stock</div>
                <div class="value">{{ $summary['low_stock_count'] }}</div>
            </td>
            <td>
                <div class="label">Total stock value</div>
                <div class="value">Rs {{ number_format($summary['total_stock_value'], 2) }}</div>
            </td>
        </tr>
    </table>

    <p class="muted" style="margin-bottom: 6px;">Current stock position is point-in-time and does not reflect the selected period.</p>

    <h2>Current stock position</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Item</th>
                <th>Unit</th>
                <th class="num">Unit rate</th>
                <th class="num">In stock</th>
                <th class="num">Reorder level</th>
                <th>Status</th>
                <th class="num">Line value</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($position as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['unit'] }}</td>
                    <td class="num">{{ number_format($item['unit_rate'], 2) }}</td>
                    <td class="num">{{ $item['quantity_in_stock'] }}</td>
                    <td class="num">{{ $item['reorder_level'] }}</td>
                    <td>{{ $item['low_stock'] ? 'Low stock' : 'OK' }}</td>
                    <td class="num">{{ number_format($item['line_value'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No inventory items.</td>
                </tr>
            @endforelse
            <tr class="total">
                <td colspan="6">Total stock value</td>
                <td class="num">{{ number_format(array_sum(array_column($position, 'line_value')), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Movements in period</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Type</th>
                <th class="num">Quantity</th>
                <th class="num">Unit rate</th>
                <th>Job</th>
                <th>Recorded by</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>{{ $movement['moved_at'] }}</td>
                    <td>{{ $movement['item'] }}</td>
                    <td>{{ ucfirst($movement['type']) }}</td>
                    <td class="num">{{ $movement['quantity'] }}</td>
                    <td class="num">{{ $movement['unit_rate'] === null ? '—' : number_format($movement['unit_rate'], 2) }}</td>
                    <td>{{ $movement['job_number'] ?? '—' }}</td>
                    <td>{{ $movement['recorded_by'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">No movements in this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
