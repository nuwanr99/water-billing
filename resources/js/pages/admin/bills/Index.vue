<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
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
import { useDatatable } from '@/composables/useDatatable';
import { index, show } from '@/routes/admin/bills';
import type { DatatableFilters, Paginated } from '@/types';

type BillStatus = 'generated' | 'approved' | 'paid' | 'overdue';

type BillRow = {
  id: number;
  bill_number: string;
  billing_month: string;
  account_number: string;
  owner_name: string;
  monthly_charge: number;
  total_due: number;
  status: BillStatus;
  due_date: string;
  is_reissue: boolean;
};

const props = defineProps<{
  bills: Paginated<BillRow>;
  filters: DatatableFilters;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Bills',
        href: index(),
      },
    ],
  },
});

const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

const statusLabels: Record<BillStatus, string> = {
  generated: 'Generated',
  approved: 'Approved',
  paid: 'Paid',
  overdue: 'Overdue',
};

const statusDotClasses: Record<BillStatus, string> = {
  generated: 'bg-muted-foreground',
  approved: 'bg-sky-500',
  paid: 'bg-emerald-500',
  overdue: 'bg-red-500',
};

function formatCurrency(amount: number): string {
  const formatted = Math.abs(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return amount < 0 ? `Rs ${formatted} CR` : `Rs ${formatted}`;
}

function formatMonth(billingMonth: string): string {
  const [year, month] = billingMonth.split('-');

  return new Date(Number(year), Number(month) - 1, 1).toLocaleString('en-US', {
    month: 'short',
    year: 'numeric',
  });
}
</script>

<template>
  <Head title="Bills" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Bills"
        description="Current bills across all water accounts"
      />
    </div>

    <SearchFilter
      v-model="search"
      placeholder="Search by bill number, month, account, or member..."
    />

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
              Bill #
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
            <TableHead>Member</TableHead>
            <TableHead>Monthly charge</TableHead>
            <SortableHead
              column="total_due"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Total due
            </SortableHead>
            <SortableHead
              column="status"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Status
            </SortableHead>
            <SortableHead
              column="due_date"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Due date
            </SortableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="bill in bills.data" :key="bill.id">
            <TableCell class="font-medium">
              <div class="flex items-center gap-2">
                <Link :href="show(bill.id)" class="hover:underline">
                  {{ bill.bill_number }}
                </Link>
                <Badge v-if="bill.is_reissue" variant="secondary">
                  Reissue
                </Badge>
              </div>
            </TableCell>
            <TableCell>{{ formatMonth(bill.billing_month) }}</TableCell>
            <TableCell class="text-muted-foreground">
              {{ bill.account_number }}
            </TableCell>
            <TableCell>{{ bill.owner_name }}</TableCell>
            <TableCell class="tabular-nums">
              {{ formatCurrency(bill.monthly_charge) }}
            </TableCell>
            <TableCell class="font-medium tabular-nums">
              {{ formatCurrency(bill.total_due) }}
            </TableCell>
            <TableCell>
              <Badge variant="outline" class="gap-1.5">
                <span
                  class="size-1.5 rounded-full"
                  :class="statusDotClasses[bill.status]"
                />
                {{ statusLabels[bill.status] }}
              </Badge>
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ bill.due_date }}
            </TableCell>
          </TableRow>
          <TableEmpty v-if="bills.data.length === 0" :colspan="8">
            No bills found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="bills" />
  </div>
</template>
