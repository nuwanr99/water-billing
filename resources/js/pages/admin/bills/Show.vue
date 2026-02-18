<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { index, reissue, show } from '@/routes/admin/bills';

type TariffTier = {
  lower_units: number;
  upper_units: number | null;
  units: number;
  rate_per_unit: number;
  amount: number;
};

type PresentedEntry = {
  id: number;
  type: string;
  date: string;
  document_number: string | null;
  description: string | null;
  amount: number;
};

type BillBreakdown = {
  reading: {
    previous_value: number;
    current_value: number;
    consumption: number;
    reading_date: string;
    billing_month: string;
  };
  tiers: TariffTier[];
  presented_entries: PresentedEntry[];
  summary: {
    previous_due: number;
    payments: number;
    credits: number;
    debits: number;
    this_month: number;
    previous_balance: number;
    total_due: number;
  };
};

const props = defineProps<{
  bill: {
    id: number;
    bill_number: string;
    billing_month: string;
    month_label: string;
    is_current: boolean;
    usage_charge: number;
    service_charge: number;
    monthly_charge: number;
    previous_balance: number;
    total_due: number;
    breakdown: BillBreakdown;
    status: string;
    due_date: string;
    issued_at: string;
    is_reissue: boolean;
    supersedes_bill_id: number | null;
    supersedes_bill_number: string | null;
    reissued_by_bill_id: number | null;
    reissued_by_bill_number: string | null;
    generated_by: string;
    reading_value: number;
  };
  account: {
    id: number;
    owner_name: string;
    account_number: string;
    meter_number: string;
  };
  canReissue: boolean;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Bills',
        href: index(),
      },
      {
        title: 'Detail',
        href: '#',
      },
    ],
  },
});

const statusVariants: Record<
  string,
  'default' | 'secondary' | 'destructive' | 'outline'
> = {
  generated: 'outline',
  approved: 'secondary',
  paid: 'default',
  overdue: 'destructive',
};

const statusLabels: Record<string, string> = {
  generated: 'Generated',
  approved: 'Approved',
  paid: 'Paid',
  overdue: 'Overdue',
};

const entryTypeLabels: Record<string, string> = {
  water_charge: 'Water charge',
  charge: 'Charge',
  penalty: 'Penalty',
  adjustment: 'Adjustment',
  payment: 'Payment',
  reversal: 'Reversal',
};

