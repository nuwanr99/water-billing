<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpDown, Plus } from '@lucide/vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import TransferDialog from '@/components/admin/TransferDialog.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { usePermission } from '@/composables/usePermission';
import { create, index, show } from '@/routes/admin/system-ledger';
import type { DatatableFilters, Paginated } from '@/types';

type EntryRow = {
  id: number;
  reference_number: string;
  entry_date: string;
  description: string;
  source_type: string | null;
  total_debit: number;
  is_posted: boolean;
};

const props = defineProps<{
  entries: Paginated<EntryRow>;
  filters: DatatableFilters;
  transferAccounts: {
    id: number;
    code: string;
    name: string;
  }[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'System ledger',
        href: index(),
      },
    ],
  },
});

const { hasPermission } = usePermission();

const { search } = useDatatable(index().url, props.filters);

const sourceLabels: Record<string, string> = {
  manual: 'Manual',
  payment: 'Payment',
  expense: 'Expense',
  transfer: 'Transfer',
};

const sourceDotClasses: Record<string, string> = {
  manual: 'bg-muted-foreground',
  payment: 'bg-emerald-500',
  expense: 'bg-red-500',
  transfer: 'bg-blue-500',
};

function formatCurrency(amount: number): string {
  return `Rs ${amount.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}
</script>

<template>
  <Head title="System ledger" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="System ledger"
        description="The society's journal of posted double-entry transactions"
      />
      <div
        v-if="hasPermission('system-ledger.manage')"
        class="flex flex-wrap items-center gap-2"
      >
        <TransferDialog :accounts="transferAccounts">
          <Button variant="outline">
            <ArrowUpDown class="size-4" />
            Transfer
          </Button>
        </TransferDialog>
        <Button as-child>
          <Link :href="create()">
            <Plus class="size-4" />
            New journal entry
          </Link>
        </Button>
      </div>
    </div>

    <SearchFilter
      v-model="search"
      placeholder="Search by reference or description..."
    />

    <div class="rounded-xl border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Reference</TableHead>
            <TableHead>Date</TableHead>
            <TableHead>Description</TableHead>
            <TableHead>Source</TableHead>
            <TableHead>Amount</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="entry in entries.data" :key="entry.id">
            <TableCell class="font-medium">
              <Link :href="show(entry.id)" class="hover:underline">
                {{ entry.reference_number }}
              </Link>
            </TableCell>
            <TableCell class="whitespace-nowrap">
              {{ entry.entry_date }}
            </TableCell>
            <TableCell class="max-w-64 truncate text-muted-foreground">
              {{ entry.description }}
            </TableCell>
            <TableCell>
              <Badge v-if="entry.source_type" variant="outline" class="gap-1.5">
                <span
                  class="size-1.5 rounded-full"
                  :class="
                    sourceDotClasses[entry.source_type] ?? 'bg-muted-foreground'
                  "
                />
                {{ sourceLabels[entry.source_type] ?? entry.source_type }}
              </Badge>
              <span v-else class="text-muted-foreground">—</span>
            </TableCell>
            <TableCell class="tabular-nums">
              {{ formatCurrency(entry.total_debit) }}
            </TableCell>
          </TableRow>
          <TableEmpty v-if="entries.data.length === 0" :colspan="5">
            No journal entries found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="entries" />
  </div>
</template>
