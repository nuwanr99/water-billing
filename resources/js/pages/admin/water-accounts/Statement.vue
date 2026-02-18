<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
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
import { index } from '@/routes/admin/water-accounts';
import { create as createCharge } from '@/routes/admin/water-accounts/charges';
import type { Paginated } from '@/types';

type LedgerEntryType =
  'water_charge' | 'charge' | 'penalty' | 'adjustment' | 'payment' | 'reversal';

type LedgerEntry = {
  id: number;
  type: LedgerEntryType;
  date: string;
  billing_month: string | null;
  document_number: string | null;
  description: string | null;
  amount: number;
  running_balance: number;
  recorded_by: string | null;
};

const props = defineProps<{
  account: {
    id: number;
    owner_name: string;
    account_number: string;
    meter_number: string;
    balance: number;
  };
  entries: Paginated<LedgerEntry>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Water accounts',
        href: index(),
      },
      {
        title: 'Statement',
        href: '#',
      },
    ],
  },
});

const { hasPermission } = usePermission();

const typeLabels: Record<LedgerEntryType, string> = {
  water_charge: 'Water charge',
  charge: 'Charge',
  penalty: 'Penalty',
  adjustment: 'Adjustment',
  payment: 'Payment',
  reversal: 'Reversal',
};

const typeClasses: Record<LedgerEntryType, string> = {
  water_charge:
    'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
  charge:
    'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
  penalty:
    'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
  adjustment:
    'border-transparent bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300',
  payment:
    'border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
  reversal:
    'border-transparent bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
};

/**
 * Currency displays with two decimals and a "Rs " prefix. Negative values
 * are credit in the member's favour, shown as "CR" amounts.
 */
const formatCurrency = (value: number): string => {
  const absolute = Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return value < 0 ? `Rs ${absolute} CR` : `Rs ${absolute}`;
};

const formatSignedAmount = (value: number): string =>
  value > 0 ? `+${formatCurrency(value)}` : formatCurrency(value);

const pageTitle = `Statement ${props.account.account_number}`;
</script>

<template>
  <Head :title="pageTitle" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Account statement"
        description="Every financial event on the account with running balances"
      />
      <Button v-if="hasPermission('ledger.record-charge')" as-child>
        <Link :href="createCharge(account.id)">
          <Plus class="size-4" />
          Add charge
        </Link>
      </Button>
    </div>

    <Card>
      <CardContent class="flex flex-wrap items-end justify-between gap-6">
        <dl class="grid grid-cols-1 gap-x-10 gap-y-4 sm:grid-cols-3">
          <div>
            <dt class="text-sm text-muted-foreground">Member</dt>
            <dd class="font-medium">{{ account.owner_name }}</dd>
          </div>
          <div>
            <dt class="text-sm text-muted-foreground">Account</dt>
            <dd class="font-medium">{{ account.account_number }}</dd>
          </div>
          <div>
            <dt class="text-sm text-muted-foreground">Meter</dt>
            <dd class="font-medium">{{ account.meter_number }}</dd>
          </div>
        </dl>
        <div class="text-right">
          <p class="text-sm text-muted-foreground">Current balance</p>
          <p
            class="text-3xl font-semibold tracking-tight"
            :class="
              account.balance < 0 && 'text-emerald-600 dark:text-emerald-400'
            "
          >
            {{ formatCurrency(account.balance) }}
          </p>
          <p v-if="account.balance < 0" class="text-xs text-muted-foreground">
            Credit in member's favour
          </p>
        </div>
      </CardContent>
    </Card>

    <div class="rounded-xl border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Date</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Document #</TableHead>
            <TableHead>Description</TableHead>
            <TableHead class="text-right">Amount</TableHead>
            <TableHead class="text-right">Balance</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="entry in entries.data" :key="entry.id">
            <TableCell class="whitespace-nowrap">
              <div>{{ entry.date }}</div>
              <div
                v-if="entry.billing_month"
                class="text-xs text-muted-foreground"
              >
                {{ entry.billing_month }}
              </div>
            </TableCell>
            <TableCell>
              <Badge variant="outline" :class="typeClasses[entry.type]">
                {{ typeLabels[entry.type] }}
              </Badge>
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ entry.document_number ?? '—' }}
            </TableCell>
            <TableCell>
              <div>{{ entry.description ?? '—' }}</div>
              <div
                v-if="entry.recorded_by"
                class="text-xs text-muted-foreground"
              >
                Recorded by {{ entry.recorded_by }}
              </div>
            </TableCell>
            <TableCell
              class="text-right font-medium whitespace-nowrap tabular-nums"
              :class="
                entry.amount < 0 && 'text-emerald-600 dark:text-emerald-400'
              "
            >
              {{ formatSignedAmount(entry.amount) }}
            </TableCell>
            <TableCell
              class="text-right whitespace-nowrap tabular-nums"
              :class="
                entry.running_balance < 0 &&
                'text-emerald-600 dark:text-emerald-400'
              "
            >
              {{ formatCurrency(entry.running_balance) }}
            </TableCell>
          </TableRow>
          <TableEmpty v-if="entries.data.length === 0" :colspan="6">
            No ledger entries yet.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="entries" />
  </div>
</template>
