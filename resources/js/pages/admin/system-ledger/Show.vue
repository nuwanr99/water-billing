<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/admin/system-ledger';

type EntryLine = {
  id: number;
  account_code: string;
  account_name: string;
  debit: number | null;
  credit: number | null;
  description: string | null;
};

defineProps<{
  entry: {
    id: number;
    reference_number: string;
    entry_date: string;
    description: string;
    source_type: string | null;
    total_debit: number;
    total_credit: number;
    is_posted: boolean;
    created_at: string | null;
    lines: EntryLine[];
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'System ledger',
        href: index(),
      },
      {
        title: 'Detail',
        href: '#',
      },
    ],
  },
});

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
  <Head :title="`Journal ${entry.reference_number}`" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-2">
        <Heading
          variant="small"
          :title="`Journal ${entry.reference_number}`"
          :description="entry.description"
        />
        <div class="flex flex-wrap items-center gap-2">
          <Badge v-if="entry.source_type" variant="outline" class="gap-1.5">
            <span
              class="size-1.5 rounded-full"
              :class="
                sourceDotClasses[entry.source_type] ?? 'bg-muted-foreground'
              "
            />
            {{ sourceLabels[entry.source_type] ?? entry.source_type }}
          </Badge>
          <Badge :variant="entry.is_posted ? 'default' : 'outline'">
            {{ entry.is_posted ? 'Posted' : 'Draft' }}
          </Badge>
        </div>
      </div>
      <Button variant="outline" as-child>
        <Link :href="index()">
          <ArrowLeft class="size-4" />
          Back to journal
        </Link>
      </Button>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Entry details</CardTitle>
      </CardHeader>
      <CardContent>
        <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
          <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Reference</dt>
            <dd class="font-medium">{{ entry.reference_number }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Entry date</dt>
            <dd>{{ entry.entry_date }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Recorded at</dt>
            <dd>{{ entry.created_at ?? '—' }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Source</dt>
            <dd>
              {{
                entry.source_type
                  ? (sourceLabels[entry.source_type] ?? entry.source_type)
                  : '—'
              }}
            </dd>
          </div>
        </dl>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Lines</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="rounded-xl border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Account</TableHead>
                <TableHead class="text-right">Debit</TableHead>
                <TableHead class="text-right">Credit</TableHead>
                <TableHead>Line note</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="line in entry.lines" :key="line.id">
                <TableCell class="font-medium">
                  {{ line.account_code }} — {{ line.account_name }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ line.debit !== null ? formatCurrency(line.debit) : '' }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ line.credit !== null ? formatCurrency(line.credit) : '' }}
                </TableCell>
                <TableCell class="max-w-64 truncate text-muted-foreground">
                  {{ line.description ?? '—' }}
                </TableCell>
              </TableRow>
              <TableRow class="font-semibold">
                <TableCell>Totals</TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatCurrency(entry.total_debit) }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatCurrency(entry.total_credit) }}
                </TableCell>
                <TableCell />
              </TableRow>
            </TableBody>
          </Table>
        </div>
        <p class="mt-4 text-sm text-muted-foreground">
          Posted journals are immutable — corrections are posted as reversing
          entries.
        </p>
      </CardContent>
    </Card>
  </div>
</template>
