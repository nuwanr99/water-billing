<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Libraries\Datatable;
use App\Libraries\ReportPeriod;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R7 Inventory (docs/management-reports.md): the current stock position,
 * which is point-in-time and ignores the requested period, alongside the
 * stock movements recorded within the period.
 */
class InventoryReportController extends ReportController
{
    protected function title(): string
    {
        return 'Inventory';
    }

    /**
     * Show the inventory position and the period's movements.
     */
    public function index(Request $request): Response
    {
        $period = $this->period($request);

        $datatable = new Datatable(
            $request,
            searchColumns: ['item.name'],
            orderColumns: ['moved_at', 'quantity'],
            defaultSort: 'moved_at',
            defaultDirection: 'desc',
        );

        $movements = $datatable->paginate($this->movementQuery($period))
            ->through(fn (StockMovement $movement): array => $this->movementLine($movement));

        return Inertia::render('admin/reports/InventoryReport', [
            'period' => $period->filters(),
            'summary' => $this->summary(),
            'position' => $this->positionRows(),
            'movements' => $movements,
            'filters' => $datatable->filters(),
            'exportParams' => $period->queryParameters(),
        ]);
    }

    /**
     * Download the inventory position and movements as an A4 PDF.
     */
    public function pdf(Request $request): HttpResponse
    {
        $period = $this->period($request);

        return $this->pdfResponse('pdf.reports.inventory', $period, [
            'summary' => $this->summary(),
            'position' => $this->positionRows(),
            'movements' => $this->movementQuery($period)
                ->orderBy('moved_at')
                ->get()
                ->map(fn (StockMovement $movement): array => $this->movementLine($movement))
                ->all(),
        ]);
    }

    /**
     * Export the current stock position as CSV.
     */
    public function csv(Request $request): StreamedResponse
    {
        $period = $this->period($request);

        $rows = InventoryItem::query()
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item): array => [
                $item->name,
                $item->unit,
                number_format((float) $item->unit_rate, 2, '.', ''),
                $item->quantity_in_stock,
                $item->reorder_level,
                $item->isLowStock() ? 'yes' : 'no',
                number_format((float) $item->unit_rate * $item->quantity_in_stock, 2, '.', ''),
            ]);

        return $this->csvResponse($period, [
            'Name', 'Unit', 'Unit rate', 'Quantity in stock', 'Reorder level', 'Low stock', 'Line value',
        ], $rows);
    }

    /**
     * The headline tiles: active item count, low-stock count, and total
     * stock value. Point-in-time, so it ignores the requested period.
     *
     * @return array{active_items: int, low_stock_count: int, total_stock_value: float}
     */
    protected function summary(): array
    {
        $active = InventoryItem::query()->where('is_active', true);

        return [
            'active_items' => (int) (clone $active)->count(),
            'low_stock_count' => (int) (clone $active)->whereColumn('quantity_in_stock', '<=', 'reorder_level')->count(),
            'total_stock_value' => (float) (clone $active)->selectRaw('sum(quantity_in_stock * unit_rate) as value')->value('value'),
        ];
    }

    /**
     * The current stock position, sorted by name.
     *
     * @return list<array{id: int, name: string, unit: string, unit_rate: float, quantity_in_stock: int, reorder_level: int, low_stock: bool, line_value: float}>
     */
    protected function positionRows(): array
    {
        $rows = InventoryItem::query()
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'unit_rate' => (float) $item->unit_rate,
                'quantity_in_stock' => $item->quantity_in_stock,
                'reorder_level' => $item->reorder_level,
                'low_stock' => $item->isLowStock(),
                'line_value' => round((float) $item->unit_rate * $item->quantity_in_stock, 2),
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * The stock movements recorded within the period.
     *
     * @return Builder<StockMovement>
     */
    protected function movementQuery(ReportPeriod $period): Builder
    {
        return StockMovement::query()
            ->with(['item:id,name', 'maintenanceJob:id,job_number', 'mover:id,first_name,last_name'])
            ->whereBetween('moved_at', [$period->start, $period->end]);
    }

    /**
     * The movement row shape shared by the page and the PDF.
     *
     * @return array{id: int, moved_at: string, item: string, type: string, quantity: int, unit_rate: float|null, job_number: string|null, recorded_by: string}
     */
    protected function movementLine(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'moved_at' => $movement->moved_at->format('d M Y'),
            'item' => $movement->item->name,
            'type' => $movement->movement_type->value,
            'quantity' => $movement->quantity,
            'unit_rate' => $movement->unit_rate === null ? null : (float) $movement->unit_rate,
            'job_number' => $movement->maintenanceJob?->job_number,
            'recorded_by' => $movement->mover->name,
        ];
    }
}
