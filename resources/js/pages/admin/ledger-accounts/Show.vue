<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpDown, Pencil } from '@lucide/vue';
import TransferDialog from '@/components/admin/TransferDialog.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/composables/usePermission';
import { edit, index } from '@/routes/admin/ledger-accounts';
import { show as showEntry } from '@/routes/admin/system-ledger';
import type { Paginated } from '@/types';

type AccountType = 'asset' | 'liability' | 'equity' | 'income' | 'expense';

type LedgerLine = {
  id: number;
  entry_id: number;
  reference_number: string;
  date: string;
  description: string | null;
  source_type: string | null;
  debit: number | null;
  credit: number | null;
  balance: number;
};

const props = defineProps<{
  account: {
    id: number;
    code: string;
    name: string;
    type: AccountType;
    is_active: boolean;
    balance: number;
    total_debits: number;
    total_credits: number;
  };
  lines: Paginated<LedgerLine>;
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
      {
        title: 'Ledger',
        href: '#',
      },
    ],
  },
});

const { hasPermission } = usePermission();

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

const pageTitle = `${props.account.code} — ${props.account.name}`;
</script>

<template>
  <Head :title="pageTitle" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-2">
        <Heading
          variant="small"
          :title="pageTitle"
          description="Every journal line that touched this account, with running balances"
        />
        <div class="flex flex-wrap items-center gap-2">
          <Badge variant="outline" :class="typeClasses[account.type]">
            {{ typeLabels[account.type] }}
          </Badge>
          <Badge v-if="!account.is_active" variant="secondary">
            Inactive
          </Badge>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <Button v-if="hasPermission('system-ledger.manage')" as-child>
          <Link :href="edit(account.id)">
            <Pencil class="size-4" />
            Edit account
          </Link>
        </Button>
        <TransferDialog
          v-if="hasPermission('system-ledger.manage')"
          :accounts="transferAccounts"
          :preselected-from-id="account.id"
        >
          <Button variant="outline">
            <ArrowUpDown class="size-4" />
            Transfer
          </Button>
        </TransferDialog>
        <Button variant="outline" as-child>
          <Link :href="index()">
            <ArrowLeft class="size-4" />
            Back to accounts
          </Link>
        </Button>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
      <Card>
        <CardContent>
          <p class="text-sm text-muted-foreground">Balance</p>
          <p
            class="text-3xl font-semibold tracking-tight tabular-nums"
            :class="
              account.balance < 0 && 'text-emerald-600 dark:text-emerald-400'
            "
          >
            {{ formatCurrency(account.balance) }}
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardContent>
          <p class="text-sm text-muted-foreground">Total debits</p>
          <p class="text-3xl font-semibold tracking-tight tabular-nums">
            {{ formatCurrency(account.total_debits) }}
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardContent>
          <p class="text-sm text-muted-foreground">Total credits</p>
          <p class="text-3xl font-semibold tracking-tight tabular-nums">
            {{ formatCurrency(account.total_credits) }}
          </p>
        </CardContent>
      </Card>
    </div>

    <div class="rounded-xl border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Date</TableHead>
            <TableHead>Journal</TableHead>
            <TableHead>Description</TableHead>
            <TableHead class="text-right">Debit</TableHead>
            <TableHead class="text-right">Credit</TableHead>
            <TableHead class="text-right">Balance</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="line in lines.data" :key="line.id">
            <TableCell class="whitespace-nowrap">{{ line.date }}</TableCell>
            <TableCell>
              <div class="flex items-center gap-2">
                <Link
                  :href="showEntry(line.entry_id)"
                  class="font-medium hover:underline"
                >
                  {{ line.reference_number }}
                </Link>
                <Badge
                  v-if="line.source_type"
                  variant="outline"
                  class="gap-1.5"
                >
                  <span
                    class="size-1.5 rounded-full"
                    :class="
                      sourceDotClasses[line.source_type] ??
                      'bg-muted-foreground'
                    "
                  />
                  {{ sourceLabels[line.source_type] ?? line.source_type }}
                </Badge>
              </div>
            </TableCell>
            <TableCell class="max-w-64 truncate text-muted-foreground">
              {{ line.description ?? '—' }}
            </TableCell>
            <TableCell class="text-right whitespace-nowrap tabular-nums">
              {{ line.debit !== null ? formatCurrency(line.debit) : '—' }}
            </TableCell>
            <TableCell class="text-right whitespace-nowrap tabular-nums">
              {{ line.credit !== null ? formatCurrency(line.credit) : '—' }}
            </TableCell>
            <TableCell
              class="text-right whitespace-nowrap tabular-nums"
              :class="
                line.balance < 0 && 'text-emerald-600 dark:text-emerald-400'
              "
            >
              {{ formatCurrency(line.balance) }}
            </TableCell>
          </TableRow>
          <TableEmpty v-if="lines.data.length === 0" :colspan="6">
            No journal entries touch this account yet.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="lines" />
  </div>
</template>
