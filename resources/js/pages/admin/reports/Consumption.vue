<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportExportButtons from '@/components/admin/reports/ReportExportButtons.vue';
import ReportPeriodFilter from '@/components/admin/reports/ReportPeriodFilter.vue';
import ReportStatTile from '@/components/admin/reports/ReportStatTile.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
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
import type { ReportPeriodFilters } from '@/lib/reports';
import { csv as csvUrl, index, pdf as pdfUrl } from '@/routes/admin/reports/consumption';
import type { DatatableFilters, Paginated } from '@/types';

type ReadingRow = {
  id: number;
  account_number: string;
  owner: string;
  category: string;
  billing_month: string;
  reading_value: number;
  consumption: number;
  reading_date: string;
};

type CategoryRow = {
  category: string;
  accounts_read: number;
  total_consumption: number;
  average_consumption: number;
};

const props = defineProps<{
  period: ReportPeriodFilters;
  summary: {
    total_consumption: number;
    accounts_read: number;
    average_per_account: number;
    active_accounts: number;
    coverage_percent: number;
  };
  byCategory: CategoryRow[];
  readings: Paginated<ReadingRow>;
  filters: DatatableFilters;
  exportParams: Record<string, string | number>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Consumption Report', href: index() }],
  },
});

const { sort, direction, sortBy } = useDatatable(index().url, props.filters);

const formatUnits = (value: number): string =>
  value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

const exportQuery = new URLSearchParams(
  Object.entries(props.exportParams).map(([key, value]) => [
    key,
    String(value),
  ]),
).toString();
</script>

<template>
  <Head title="Consumption Report" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Consumption"
        :description="`Metered consumption for ${period.label}`"
      />
      <ReportExportButtons
        :pdf-url="`${pdfUrl().url}?${exportQuery}`"
        :csv-url="`${csvUrl().url}?${exportQuery}`"
      />
    </div>

    <ReportPeriodFilter :action="index().url" :filters="period" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <ReportStatTile
        label="Total consumption"
        :value="`${formatUnits(summary.total_consumption)} units`"
      />
      <ReportStatTile
        label="Average per account"
        :value="`${summary.average_per_account.toFixed(1)} units`"
        :hint="`${summary.accounts_read} accounts read`"
      />
      <ReportStatTile
        label="Reading coverage"
        :value="`${summary.coverage_percent.toFixed(1)}%`"
        hint="Accounts read at least once vs. active accounts"
      />
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">By billing category</h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Category</TableHead>
              <TableHead class="text-right">Accounts read</TableHead>
              <TableHead class="text-right">Total consumption</TableHead>
              <TableHead class="text-right">Average per account</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="row in byCategory" :key="row.category">
              <TableCell class="font-medium">{{ row.category }}</TableCell>
              <TableCell class="text-right tabular-nums">
                {{ row.accounts_read }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatUnits(row.total_consumption) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ row.average_consumption.toFixed(1) }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="byCategory.length === 0" :colspan="4">
              No meter readings in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">Meter readings in period</h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Account</TableHead>
              <TableHead>Owner</TableHead>
              <TableHead>Category</TableHead>
              <SortableHead
                column="billing_month"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Month
              </SortableHead>
              <TableHead class="text-right">Reading</TableHead>
              <SortableHead
                column="consumption"
                :sort="sort"
                :direction="direction"
                class="text-right"
                @sort="sortBy"
              >
                Consumption
              </SortableHead>
              <SortableHead
                column="reading_date"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Reading date
              </SortableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="reading in readings.data" :key="reading.id">
              <TableCell class="font-medium">
                {{ reading.account_number }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ reading.owner }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ reading.category }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ reading.billing_month }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatUnits(reading.reading_value) }}
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatUnits(reading.consumption) }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ reading.reading_date }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="readings.data.length === 0" :colspan="7">
              No meter readings in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <Pagination :paginator="readings" />
  </div>
</template>
