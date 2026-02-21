<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpDown, Plus } from '@lucide/vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
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
import { create, edit, index, show } from '@/routes/admin/ledger-accounts';
import type { DatatableFilters, Paginated } from '@/types';

type AccountType = 'asset' | 'liability' | 'equity' | 'income' | 'expense';

type LedgerAccountRow = {
  id: number;
  code: string;
  name: string;
  type: AccountType;
  is_active: boolean;
  lines_count: number;
  balance: number;
};

const props = defineProps<{
  accounts: Paginated<LedgerAccountRow>;
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
        title: 'Chart of accounts',
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

const typeLabels: Record<AccountType, string> = {
  asset: 'Asset',
  liability: 'Liability',
  equity: 'Equity',
  income: 'Income',
  expense: 'Expense',
};

const typeClasses: Record<AccountType, string> = {
  asset:
    'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
  liability:
    'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
  equity:
    'border-transparent bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300',
  income:
    'border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
  expense:
    'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

/**
 * Balances display with two decimals and a "Rs " prefix. A negative natural
 * balance is a credit against the account's normal side, shown as "CR".
 */
const formatCurrency = (value: number): string => {
  const absolute = Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return value < 0 ? `Rs ${absolute} CR` : `Rs ${absolute}`;
};
</script>

<template>
  <Head title="Chart of accounts" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Chart of accounts"
        description="Ledger accounts and their natural balances"
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
            New account
          </Link>
        </Button>
      </div>
    </div>

    <SearchFilter v-model="search" placeholder="Search by code or name..." />

    <div class="rounded-xl border">
      <Table>
        <TableHeader>
          <TableRow>
            <SortableHead
              column="code"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Code
            </SortableHead>
            <SortableHead
              column="name"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Name
            </SortableHead>
            <SortableHead
              column="type"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Type
            </SortableHead>
            <TableHead>Balance</TableHead>
            <TableHead>Entries</TableHead>
            <SortableHead
              column="is_active"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Active
            </SortableHead>
            <TableHead class="w-0">
              <span class="sr-only">Actions</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="account in accounts.data" :key="account.id">
            <TableCell class="font-medium tabular-nums">
              <Link :href="show(account.id)" class="hover:underline">
                {{ account.code }}
              </Link>
            </TableCell>
            <TableCell>{{ account.name }}</TableCell>
            <TableCell>
              <Badge variant="outline" :class="typeClasses[account.type]">
                {{ typeLabels[account.type] }}
              </Badge>
            </TableCell>
            <TableCell class="tabular-nums">
              {{ formatCurrency(account.balance) }}
            </TableCell>
            <TableCell class="tabular-nums">
              {{ account.lines_count }}
            </TableCell>
            <TableCell>
              <Badge :variant="account.is_active ? 'default' : 'secondary'">
                {{ account.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </TableCell>
            <TableCell>
              <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="show(account.id)"> View </Link>
                </Button>
                <Button
                  v-if="hasPermission('system-ledger.manage')"
                  variant="outline"
                  size="sm"
                  as-child
                >
                  <Link :href="edit(account.id)"> Edit </Link>
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableEmpty v-if="accounts.data.length === 0" :colspan="7">
            No ledger accounts found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="accounts" />
  </div>
</template>
