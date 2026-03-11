<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, CheckCircle2, ChevronDown, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import AccountSummaryCard from '@/components/meter-readings/AccountSummaryCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { collect, index } from '@/routes/admin/payments';
import { store } from '@/routes/admin/payments/collect';

type LedgerEntry = {
  id: number;
  type: string;
  date: string;
  description: string | null;
  amount: number;
  running_balance: number;
};

const props = defineProps<{
  account: {
    id: number;
    owner_name: string;
    account_number: string;
    meter_number: string;
    balance: number;
  };
  lastBill: {
    bill_number: string;
    month_label: string;
    monthly_charge: number;
    total_due: number;
    status: string;
    due_date: string;
  } | null;
  lastPayment: {
    receipt_number: string;
    amount: number;
    paid_at: string;
  } | null;
  recentEntries: LedgerEntry[];
  destinations: { id: number; code: string; name: string }[];
}>();

setLayoutProps({
  breadcrumbs: [
    {
      title: 'Payments',
      href: index(),
    },
    {
      title: 'Collect',
      href: collect(),
    },
    {
      title: props.account.account_number,
      href: '#',
    },
  ],
});

const entryTypeLabels: Record<string, string> = {
  water_charge: 'Water charge',
  charge: 'Charge',
  penalty: 'Penalty',
  adjustment: 'Adjustment',
  payment: 'Payment',
  reversal: 'Reversal',
};

const entryLabel = (entry: LedgerEntry): string =>
  entry.description ?? entryTypeLabels[entry.type] ?? entry.type;

const formatRs = (value: number): string =>
  `Rs ${new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(Number(value) || 0))}`;

/** Negative amounts are credits and display as "CR Rs …". */
const formatSigned = (value: number): string =>
  value < 0 ? `CR ${formatRs(value)}` : formatRs(value);

const billStatusMeta: Record<
  string,
  {
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
  }
> = {
  generated: { label: 'Generated', variant: 'secondary' },
  approved: { label: 'Approved', variant: 'default' },
  paid: { label: 'Paid', variant: 'default' },
  overdue: { label: 'Overdue', variant: 'destructive' },
};

const billStatus = computed(() =>
  props.lastBill === null
    ? null
    : (billStatusMeta[props.lastBill.status] ?? {
        label: props.lastBill.status,
        variant: 'outline' as const,
      }),
);

const todayIso = new Date().toLocaleDateString('en-CA');

const form = useForm<{
  amount: string;
  destination_account_id: number | null;
  reference: string;
  paid_at: string;
  attachment: File | null;
}>({
  amount: props.account.balance > 0 ? props.account.balance.toFixed(2) : '',
  destination_account_id: props.destinations[0]?.id ?? null,
  reference: '',
  paid_at: todayIso,
  attachment: null,
});

const attachmentInput = ref<HTMLInputElement | null>(null);

const onAttachmentChange = (event: Event): void => {
  const files = (event.target as HTMLInputElement).files;

  form.attachment = files?.[0] ?? null;
};

const clearAttachment = (): void => {
  form.attachment = null;

  if (attachmentInput.value) {
    attachmentInput.value.value = '';
  }
};

const amountValue = computed(() => Number(form.amount));

const isSubmittable = computed(
  () => Number.isFinite(amountValue.value) && amountValue.value > 0,
);

const submit = (): void => {
  form.post(store(props.account.id).url);
};
</script>

