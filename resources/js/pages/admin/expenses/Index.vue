<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
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
import { create, index, show } from '@/routes/admin/expenses';
import type { DatatableFilters, Paginated } from '@/types';

type ExpenseRow = {
  id: number;
  expense_number: string;
  expense_date: string;
  amount: number;
  category: string;
  paid_from: string;
  description: string;
  job_number: string | null;
};

const props = defineProps<{
  expenses: Paginated<ExpenseRow>;
  filters: DatatableFilters;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Expenses', href: index() }],
  },
});

const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

const formatRs = (value: number): string =>
  `Rs ${value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
</script>

<template>
  <Head title="Expenses" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Expenses"
        description="Record society spending against the ledger"
      />
      <Button as-child>
        <Link :href="create()">
          <Plus class="size-4" />
          New expense
        </Link>
      </Button>
    </div>

    <SearchFilter
      v-model="search"
      placeholder="Search by number or description..."
    />

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
              Number
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
            <TableHead>Job</TableHead>
            <SortableHead
              column="amount"
              :sort="sort"
              :direction="direction"
              class="text-right"
              @sort="sortBy"
            >
              Amount
            </SortableHead>
            <TableHead class="w-0">
              <span class="sr-only">Actions</span>
            </TableHead>
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
            <TableCell class="text-muted-foreground">
              {{ expense.job_number ?? '—' }}
            </TableCell>
            <TableCell class="text-right font-medium tabular-nums">
              {{ formatRs(expense.amount) }}
            </TableCell>
            <TableCell>
              <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="show(expense.id)">View</Link>
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableEmpty v-if="expenses.data.length === 0" :colspan="7">
            No expenses recorded yet.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="expenses" />
  </div>
</template>
