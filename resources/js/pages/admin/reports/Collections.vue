<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ReportExportButtons from '@/components/admin/reports/ReportExportButtons.vue';
import ReportPeriodFilter from '@/components/admin/reports/ReportPeriodFilter.vue';
import ReportStatTile from '@/components/admin/reports/ReportStatTile.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
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
} from '@/routes/admin/reports/collections';
import type { DatatableFilters, Paginated } from '@/types';

type PaymentRow = {
  id: number;
  receipt_number: string;
  account_number: string;
  owner: string;
  method: string;
  amount: number;
  paid_at: string;
};

type SeriesRow = {
  bucket_label: string;
  count: number;
  total: number;
};

const props = defineProps<{
  period: ReportPeriodFilters;
  reportFilters: {
    method: string | null;
  };
  methods: string[];
  summary: {
    total_collected: number;
    payment_count: number;
    manual_total: number;
    payhere_total: number;
    total_billed: number;
    collection_efficiency: number | null;
  };
  series: SeriesRow[];
  payments: Paginated<PaymentRow>;
  filters: DatatableFilters;
  exportParams: Record<string, string | number>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Collection / Payment Status Report', href: index() }],
  },
});

const { sort, direction, sortBy } = useDatatable(index().url, props.filters);

const method = ref(props.reportFilters.method ?? 'all');

const reload = (params: Record<string, string | number | null>) => {
  router.get(
    index().url,
    Object.fromEntries(
      Object.entries({
        period_type: props.period.period_type,
        month: props.period.month,
        quarter: props.period.quarter,
        year: props.period.year,
        from: props.period.from,
        to: props.period.to,
        method: method.value === 'all' ? null : method.value,
        ...params,
      }).filter(([, value]) => value !== null && value !== ''),
    ),
    { preserveState: true, preserveScroll: true },
  );
};

watch(method, () => reload({}));

const periodExtraParams = {
  method: method.value === 'all' ? null : method.value,
};

const exportQuery = new URLSearchParams(
  Object.entries(props.exportParams).map(([key, value]) => [
    key,
    String(value),
  ]),
).toString();
</script>

<template>
  <Head title="Collection / Payment Status Report" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Collection / Payment Status"
        :description="`What was collected for ${period.label}`"
      />
      <ReportExportButtons
        :pdf-url="`${pdfUrl().url}?${exportQuery}`"
        :csv-url="`${csvUrl().url}?${exportQuery}`"
      />
    </div>

    <div class="flex flex-wrap items-end gap-3">
      <ReportPeriodFilter
        :action="index().url"
        :filters="period"
        :extra-params="periodExtraParams"
      />

      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">Method</Label>
        <Select v-model="method">
          <SelectTrigger class="w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All methods</SelectItem>
            <SelectItem
              v-for="option in methods"
              :key="option"
              :value="option"
              class="capitalize"
            >
              {{ option }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
      <ReportStatTile label="Total collected" :value="formatRs(summary.total_collected)" />
      <ReportStatTile label="Payments" :value="summary.payment_count" />
      <ReportStatTile label="Manual" :value="formatRs(summary.manual_total)" />
      <ReportStatTile label="PayHere" :value="formatRs(summary.payhere_total)" />
      <ReportStatTile
        label="Collection efficiency"
        :value="summary.collection_efficiency === null ? 'N/A' : `${summary.collection_efficiency}%`"
      />
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">Sub-totals</h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Period</TableHead>
              <TableHead class="text-right">Payments</TableHead>
              <TableHead class="text-right">Total</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="row in series" :key="row.bucket_label">
              <TableCell class="font-medium">{{ row.bucket_label }}</TableCell>
              <TableCell class="text-right tabular-nums">
                {{ row.count }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(row.total) }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="series.length === 0" :colspan="3">
              No payments in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">Receipts in period</h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <SortableHead
                column="receipt_number"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Receipt
              </SortableHead>
              <TableHead>Account</TableHead>
              <TableHead>Owner</TableHead>
              <TableHead>Method</TableHead>
              <SortableHead
                column="amount"
                :sort="sort"
                :direction="direction"
                class="text-right"
                @sort="sortBy"
              >
                Amount
              </SortableHead>
              <SortableHead
                column="paid_at"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Paid at
              </SortableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="payment in payments.data" :key="payment.id">
              <TableCell class="font-medium">
                {{ payment.receipt_number }}
              </TableCell>
              <TableCell>{{ payment.account_number }}</TableCell>
              <TableCell class="text-muted-foreground">
                {{ payment.owner }}
              </TableCell>
              <TableCell class="capitalize text-muted-foreground">
                {{ payment.method }}
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatRs(payment.amount) }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ payment.paid_at }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="payments.data.length === 0" :colspan="6">
              No payments in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <Pagination :paginator="payments" />
  </div>
</template>
