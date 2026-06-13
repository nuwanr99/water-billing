<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportExportButtons from '@/components/admin/reports/ReportExportButtons.vue';
import ReportPeriodFilter from '@/components/admin/reports/ReportPeriodFilter.vue';
import ReportStatTile from '@/components/admin/reports/ReportStatTile.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatRs  } from '@/lib/reports';
import type {ReportPeriodFilters} from '@/lib/reports';
import {
  csv as csvUrl,
  index,
  pdf as pdfUrl,
} from '@/routes/admin/reports/financial-position';

type TrialBalanceRow = {
  code: string;
  name: string;
  type: 'asset' | 'liability' | 'equity' | 'income' | 'expense';
  debit: number;
  credit: number;
  balance: number;
};

const props = defineProps<{
  period: ReportPeriodFilters;
  summary: {
    total_income: number;
    total_expense: number;
    net: number;
    total_debits: number;
    total_credits: number;
    is_balanced: boolean;
  };
  trialBalance: TrialBalanceRow[];
  exportParams: Record<string, string | number>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Financial Position Report', href: index() }],
  },
});

const exportQuery = new URLSearchParams(
  Object.entries(props.exportParams).map(([key, value]) => [
    key,
    String(value),
  ]),
).toString();
</script>

<template>
  <Head title="Financial Position Report" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Financial Position"
        :description="`Income and expense summary for ${period.label}`"
      />
      <ReportExportButtons
        :pdf-url="`${pdfUrl().url}?${exportQuery}`"
        :csv-url="`${csvUrl().url}?${exportQuery}`"
      />
    </div>

    <ReportPeriodFilter :action="index().url" :filters="period" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <ReportStatTile label="Total income" :value="formatRs(summary.total_income)" />
      <ReportStatTile label="Total expense" :value="formatRs(summary.total_expense)" />
      <ReportStatTile label="Net" :value="formatRs(summary.net)" />
      <ReportStatTile
        label="Total debits / credits"
        :value="formatRs(summary.total_debits)"
        :hint="summary.is_balanced ? 'Balanced' : 'Out of balance'"
      />
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex items-center gap-2">
        <h3 class="text-sm font-medium">Chart of accounts (trial balance)</h3>
        <Badge :variant="summary.is_balanced ? 'secondary' : 'destructive'">
          {{ summary.is_balanced ? 'Balanced' : 'Out of balance' }}
        </Badge>
      </div>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Code</TableHead>
              <TableHead>Account</TableHead>
              <TableHead class="capitalize">Type</TableHead>
              <TableHead class="text-right">Debit</TableHead>
              <TableHead class="text-right">Credit</TableHead>
              <TableHead class="text-right">Balance</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="row in trialBalance" :key="row.code">
              <TableCell class="font-medium">{{ row.code }}</TableCell>
              <TableCell>{{ row.name }}</TableCell>
              <TableCell class="capitalize text-muted-foreground">
                {{ row.type }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(row.debit) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(row.credit) }}
              </TableCell>
              <TableCell class="text-right font-medium tabular-nums">
                {{ formatRs(row.balance) }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="trialBalance.length === 0" :colspan="6">
              No accounts found.
            </TableEmpty>
          </TableBody>
          <tfoot v-if="trialBalance.length > 0">
            <TableRow class="font-semibold">
              <TableCell colspan="3">Total</TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(summary.total_debits) }}
              </TableCell>
              <TableCell class="text-right tabular-nums">
                {{ formatRs(summary.total_credits) }}
              </TableCell>
              <TableCell></TableCell>
            </TableRow>
          </tfoot>
        </Table>
      </div>
    </div>
  </div>
</template>
