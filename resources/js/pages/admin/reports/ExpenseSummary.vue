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
import { csv as csvUrl, index, pdf as pdfUrl } from '@/routes/admin/reports/expenses';
import type { DatatableFilters, Paginated } from '@/types';

type ExpenseRow = {
  id: number;
  expense_number: string;
  expense_date: string;
  category: string;
  paid_from: string;
  amount: number;
  description: string;
  job_number: string | null;
};

type CategoryRow = {
  account_id: number;
  code: string;
  name: string;
  expense_count: number;
  total: number;
};

const props = defineProps<{
  period: ReportPeriodFilters;
  reportFilters: {
    category: number | null;
    job: number | null;
  };
  categories: { id: number; code: string; name: string }[];
  jobs: { id: number; job_number: string; title: string }[];
  summary: {
    total_expenditure: number;
    expense_count: number;
    category_count: number;
  };
  byCategory: CategoryRow[];
  expenses: Paginated<ExpenseRow>;
  filters: DatatableFilters;
  exportParams: Record<string, string | number>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Expense Summary Report', href: index() }],
  },
});

const { sort, direction, sortBy } = useDatatable(index().url, props.filters);

const category = ref(
  props.reportFilters.category === null
    ? 'all'
    : String(props.reportFilters.category),
);
const job = ref(
  props.reportFilters.job === null ? 'all' : String(props.reportFilters.job),
);

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
        category: category.value === 'all' ? null : category.value,
        job: job.value === 'all' ? null : job.value,
        ...params,
      }).filter(([, value]) => value !== null && value !== ''),
    ),
    { preserveState: true, preserveScroll: true },
  );
};

watch([category, job], () => reload({}));

const periodExtraParams = {
  category: category.value === 'all' ? null : category.value,
  job: job.value === 'all' ? null : job.value,
};

const exportQuery = new URLSearchParams(
  Object.entries(props.exportParams).map(([key, value]) => [
    key,
    String(value),
  ]),
).toString();
</script>

<template>
  <Head title="Expense Summary Report" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Expense Summary"
        :description="`Operating expenditure for ${period.label}`"
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
        <Label class="text-xs text-muted-foreground">Category</Label>
        <Select v-model="category">
          <SelectTrigger class="w-44">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All categories</SelectItem>
            <SelectItem
              v-for="option in categories"
              :key="option.id"
              :value="String(option.id)"
            >
              {{ option.code }} — {{ option.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">Job</Label>
        <Select v-model="job">
          <SelectTrigger class="w-44">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All jobs</SelectItem>
            <SelectItem
              v-for="option in jobs"
              :key="option.id"
              :value="String(option.id)"
            >
              {{ option.job_number }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <ReportStatTile
        label="Total expenditure"
        :value="formatRs(summary.total_expenditure)"
      />
      <ReportStatTile label="Expenses" :value="summary.expense_count" />
      <ReportStatTile
        label="Categories used"
        :value="summary.category_count"
      />
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">By expense category</h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Code</TableHead>
              <TableHead>Category</TableHead>
              <TableHead class="text-right">Count</TableHead>
              <TableHead class="text-right">Total</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="row in byCategory" :key="row.account_id">
              <TableCell class="text-muted-foreground">
                {{ row.code }}
              </TableCell>
              <TableCell class="font-medium">{{ row.name }}</TableCell>
              <TableCell class="text-right tabular-nums">
                {{ row.expense_count }}
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatRs(row.total) }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="byCategory.length === 0" :colspan="4">
              No expenses in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">Expenses in period</h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <SortableHead
                column="expense_number"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Expense
              </SortableHead>
              <SortableHead
                column="expense_date"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Date
              </SortableHead>
              <TableHead>Category</TableHead>
              <TableHead>Paid from</TableHead>
              <SortableHead
                column="amount"
                :sort="sort"
                :direction="direction"
                class="text-right"
                @sort="sortBy"
              >
                Amount
              </SortableHead>
              <TableHead>Description</TableHead>
              <TableHead>Job</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="expense in expenses.data" :key="expense.id">
              <TableCell class="font-medium">
                {{ expense.expense_number }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ expense.expense_date }}
              </TableCell>
              <TableCell>{{ expense.category }}</TableCell>
              <TableCell class="text-muted-foreground">
                {{ expense.paid_from }}
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatRs(expense.amount) }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ expense.description }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ expense.job_number ?? '—' }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="expenses.data.length === 0" :colspan="7">
              No expenses in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <Pagination :paginator="expenses" />
  </div>
</template>
