<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Banknote, Paperclip } from '@lucide/vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
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
import { collect, index, receipt } from '@/routes/admin/payments';
import type { DatatableFilters, Paginated } from '@/types';

type PaymentMethod = 'manual' | 'payhere';

type PaymentStatus = 'pending' | 'completed' | 'failed';

type PaymentRow = {
  id: number;
  receipt_number: string;
  account_number: string;
  owner_name: string;
  amount: number;
  method: PaymentMethod;
  status: PaymentStatus;
  destination: string | null;
  reference: string | null;
  has_attachment: boolean;
  recorded_by: string | null;
  paid_at: string;
};

const props = defineProps<{
  payments: Paginated<PaymentRow>;
  filters: DatatableFilters;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Payments',
        href: index(),
      },
    ],
  },
});

const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

const { hasPermission } = usePermission();

const methodLabels: Record<PaymentMethod, string> = {
  manual: 'Manual',
  payhere: 'PayHere',
};

const methodClasses: Record<PaymentMethod, string> = {
  manual:
    'border-transparent bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
  payhere:
    'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
};

const statusLabels: Record<PaymentStatus, string> = {
  pending: 'Pending',
  completed: 'Completed',
  failed: 'Failed',
};

const statusDotClasses: Record<PaymentStatus, string> = {
  pending: 'bg-amber-500',
  completed: 'bg-emerald-500',
  failed: 'bg-red-500',
};

function formatCurrency(amount: number): string {
  const formatted = Math.abs(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return amount < 0 ? `Rs ${formatted} CR` : `Rs ${formatted}`;
}
</script>

<template>
  <Head title="Payments" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Payments"
        description="Every receipt recorded across all water accounts"
      />
      <Button v-if="hasPermission('payments.record-manual')" as-child>
        <Link :href="collect()">
          <Banknote class="size-4" />
          Collect payment
        </Link>
      </Button>
    </div>

    <SearchFilter
      v-model="search"
      placeholder="Search by receipt, reference, account, or member..."
    />

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
              Receipt #
            </SortableHead>
            <SortableHead
              column="paid_at"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Date
            </SortableHead>
            <TableHead>Account</TableHead>
            <TableHead>Member</TableHead>
            <SortableHead
              column="amount"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Amount
            </SortableHead>
            <SortableHead
              column="method"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Method
            </SortableHead>
            <SortableHead
              column="status"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Status
            </SortableHead>
            <TableHead>Destination</TableHead>
            <TableHead>Recorded by</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="payment in payments.data" :key="payment.id">
            <TableCell class="font-medium">
              <Link :href="receipt(payment.id)" class="hover:underline">
                {{ payment.receipt_number }}
              </Link>
            </TableCell>
            <TableCell class="whitespace-nowrap text-muted-foreground">
              {{ payment.paid_at }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ payment.account_number }}
            </TableCell>
            <TableCell>{{ payment.owner_name }}</TableCell>
            <TableCell class="font-medium tabular-nums">
              {{ formatCurrency(payment.amount) }}
            </TableCell>
            <TableCell>
              <Badge variant="outline" :class="methodClasses[payment.method]">
                {{ methodLabels[payment.method] }}
              </Badge>
            </TableCell>
            <TableCell>
              <Badge variant="outline" class="gap-1.5">
                <span
                  class="size-1.5 rounded-full"
                  :class="statusDotClasses[payment.status]"
                />
                {{ statusLabels[payment.status] }}
              </Badge>
            </TableCell>
            <TableCell class="text-muted-foreground">
              <div class="flex items-center gap-1.5">
                <span>{{ payment.destination ?? '—' }}</span>
                <span
                  v-if="payment.has_attachment"
                  title="Has attachment"
                  aria-label="Has attachment"
                >
                  <Paperclip class="size-3.5" />
                </span>
              </div>
              <div v-if="payment.reference" class="text-xs">
                Ref {{ payment.reference }}
              </div>
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ payment.recorded_by ?? '—' }}
            </TableCell>
          </TableRow>
          <TableEmpty v-if="payments.data.length === 0" :colspan="9">
            No payments found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="payments" />
  </div>
</template>
