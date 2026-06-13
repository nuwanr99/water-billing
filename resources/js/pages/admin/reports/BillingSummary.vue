<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { ref, watch } from 'vue';
import ReportExportButtons from '@/components/admin/reports/ReportExportButtons.vue';
import ReportPeriodFilter from '@/components/admin/reports/ReportPeriodFilter.vue';
import ReportStatTile from '@/components/admin/reports/ReportStatTile.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import BillStatusBadge from '@/components/BillStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import SearchableCombobox from '@/components/SearchableCombobox.vue';
import { Button } from '@/components/ui/button';
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
import type { BillStatus } from '@/lib/bills';
import { formatRs  } from '@/lib/reports';
import type {ReportPeriodFilters} from '@/lib/reports';
import {
  accounts as accountsUrl,
  csv as csvUrl,
  index,
  pdf as pdfUrl,
} from '@/routes/admin/reports/billing';
import type { ComboboxOption, DatatableFilters, Paginated } from '@/types';

type BillRow = {
  id: number;
  bill_number: string;
  billing_month: string;
  account_number: string;
  owner: string;
  category: string;
  total_due: number;
  status: BillStatus;
  due_date: string;
};

type CategoryRow = {
  category: string;
  bill_count: number;
  total_billed: number;
  total_paid: number;
  total_outstanding: number;
};

const props = defineProps<{
  period: ReportPeriodFilters;
  reportFilters: {
    category: number | null;
    status: string | null;
    account: number | null;
  };
  categories: { id: number; name: string }[];
  statuses: string[];
  summary: {
    total_billed: number;
    bill_count: number;
    status_counts: Record<string, number>;
  };
  byCategory: CategoryRow[];
  bills: Paginated<BillRow>;
  filters: DatatableFilters;
  statement: {
    account_number: string;
    owner: string;
    category: string;
    connection_address: string;
  } | null;
  exportParams: Record<string, string | number>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Billing Summary Report', href: index() }],
  },
});

const { sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

const category = ref(
  props.reportFilters.category === null
    ? 'all'
    : String(props.reportFilters.category),
);
const status = ref(props.reportFilters.status ?? 'all');

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
        status: status.value === 'all' ? null : status.value,
        account: props.reportFilters.account,
        ...params,
      }).filter(([, value]) => value !== null && value !== ''),
    ),
    { preserveState: true, preserveScroll: true },
  );
};

watch([category, status], () => reload({}));

// Statement account picker: searches the accounts endpoint as the user types.
const accountOptions = ref<ComboboxOption[]>([]);
const accountSearch = ref('');
const selectedAccount = ref<ComboboxOption | null>(null);
const { get: searchAccounts, processing: searchingAccounts } = useHttp();

watch(accountSearch, async (term) => {
  const results = (await searchAccounts(
    `${accountsUrl().url}?search=${encodeURIComponent(term ?? '')}`,
  )) as { id: number; account_number: string; owner: string }[];

  accountOptions.value = results.map((account) => ({
    value: account.id,
    label: `${account.account_number} — ${account.owner}`,
  }));
});

watch(selectedAccount, (option) => {
  reload({ account: option === null ? null : option.value });
});

const clearStatement = () => reload({ account: null });

const periodExtraParams = {
  category: category.value === 'all' ? null : category.value,
  status: status.value === 'all' ? null : status.value,
  account: props.reportFilters.account,
};

const exportQuery = new URLSearchParams(
  Object.entries(props.exportParams).map(([key, value]) => [
    key,
    String(value),
  ]),
).toString();
</script>

<template>
  <Head title="Billing Summary Report" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Billing Summary"
        :description="`What was billed for ${period.label}`"
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
          <SelectTrigger class="w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All categories</SelectItem>
            <SelectItem
              v-for="option in categories"
              :key="option.id"
              :value="String(option.id)"
            >
              {{ option.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">Status</Label>
        <Select v-model="status">
          <SelectTrigger class="w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem
              v-for="option in statuses"
              :key="option"
              :value="option"
              class="capitalize"
            >
              {{ option }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid w-64 gap-1.5">
        <Label class="text-xs text-muted-foreground">
          Account statement
        </Label>
        <SearchableCombobox
          v-model="selectedAccount"
          v-model:search-term="accountSearch"
          :options="accountOptions"
          :loading="searchingAccounts"
          placeholder="Search account or owner..."
        />
      </div>
    </div>

    <div
      v-if="statement"
      class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/50 px-4 py-3"
    >
      <div class="text-sm">
        <span class="font-semibold">{{ statement.account_number }}</span>
        <span class="text-muted-foreground">
          — {{ statement.owner }} · {{ statement.category }} ·
          {{ statement.connection_address }}
        </span>
      </div>
      <Button variant="ghost" size="sm" @click="clearStatement">
        <X class="size-4" />
        Clear statement
      </Button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
      <ReportStatTile label="Total billed" :value="formatRs(summary.total_billed)" />
      <ReportStatTile label="Bills" :value="summary.bill_count" />
      <ReportStatTile
        v-for="(count, name) in summary.status_counts"
        :key="name"
        :label="String(name)"
        :value="count"
      />
    </div>

    <div v-if="!statement" class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">By billing category</h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Category</TableHead>
              <TableHead class="text-right">Bills</TableHead>
              <TableHead class="text-right">Total billed</TableHead>
              <TableHead class="text-right">Total paid</TableHead>
              <TableHead class="text-right">Outstanding</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="row in byCategory" :key="row.category">
              <TableCell class="font-medium">{{ row.category }}</TableCell>
              <TableCell class="text-right tabular-nums">
                {{ row.bill_count }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(row.total_billed) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(row.total_paid) }}
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatRs(row.total_outstanding) }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="byCategory.length === 0" :colspan="5">
              No bills in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">
        {{ statement ? 'Billing statement' : 'Bills in period' }}
      </h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <SortableHead
                column="bill_number"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Bill
              </SortableHead>
              <SortableHead
                column="billing_month"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Month
              </SortableHead>
              <TableHead>Account</TableHead>
              <TableHead>Owner</TableHead>
              <TableHead>Category</TableHead>
              <SortableHead
                column="total_due"
                :sort="sort"
                :direction="direction"
                class="text-right"
                @sort="sortBy"
              >
                Total due
              </SortableHead>
              <SortableHead
                column="due_date"
                :sort="sort"
                :direction="direction"
                @sort="sortBy"
              >
                Due date
              </SortableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="bill in bills.data" :key="bill.id">
              <TableCell class="font-medium">{{ bill.bill_number }}</TableCell>
              <TableCell class="text-muted-foreground">
                {{ bill.billing_month }}
              </TableCell>
              <TableCell>{{ bill.account_number }}</TableCell>
              <TableCell class="text-muted-foreground">
                {{ bill.owner }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ bill.category }}
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatRs(bill.total_due) }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ bill.due_date }}
              </TableCell>
              <TableCell>
                <BillStatusBadge :status="bill.status" />
              </TableCell>
            </TableRow>
            <TableEmpty v-if="bills.data.length === 0" :colspan="8">
              No bills in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <Pagination :paginator="bills" />
  </div>
</template>