const formatCurrency = (amount: number): string => {
  const formatted = Math.abs(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return amount < 0 ? `Rs ${formatted} CR` : `Rs ${formatted}`;
};

const formatUnits = (units: number): string =>
  units.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

/** Slabs are (lower, upper] ranges, so non-first slabs start above their lower bound. */
const formatSlabRange = (tier: TariffTier): string =>
  tier.upper_units === null
    ? `Above ${tier.lower_units} units`
    : tier.lower_units === 0
      ? `First ${tier.upper_units} units`
      : `> ${tier.lower_units} – ${tier.upper_units} units`;

const reissueOpen = ref(false);

const reissueForm = useForm({
  reading_value: props.bill.reading_value.toFixed(2),
});

/** Guard errors keyed outside the form fields (e.g. `bill` from the service). */
const reissueGuardError = computed(
  () => (reissueForm.errors as Record<string, string | undefined>).bill,
);

const submitReissue = () => {
  reissueForm.post(reissue(props.bill.id).url, {
    preserveScroll: true,
    onSuccess: () => {
      reissueOpen.value = false;
    },
  });
};
</script>

<template>
  <Head :title="`Bill ${bill.bill_number}`" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-2">
        <Heading
          variant="small"
          :title="`Bill ${bill.bill_number}`"
          :description="`${account.owner_name} · ${account.account_number} · Meter ${account.meter_number}`"
        />
        <div class="flex flex-wrap items-center gap-2">
          <Badge :variant="statusVariants[bill.status] ?? 'outline'">
            {{ statusLabels[bill.status] ?? bill.status }}
          </Badge>
          <Badge variant="outline">{{ bill.month_label }}</Badge>
          <Link
            v-if="bill.is_reissue && bill.supersedes_bill_id"
            :href="show(bill.supersedes_bill_id).url"
          >
            <Badge variant="secondary" class="hover:underline">
              Reissue of {{ bill.supersedes_bill_number }}
            </Badge>
          </Link>
          <component
            :is="bill.reissued_by_bill_id ? Link : 'span'"
            v-if="!bill.is_current"
            :href="
              bill.reissued_by_bill_id
                ? show(bill.reissued_by_bill_id).url
                : undefined
            "
          >
            <Badge
              variant="destructive"
              :class="bill.reissued_by_bill_id ? 'hover:underline' : ''"
            >
              Superseded{{
                bill.reissued_by_bill_number
                  ? ` by ${bill.reissued_by_bill_number}`
                  : ''
              }}
            </Badge>
          </component>
        </div>
      </div>

      <Dialog v-if="canReissue" v-model:open="reissueOpen">
        <DialogTrigger as-child>
          <Button variant="outline">Reissue bill</Button>
        </DialogTrigger>
        <DialogContent>
          <form class="space-y-6" @submit.prevent="submitReissue">
            <DialogHeader class="space-y-3">
              <DialogTitle>Reissue {{ bill.bill_number }}?</DialogTitle>
              <DialogDescription>
                This bill will be superseded and a new bill generated from the
                corrected meter reading. Its ledger charge is reversed and
                reposted.
              </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
              <Label for="reading_value">Corrected reading</Label>
              <Input
                id="reading_value"
                v-model="reissueForm.reading_value"
                type="number"
                step="0.01"
                min="0"
                inputmode="decimal"
              />
              <InputError :message="reissueForm.errors.reading_value" />
              <InputError :message="reissueGuardError" />
            </div>

            <DialogFooter class="gap-2">
              <DialogClose as-child>
                <Button
                  type="button"
                  variant="secondary"
                  @click="
                    () => {
                      reissueForm.clearErrors();
                      reissueForm.reset();
                    }
                  "
                >
                  Cancel
                </Button>
              </DialogClose>

              <Button type="submit" :disabled="reissueForm.processing">
                Reissue bill
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>Statement summary</CardTitle>
        </CardHeader>
        <CardContent>
          <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-4">
              <dt class="text-muted-foreground">Previous bill total due</dt>
              <dd class="tabular-nums">
                {{ formatCurrency(bill.breakdown.summary.previous_due) }}
              </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-muted-foreground">Payments received</dt>
              <dd class="tabular-nums">
                − {{ formatCurrency(bill.breakdown.summary.payments) }}
              </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-muted-foreground">Credits &amp; adjustments</dt>
              <dd class="tabular-nums">
                − {{ formatCurrency(bill.breakdown.summary.credits) }}
              </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-muted-foreground">Other charges</dt>
              <dd class="tabular-nums">
                + {{ formatCurrency(bill.breakdown.summary.debits) }}
              </dd>
            </div>
            <div
              class="flex items-center justify-between gap-4 border-t pt-2 font-medium"
            >
              <dt>Previous balance</dt>
              <dd class="tabular-nums">
                {{ formatCurrency(bill.breakdown.summary.previous_balance) }}
              </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-muted-foreground">This month's charges</dt>
              <dd class="tabular-nums">
                + {{ formatCurrency(bill.breakdown.summary.this_month) }}
              </dd>
            </div>
            <div
              class="flex items-center justify-between gap-4 border-t pt-2 text-base font-semibold"
            >
              <dt>Total due</dt>
              <dd class="tabular-nums">{{ formatCurrency(bill.total_due) }}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Meter reading</CardTitle>
        </CardHeader>
        <CardContent>
          <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-4">
              <dt class="text-muted-foreground">Previous reading</dt>
              <dd class="tabular-nums">
                {{ formatUnits(bill.breakdown.reading.previous_value) }}
              </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-muted-foreground">Current reading</dt>
              <dd class="tabular-nums">
                {{ formatUnits(bill.breakdown.reading.current_value) }}
              </dd>
            </div>
            <div
              class="flex items-center justify-between gap-4 border-t pt-2 font-medium"
            >
              <dt>Consumption</dt>
              <dd class="tabular-nums">
                {{ formatUnits(bill.breakdown.reading.consumption) }} units
              </dd>
            </div>
            <div class="flex items-center justify-between gap-4">
              <dt class="text-muted-foreground">Reading date</dt>
              <dd>{{ bill.breakdown.reading.reading_date }}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Tariff breakdown</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="rounded-xl border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Slab</TableHead>
                <TableHead class="text-right">Units</TableHead>
                <TableHead class="text-right">Rate (Rs/unit)</TableHead>
                <TableHead class="text-right">Amount</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="(tier, tierIndex) in bill.breakdown.tiers"
                :key="tierIndex"
              >
                <TableCell>{{ formatSlabRange(tier) }}</TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatUnits(tier.units) }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ tier.rate_per_unit.toFixed(2) }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatCurrency(tier.amount) }}
                </TableCell>
              </TableRow>
              <TableRow>
                <TableCell class="text-muted-foreground" :colspan="3">
                  Usage charge
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatCurrency(bill.usage_charge) }}
                </TableCell>
              </TableRow>
              <TableRow>
                <TableCell class="text-muted-foreground" :colspan="3">
                  Monthly service charge
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatCurrency(bill.service_charge) }}
                </TableCell>
              </TableRow>
              <TableRow class="font-semibold">
                <TableCell :colspan="3">Monthly charge</TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatCurrency(bill.monthly_charge) }}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Presented charges</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="rounded-xl border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Date</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Document</TableHead>
                <TableHead>Description</TableHead>
                <TableHead class="text-right">Amount</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="entry in bill.breakdown.presented_entries"
                :key="entry.id"
              >
                <TableCell class="whitespace-nowrap">
                  {{ entry.date }}
                </TableCell>
                <TableCell>
                  <Badge variant="outline">
                    {{ entryTypeLabels[entry.type] ?? entry.type }}
                  </Badge>
                </TableCell>
                <TableCell class="text-muted-foreground">
                  {{ entry.document_number ?? '—' }}
                </TableCell>
                <TableCell class="max-w-64 truncate text-muted-foreground">
                  {{ entry.description ?? '—' }}
                </TableCell>
                <TableCell class="text-right tabular-nums">
                  {{ formatCurrency(entry.amount) }}
                </TableCell>
              </TableRow>
              <TableEmpty
                v-if="bill.breakdown.presented_entries.length === 0"
                :colspan="5"
              >
                No charges or payments were presented on this bill.
              </TableEmpty>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle>Bill details</CardTitle>
      </CardHeader>
      <CardContent>
        <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
          <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Billing month</dt>
            <dd>{{ bill.month_label }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Issued at</dt>
            <dd>{{ bill.issued_at }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Due date</dt>
            <dd>{{ bill.due_date }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Generated by</dt>
            <dd>{{ bill.generated_by }}</dd>
          </div>
        </dl>
      </CardContent>
    </Card>
  </div>
</template>
