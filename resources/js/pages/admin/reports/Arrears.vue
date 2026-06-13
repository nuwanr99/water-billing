<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportExportButtons from '@/components/admin/reports/ReportExportButtons.vue';
import ReportPeriodFilter from '@/components/admin/reports/ReportPeriodFilter.vue';
import ReportStatTile from '@/components/admin/reports/ReportStatTile.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
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
} from '@/routes/admin/reports/arrears';
import type { DatatableFilters, Paginated } from '@/types';

type AgingBuckets = {
  current: number;
  '30_59': number;
  '60_89': number;
  '90_plus': number;
};

type AccountRow = {
  id: number;
  account_number: string;
  owner: string;
  connection_address: string;
  balance: number;
  aging: AgingBuckets;
};

const props = defineProps<{
  period: ReportPeriodFilters;
  asOf: string;
  summary: {
    total_arrears: number;
    account_count: number;
  };
  aging: AgingBuckets;
  accounts: Paginated<AccountRow>;
  filters: DatatableFilters;
  exportParams: Record<string, string | number>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Arrears / Outstanding Balances', href: index() }],
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
  <Head title="Arrears / Outstanding Balances Report" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Arrears / Outstanding Balances"
        description="Who owes the society money, and how much"
      />
      <ReportExportButtons
        :pdf-url="`${pdfUrl().url}?${exportQuery}`"
        :csv-url="`${csvUrl().url}?${exportQuery}`"
      />
    </div>

    <ReportPeriodFilter :action="index().url" :filters="period" />

    <p class="text-xs text-muted-foreground">
      Balances as at {{ asOf }} — a point-in-time snapshot, not scoped to the
      selected period.
    </p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <ReportStatTile
        label="Total arrears"
        :value="formatRs(summary.total_arrears)"
      />
      <ReportStatTile
        label="Accounts in arrears"
        :value="summary.account_count"
      />
    </div>

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">Aging summary</h3>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <ReportStatTile label="Current (<30d)" :value="formatRs(aging.current)" />
        <ReportStatTile label="30-59 days" :value="formatRs(aging['30_59'])" />
        <ReportStatTile label="60-89 days" :value="formatRs(aging['60_89'])" />
        <ReportStatTile label="90+ days" :value="formatRs(aging['90_plus'])" />
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-medium">Accounts in arrears</h3>
        <Input
          v-model="search"
          type="search"
          placeholder="Search account or owner..."
          class="w-64"
        />
      </div>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Account</TableHead>
              <TableHead>Owner</TableHead>
              <TableHead>Connection address</TableHead>
              <SortableHead
                column="balance"
                :sort="sort"
                :direction="direction"
                class="text-right"
                @sort="sortBy"
              >
                Balance
              </SortableHead>
              <TableHead class="text-right">Current</TableHead>
              <TableHead class="text-right">30-59d</TableHead>
              <TableHead class="text-right">60-89d</TableHead>
              <TableHead class="text-right">90+d</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="account in accounts.data" :key="account.id">
              <TableCell class="font-medium">
                {{ account.account_number }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ account.owner }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ account.connection_address || '—' }}
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatRs(account.balance) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(account.aging.current) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(account.aging['30_59']) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(account.aging['60_89']) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(account.aging['90_plus']) }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="accounts.data.length === 0" :colspan="8">
              No accounts in arrears.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <Pagination :paginator="accounts" />
  </div>
</template>
