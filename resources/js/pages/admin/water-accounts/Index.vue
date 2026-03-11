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
import WaterAccountStatusBadge from '@/components/WaterAccountStatusBadge.vue';
import { useDatatable } from '@/composables/useDatatable';
import { usePermission } from '@/composables/usePermission';
import { create, edit, index, statement } from '@/routes/admin/water-accounts';
import type { DatatableFilters, Paginated, WaterAccountStatus } from '@/types';

type WaterAccountRow = {
  id: number;
  account_number: string;
  meter_number: string;
  owner: { id: number; name: string };
  billing_category: string | null;
  status: WaterAccountStatus;
  balance: number;
  connected_at: string | null;
  created_at: string | null;
};

const props = defineProps<{
  waterAccounts: Paginated<WaterAccountRow>;
  filters: DatatableFilters;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Water accounts',
        href: index(),
      },
    ],
  },
});

const { hasPermission } = usePermission();
const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

/**
 * Balances display with two decimals and a "Rs " prefix; a negative balance
 * is credit in the member's favour, shown as "CR".
 */
const formatBalance = (value: number): string => {
  const absolute = Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return value < 0 ? `Rs ${absolute} CR` : `Rs ${absolute}`;
};
</script>

<template>
  <Head title="Water accounts" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Water accounts"
        description="Manage water connections and their owners"
      />
      <Button v-if="hasPermission('water-accounts.create')" as-child>
        <Link :href="create()">
          <Plus class="size-4" />
          New water account
        </Link>
      </Button>
    </div>

    <SearchFilter
      v-model="search"
      placeholder="Search by account, meter, or owner..."
    />

    <div class="rounded-xl border">
      <Table>
        <TableHeader>
          <TableRow>
            <SortableHead
              column="account_number"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Account
            </SortableHead>
            <SortableHead
              column="owner"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Owner
            </SortableHead>
            <TableHead>Meter</TableHead>
            <TableHead>Category</TableHead>
            <SortableHead
              column="balance"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Balance
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
              column="connected_at"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Connected
            </SortableHead>
            <TableHead class="w-0">
              <span class="sr-only">Actions</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="waterAccount in waterAccounts.data"
            :key="waterAccount.id"
          >
            <TableCell class="font-medium">
              {{ waterAccount.account_number }}
            </TableCell>
            <TableCell>{{ waterAccount.owner.name }}</TableCell>
            <TableCell class="text-muted-foreground">
              {{ waterAccount.meter_number }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ waterAccount.billing_category ?? '—' }}
            </TableCell>
            <TableCell
              class="tabular-nums"
              :class="{
                'text-destructive': waterAccount.balance > 0,
                'text-emerald-600 dark:text-emerald-400':
                  waterAccount.balance < 0,
                'text-muted-foreground': waterAccount.balance === 0,
              }"
            >
              {{ formatBalance(waterAccount.balance) }}
            </TableCell>
            <TableCell>
              <WaterAccountStatusBadge :status="waterAccount.status" />
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ waterAccount.connected_at ?? '—' }}
            </TableCell>
            <TableCell>
              <div class="flex items-center gap-2">
                <Button
                  v-if="hasPermission('ledger.view')"
                  variant="outline"
                  size="sm"
                  as-child
                >
                  <Link :href="statement(waterAccount.id)"> View ledger </Link>
                </Button>
                <Button
                  v-if="hasPermission('water-accounts.edit')"
                  variant="outline"
                  size="sm"
                  as-child
                >
                  <Link :href="edit(waterAccount.id)"> Edit </Link>
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableEmpty v-if="waterAccounts.data.length === 0" :colspan="8">
            No water accounts found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="waterAccounts" />
  </div>
</template>
