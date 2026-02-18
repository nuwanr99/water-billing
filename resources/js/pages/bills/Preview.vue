<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
  ArrowLeft,
  ChevronDown,
  Droplets,
  Pencil,
  ReceiptText,
} from '@lucide/vue';
import AccountSummaryCard from '@/components/meter-readings/AccountSummaryCard.vue';
import { Button } from '@/components/ui/button';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';
import MeterReaderLayout from '@/layouts/MeterReaderLayout.vue';
import { formatReading } from '@/lib/utils';
import { store } from '@/routes/bills';
import { edit, index as meterReadingsIndex } from '@/routes/meter-readings';

defineOptions({ layout: MeterReaderLayout });

type PresentedEntry = {
  id: number;
  type: string;
  date: string;
  document_number: string | null;
  description: string | null;
  amount: number;
};

type BillTier = {
  lower_units: number;
  upper_units: number | null;
  units: number;
  rate_per_unit: number;
  amount: number;
};

const props = defineProps<{
  account: {
    id: number;
    owner_name: string;
    account_number: string;
    meter_number: string;
    connection_address: string | null;
  };
  reading: {
    id: number;
    is_editable: boolean;
  };
  preview: {
    reading: {
      previous_value: number;
      current_value: number;
      consumption: number;
      reading_date: string;
      billing_month: string;
    };
    tiers: BillTier[];
    usage_charge: number;
    service_charge: number;
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
  monthLabel: string;
}>();

setLayoutProps({
  title: `Bill preview · ${props.monthLabel}`,
  backHref: meterReadingsIndex().url,
});

const entryTypeLabels: Record<string, string> = {
  water_charge: 'Water charge',
  charge: 'Charge',
  penalty: 'Penalty',
  adjustment: 'Adjustment',
  payment: 'Payment',
  reversal: 'Reversal',
};

const entryLabel = (entry: PresentedEntry): string =>
  entry.description ?? entryTypeLabels[entry.type] ?? entry.type;

const formatRs = (value: number): string =>
  `Rs ${new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(Number(value) || 0))}`;

/** Negative amounts are credits and display as "CR Rs …". */
const formatSigned = (value: number): string =>
  value < 0 ? `CR ${formatRs(value)}` : formatRs(value);

/** Slabs are (lower, upper] ranges, so non-first slabs start above their lower bound. */
const tierRange = (tier: BillTier): string =>
  tier.upper_units === null
    ? `Above ${tier.lower_units} units`
    : tier.lower_units === 0
      ? `First ${tier.upper_units} units`
      : `> ${tier.lower_units}–${tier.upper_units} units`;

const form = useForm({});

const confirmBill = (): void => {
  form.post(store(props.reading.id).url);
};
</script>

<template>
  <div class="flex flex-1 flex-col">
    <Head :title="`Bill preview — ${account.account_number}`" />

    <div class="flex flex-col gap-4 px-4 pt-3 pb-6">
      <AccountSummaryCard :account="account" />

      <div class="flex flex-col gap-3 rounded-2xl border p-4">
        <p class="text-sm font-semibold">Meter reading · {{ monthLabel }}</p>
        <div class="flex items-center justify-between gap-2 text-sm">
          <span class="text-muted-foreground">Previous reading</span>
          <span class="tabular-nums">
            {{ formatReading(preview.reading.previous_value) }}
          </span>
        </div>
        <div class="flex items-center justify-between gap-2 text-sm">
          <span class="text-muted-foreground">Current reading</span>
          <span class="tabular-nums">
            {{ formatReading(preview.reading.current_value) }}
          </span>
        </div>
        <div
          class="flex items-center justify-between gap-2 rounded-xl bg-muted/40 px-3 py-2.5"
        >
          <span class="flex items-center gap-2 text-sm font-medium">
            <Droplets class="size-4 text-primary" />
            Units consumed
          </span>
          <span class="text-lg font-semibold tabular-nums">
            {{ formatReading(preview.reading.consumption) }}
          </span>
        </div>
        <p class="text-xs text-muted-foreground">
          Read on {{ preview.reading.reading_date }}
        </p>
      </div>

      <div class="flex flex-col gap-3 rounded-2xl border p-4">
        <p class="text-sm font-semibold">This month</p>
        <div class="flex items-center justify-between gap-2 text-sm">
          <span class="text-muted-foreground">Water usage charge</span>
          <span class="tabular-nums">{{ formatRs(preview.usage_charge) }}</span>
        </div>
        <div class="flex items-center justify-between gap-2 text-sm">
          <span class="text-muted-foreground">Service charge</span>
          <span class="tabular-nums">
            {{ formatRs(preview.service_charge) }}
          </span>
        </div>
        <div
          class="flex items-center justify-between gap-2 border-t pt-3 text-sm font-semibold"
        >
          <span>This month total</span>
          <span class="tabular-nums">
            {{ formatRs(preview.summary.this_month) }}
          </span>
        </div>

        <Collapsible v-if="preview.tiers.length > 0" class="rounded-xl border">
          <CollapsibleTrigger
            class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-xs font-medium text-muted-foreground"
          >
            Usage charge breakdown
            <ChevronDown class="size-4" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div class="flex flex-col gap-1.5 border-t px-3 py-2.5">
              <div
                v-for="(tier, tierIndex) in preview.tiers"
                :key="tierIndex"
                class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
              >
                <span>
                  {{ tierRange(tier) }} · {{ formatReading(tier.units) }} ×
                  {{ formatRs(tier.rate_per_unit) }}
                </span>
                <span class="tabular-nums">{{ formatRs(tier.amount) }}</span>
              </div>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </div>

      <div class="flex flex-col gap-3 rounded-2xl border p-4">
        <p class="text-sm font-semibold">Previous balance</p>
        <div class="flex items-center justify-between gap-2 text-sm">
          <span class="text-muted-foreground">Previous due</span>
          <span class="tabular-nums">
            {{ formatSigned(preview.summary.previous_due) }}
          </span>
        </div>
        <div
          v-for="entry in preview.presented_entries"
          :key="entry.id"
          class="flex items-start justify-between gap-2 text-sm"
        >
          <span class="min-w-0 text-muted-foreground">
            {{ entryLabel(entry) }}
            <span class="text-xs">({{ entry.date }})</span>
          </span>
          <span
            class="shrink-0 tabular-nums"
            :class="
              entry.amount < 0 ? 'text-emerald-600 dark:text-emerald-400' : ''
            "
          >
            {{ formatSigned(entry.amount) }}
          </span>
        </div>
        <p
          v-if="preview.presented_entries.length === 0"
          class="text-xs text-muted-foreground"
        >
          No payments or charges since the last bill.
        </p>
        <div
          class="flex items-center justify-between gap-2 border-t pt-3 text-sm font-semibold"
        >
          <span>Balance brought forward</span>
          <span class="tabular-nums">
            {{ formatSigned(preview.summary.previous_balance) }}
          </span>
        </div>
      </div>

      <div
        class="flex flex-col items-center gap-1 rounded-2xl border bg-muted/40 p-5 text-center"
      >
        <p
          class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
        >
          Total due
        </p>
        <p class="text-3xl font-bold tabular-nums">
          {{ formatSigned(preview.summary.total_due) }}
        </p>
        <p
          v-if="preview.summary.total_due < 0"
          class="text-xs text-muted-foreground"
        >
          Account in credit — no payment required.
        </p>
      </div>
    </div>

    <div
      class="sticky bottom-0 mt-auto flex flex-col gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur"
    >
      <Button
        size="lg"
        class="h-12 w-full text-base"
        :disabled="form.processing"
        @click="confirmBill"
      >
        <ReceiptText class="size-4" />
        Confirm &amp; generate bill
      </Button>
      <Button
        v-if="reading.is_editable"
        variant="outline"
        class="w-full"
        as-child
      >
        <Link :href="edit(reading.id).url">
          <Pencil class="size-4" />
          Edit reading
        </Link>
      </Button>
      <Button variant="ghost" class="w-full" as-child>
        <Link :href="meterReadingsIndex().url">
          <ArrowLeft class="size-4" />
          Back to meter readings
        </Link>
      </Button>
    </div>
  </div>
</template>