<template>
  <Head :title="`Collect payment — ${account.account_number}`" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Collect payment"
        :description="`${account.account_number} — ${account.owner_name}`"
      />
      <Button variant="outline" as-child>
        <Link :href="collect()">
          <ArrowLeft class="size-4" />
          Back to collection
        </Link>
      </Button>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
      <div class="flex flex-col gap-4">
        <AccountSummaryCard :account="account" />

        <div
          class="flex flex-col items-center gap-1 rounded-2xl border bg-muted/40 p-5 text-center"
        >
          <p
            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
          >
            Total due
          </p>
          <p
            class="text-3xl font-bold tabular-nums"
            :class="
              account.balance < 0
                ? 'text-emerald-600 dark:text-emerald-400'
                : ''
            "
          >
            {{ formatSigned(account.balance) }}
          </p>
          <p
            v-if="account.balance < 0"
            class="text-xs text-emerald-600 dark:text-emerald-400"
          >
            Credit in member's favour — no payment required.
          </p>
          <p
            v-else-if="account.balance === 0"
            class="flex items-center gap-1 text-xs text-muted-foreground"
          >
            <CheckCircle2 class="size-3.5" />
            Settled — nothing outstanding.
          </p>
        </div>

        <div class="flex flex-col gap-3 rounded-2xl border p-4">
          <div class="flex items-center justify-between gap-2">
            <p class="text-sm font-semibold">Last bill</p>
            <Badge
              v-if="lastBill && billStatus"
              :variant="billStatus.variant"
              class="shrink-0"
            >
              {{ billStatus.label }}
            </Badge>
          </div>
          <template v-if="lastBill">
            <div class="flex items-center justify-between gap-2 text-sm">
              <span class="text-muted-foreground">Month</span>
              <span>{{ lastBill.month_label }}</span>
            </div>
            <div class="flex items-center justify-between gap-2 text-sm">
              <span class="text-muted-foreground">Bill number</span>
              <span class="tabular-nums">{{ lastBill.bill_number }}</span>
            </div>
            <div class="flex items-center justify-between gap-2 text-sm">
              <span class="text-muted-foreground">Monthly charge</span>
              <span class="tabular-nums">
                {{ formatRs(lastBill.monthly_charge) }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-2 text-sm">
              <span class="text-muted-foreground">Total due</span>
              <span class="font-medium tabular-nums">
                {{ formatSigned(lastBill.total_due) }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-2 text-sm">
              <span class="text-muted-foreground">Due date</span>
              <span>{{ lastBill.due_date }}</span>
            </div>
          </template>
          <p v-else class="text-xs text-muted-foreground">No bills yet.</p>
        </div>

        <div class="flex flex-col gap-3 rounded-2xl border p-4">
          <p class="text-sm font-semibold">Last payment</p>
          <template v-if="lastPayment">
            <div class="flex items-center justify-between gap-2 text-sm">
              <span class="text-muted-foreground">Receipt number</span>
              <span class="tabular-nums">{{ lastPayment.receipt_number }}</span>
            </div>
            <div class="flex items-center justify-between gap-2 text-sm">
              <span class="text-muted-foreground">Amount</span>
              <span class="tabular-nums">
                {{ formatRs(lastPayment.amount) }}
              </span>
            </div>
            <div class="flex items-center justify-between gap-2 text-sm">
              <span class="text-muted-foreground">Paid on</span>
              <span>{{ lastPayment.paid_at }}</span>
            </div>
          </template>
          <p v-else class="text-xs text-muted-foreground">No payments yet.</p>
        </div>

        <Collapsible v-if="recentEntries.length > 0" class="rounded-2xl border">
          <CollapsibleTrigger
            class="flex w-full items-center justify-between gap-2 p-4 text-left text-sm font-semibold"
          >
            Recent activity
            <ChevronDown class="size-4 text-muted-foreground" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div class="flex flex-col gap-3 border-t p-4">
              <div
                v-for="entry in recentEntries"
                :key="entry.id"
                class="flex items-start justify-between gap-2 text-sm"
              >
                <span class="min-w-0 text-muted-foreground">
                  {{ entryLabel(entry) }}
                  <span class="text-xs">({{ entry.date }})</span>
                </span>
                <span class="flex shrink-0 flex-col items-end">
                  <span
                    class="tabular-nums"
                    :class="
                      entry.amount < 0
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : ''
                    "
                  >
                    {{ formatSigned(entry.amount) }}
                  </span>
                  <span class="text-xs text-muted-foreground tabular-nums">
                    Bal {{ formatSigned(entry.running_balance) }}
                  </span>
                </span>
              </div>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </div>

      <form
        class="flex flex-col gap-4 rounded-2xl border p-4"
        @submit.prevent="submit"
      >
        <p class="text-sm font-semibold">Record a payment</p>

        <div class="grid gap-2">
          <Label for="amount">Amount (Rs)</Label>
          <Input
            id="amount"
            v-model="form.amount"
            type="number"
            inputmode="decimal"
            step="0.01"
            min="0.01"
            placeholder="0.00"
            required
            class="tabular-nums"
            :aria-invalid="Boolean(form.errors.amount) || undefined"
          />
          <InputError :message="form.errors.amount" />
        </div>

        <div class="grid gap-2">
          <Label for="destination_account_id">Paid into</Label>
          <Select v-model="form.destination_account_id">
            <SelectTrigger
              id="destination_account_id"
              class="w-full"
              :aria-invalid="
                Boolean(form.errors.destination_account_id) || undefined
              "
            >
              <SelectValue placeholder="Select a cash or bank account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="destination in destinations"
                :key="destination.id"
                :value="destination.id"
              >
                {{ destination.code }} — {{ destination.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="form.errors.destination_account_id" />
        </div>

        <div class="grid gap-2">
          <Label for="reference">Reference (optional)</Label>
          <Input
            id="reference"
            v-model="form.reference"
            type="text"
            maxlength="100"
            placeholder="Bank slip number or note"
            :aria-invalid="Boolean(form.errors.reference) || undefined"
          />
          <InputError :message="form.errors.reference" />
        </div>

        <div class="grid gap-2">
          <Label for="attachment">Slip / attachment (optional)</Label>
          <input
            id="attachment"
            ref="attachmentInput"
            type="file"
            accept="image/*,.pdf"
            class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40"
            :aria-invalid="Boolean(form.errors.attachment) || undefined"
            @change="onAttachmentChange"
          />
          <div
            v-if="form.attachment"
            class="flex items-center justify-between gap-2 rounded-md border bg-muted/40 px-3 py-1.5 text-sm"
          >
            <span class="min-w-0 truncate">{{ form.attachment.name }}</span>
            <button
              type="button"
              class="shrink-0 rounded-sm text-muted-foreground transition-colors hover:text-foreground"
              aria-label="Remove attachment"
              @click="clearAttachment"
            >
              <X class="size-4" />
            </button>
          </div>
          <p class="text-xs text-muted-foreground">
            Optional — photo of the bank slip or reference document.
          </p>
          <InputError :message="form.errors.attachment" />
        </div>

        <div class="grid gap-2">
          <Label for="paid_at">Payment date</Label>
          <Input
            id="paid_at"
            v-model="form.paid_at"
            type="date"
            :max="todayIso"
            required
            :aria-invalid="Boolean(form.errors.paid_at) || undefined"
          />
          <InputError :message="form.errors.paid_at" />
        </div>

        <div class="mt-2 flex flex-col gap-2 border-t pt-4">
          <Button
            type="submit"
            size="lg"
            class="h-12 w-full text-base"
            :disabled="form.processing || !isSubmittable"
          >
            <Banknote class="size-4" />
            <template v-if="isSubmittable">
              Record payment · {{ formatRs(amountValue) }}
            </template>
            <template v-else>Record payment</template>
          </Button>
        </div>
      </form>
    </div>
  </div>
</template>
