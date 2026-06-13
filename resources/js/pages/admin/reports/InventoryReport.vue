<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportExportButtons from '@/components/admin/reports/ReportExportButtons.vue';
import ReportPeriodFilter from '@/components/admin/reports/ReportPeriodFilter.vue';
import ReportStatTile from '@/components/admin/reports/ReportStatTile.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { useDatatable } from '@/composables/useDatatable';
import { formatRs  } from '@/lib/reports';
import type {ReportPeriodFilters} from '@/lib/reports';
import {
  csv as csvUrl,
  index,
  pdf as pdfUrl,
} from '@/routes/admin/reports/inventory';
import type { DatatableFilters, Paginated } from '@/types';

type PositionRow = {
  id: number;
  name: string;
  unit: string;
  unit_rate: number;
  quantity_in_stock: number;
  reorder_level: number;
  low_stock: boolean;
  line_value: number;
};

type MovementRow = {
  id: number;
  moved_at: string;
  item: string;
  type: string;
  quantity: number;
  unit_rate: number | null;
  job_number: string | null;
  recorded_by: string;
};

const props = defineProps<{
  period: ReportPeriodFilters;
  summary: {
    active_items: number;
    low_stock_count: number;
    total_stock_value: number;
  };
  position: PositionRow[];
  movements: Paginated<MovementRow>;
  filters: DatatableFilters;
  exportParams: Record<string, string | number>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Inventory Report', href: index() }],
  },
});

const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

const exportQuery = new URLSearchParams(
  Object.entries(props.exportParams).map(([key, value]) => [
    key,
    String(value),
  ]),
).toString();
</script>

<template>
  <Head title="Inventory Report" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Inventory"
        :description="`Stock position now, movements for ${period.label}`"
      />
      <ReportExportButtons
        :pdf-url="`${pdfUrl().url}?${exportQuery}`"
        :csv-url="`${csvUrl().url}?${exportQuery}`"
      />
    </div>

    <ReportPeriodFilter :action="index().url" :filters="period" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <ReportStatTile label="Active items" :value="summary.active_items" />
      <ReportStatTile
        label="Low stock"
        :value="summary.low_stock_count"
        hint="At or below reorder level"
      />
      <ReportStatTile
        label="Total stock value"
        :value="formatRs(summary.total_stock_value)"
      />
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">Current stock position</h3>
      <p class="text-xs text-muted-foreground">
        Point-in-time as of now — not scoped to the selected period.
      </p>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Item</TableHead>
              <TableHead>Unit</TableHead>
              <TableHead class="text-right">Unit rate</TableHead>
              <TableHead class="text-right">In stock</TableHead>
              <TableHead class="text-right">Reorder level</TableHead>
              <TableHead>Status</TableHead>
              <TableHead class="text-right">Line value</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="item in position" :key="item.id">
              <TableCell class="font-medium">{{ item.name }}</TableCell>
              <TableCell class="text-muted-foreground">
                {{ item.unit }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(item.unit_rate) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ item.quantity_in_stock }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ item.reorder_level }}
              </TableCell>
              <TableCell>
                <Badge v-if="item.low_stock" variant="destructive">
                  Low stock
                </Badge>
                <Badge v-else variant="secondary">OK</Badge>
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatRs(item.line_value) }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="position.length === 0" :colspan="7">
              No inventory items.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-medium">Movements in period</h3>
        <Input
          v-model="search"
          type="search"
          placeholder="Search item..."
          class="w-56"
        />
      </div>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <SortableHead
                column="moved_at"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Date
              </SortableHead>
              <TableHead>Item</TableHead>
              <TableHead>Type</TableHead>
              <SortableHead
                column="quantity"
                :sort="sort"
                :direction="direction"
                class="text-right"
                @sort="sortBy"
              >
                Quantity
              </SortableHead>
              <TableHead class="text-right">Unit rate</TableHead>
              <TableHead>Job</TableHead>
              <TableHead>Recorded by</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="movement in movements.data" :key="movement.id">
              <TableCell class="text-muted-foreground">
                {{ movement.moved_at }}
              </TableCell>
              <TableCell class="font-medium">{{ movement.item }}</TableCell>
              <TableCell class="capitalize">{{ movement.type }}</TableCell>
              <TableCell class="text-right tabular-nums">
                {{ movement.quantity }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{
                  movement.unit_rate === null
                    ? '—'
                    : formatRs(movement.unit_rate)
                }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ movement.job_number ?? '—' }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ movement.recorded_by }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="movements.data.length === 0" :colspan="7">
              No movements in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <Pagination :paginator="movements" />
  </div>
</template>
