<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
  ArrowLeft,
  CircleCheck,
  Droplets,
  MapPin,
  ShieldCheck,
  TriangleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
  account: {
    account_number: string;
    meter_number: string;
    owner_name: string;
    address: string | null;
    balance: number;
  };
  lastBill: {
    bill_number: string;
    month_label: string;
    total_due: number;
    status: string;
    due_date: string;
  } | null;
  checkoutUrl: string;
  gatewayReady: boolean;
  backUrl?: string;
}>();

const form = useForm({
  amount: props.account.balance > 0 ? props.account.balance.toFixed(2) : '',
});

const amount = computed(() => {
  const parsed = Number.parseFloat(String(form.amount));

  return Number.isFinite(parsed) ? parsed : 0;
});

const canPay = computed(
  () => props.gatewayReady && !form.processing && amount.value > 0,
);

const payFullAmount = (): void => {
  form.amount = props.account.balance.toFixed(2);
};

const submit = (): void => {
  form.post(props.checkoutUrl);
};

/**
 * Amounts render as "Rs 1,234.56" with two decimals; the sign is conveyed
 * by the surrounding Total due / CR (credit) styling rather than a minus sign.
 */
const formatAmount = (value: number): string =>
  Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

const formatRs = (value: number): string => `Rs ${formatAmount(value)}`;

const statusMeta: Record<
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
    : (statusMeta[props.lastBill.status] ?? {
        label: props.lastBill.status,
        variant: 'outline' as const,
      }),
);
</script>

<template>
  <div
    class="flex min-h-svh flex-col items-center bg-muted/40 px-4 py-10 dark:bg-background"
  >
    <Head title="Pay your water bill" />

    <div class="flex w-full max-w-md flex-col gap-6 lg:max-w-4xl">
      <div v-if="backUrl">
        <Link
          :href="backUrl"
          class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft class="size-4" />
          Back
        </Link>
      </div>

      <div
        class="flex flex-col items-center gap-2 text-center lg:flex-row lg:gap-3 lg:text-left"
      >
        <div
          class="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground lg:size-10"
        >
          <Droplets class="size-6 lg:size-5" />
        </div>
        <h1 class="text-xl font-semibold tracking-tight lg:text-2xl">
          Pay your water bill
        </h1>
      </div>

      <div
        class="flex flex-col gap-6 lg:grid lg:grid-cols-[1fr_minmax(22rem,26rem)] lg:items-start lg:gap-8"
      >
        <div class="flex flex-col gap-6">
          <Card class="py-5">
            <CardContent class="flex flex-col gap-3 px-5">
              <div
                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
              >
                <div>
                  <p class="font-semibold lg:text-lg">
                    {{ account.owner_name }}
                  </p>
                  <p class="text-sm text-muted-foreground">
                    Account {{ account.account_number }}
                  </p>
                </div>

                <div class="lg:text-right">
                  <template v-if="account.balance > 0">
                    <p class="text-sm text-muted-foreground">Total due</p>
                    <p
                      class="text-3xl font-bold tracking-tight text-red-600 tabular-nums dark:text-red-500"
                    >
                      {{ formatRs(account.balance) }}
                    </p>
                  </template>
                  <template v-else-if="account.balance < 0">
                    <p class="text-sm text-muted-foreground">
                      Account in credit
                    </p>
                    <p
                      class="text-3xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-500"
                    >
                      CR {{ formatRs(account.balance) }}
                    </p>
                  </template>
                  <template v-else>
                    <p
                      class="flex items-center gap-2 text-xl font-semibold text-emerald-600 lg:justify-end dark:text-emerald-500"
                    >
                      <CircleCheck class="size-5" />
                      Settled — nothing due
                    </p>
                  </template>
                </div>
              </div>

              <p
                v-if="account.address"
                class="flex items-start gap-2 text-sm text-muted-foreground"
              >
                <MapPin class="mt-0.5 size-4 shrink-0" />
                <span>{{ account.address }}</span>
              </p>
            </CardContent>
          </Card>

          <Card v-if="lastBill && billStatus" class="py-4">
            <CardContent class="flex items-center justify-between gap-3 px-5">
              <div class="min-w-0">
                <p class="text-xs text-muted-foreground">Last bill</p>
                <p class="truncate text-sm font-medium">
                  {{ lastBill.month_label }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                  {{ lastBill.bill_number }} · Due {{ lastBill.due_date }}
                </p>
              </div>
              <div class="flex shrink-0 flex-col items-end gap-1">
                <span class="text-sm font-semibold tabular-nums">
                  <template v-if="lastBill.total_due < 0">CR </template>
                  {{ formatRs(lastBill.total_due) }}
                </span>
                <Badge :variant="billStatus.variant">
                  {{ billStatus.label }}
                </Badge>
              </div>
            </CardContent>
          </Card>

          <p
            class="hidden items-start gap-1.5 text-xs text-muted-foreground lg:flex"
          >
            <ShieldCheck class="mt-px size-3.5 shrink-0" />
            Payments are processed securely by PayHere. You will receive a
            receipt reference after payment.
          </p>
        </div>

        <Card>
          <CardContent>
            <form class="flex flex-col gap-5" @submit.prevent="submit">
              <div
                v-if="!gatewayReady"
                class="flex items-start gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-400"
              >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>Online payments are temporarily unavailable.</p>
              </div>

              <div class="grid gap-2">
                <div class="flex items-center justify-between gap-2">
                  <Label for="amount">Amount (Rs)</Label>
                  <button
                    v-if="account.balance > 0"
                    type="button"
                    class="rounded-full bg-secondary px-3 py-1 text-xs font-medium text-secondary-foreground transition-colors hover:bg-secondary/80 disabled:pointer-events-none disabled:opacity-50"
                    :disabled="!gatewayReady || form.processing"
                    @click="payFullAmount"
                  >
                    Pay full amount
                  </button>
                </div>
                <Input
                  id="amount"
                  v-model="form.amount"
                  type="number"
                  step="0.01"
                  min="0.01"
                  inputmode="decimal"
                  required
                  placeholder="0.00"
                  class="tabular-nums"
                  :disabled="!gatewayReady || form.processing"
                />
                <InputError :message="form.errors.amount" />
              </div>

              <Button
                type="submit"
                size="lg"
                class="w-full"
                :disabled="!canPay"
              >
                <Spinner v-if="form.processing" />
                <template v-if="amount > 0">
                  Pay {{ formatRs(amount) }} securely with PayHere
                </template>
                <template v-else>Pay securely with PayHere</template>
              </Button>

              <p
                v-if="account.balance <= 0"
                class="text-center text-xs text-muted-foreground"
              >
                Nothing is due right now — any amount you enter is recorded as
                an advance and carried as credit.
              </p>
            </form>
          </CardContent>
        </Card>
      </div>

      <p
        class="flex items-start justify-center gap-1.5 text-center text-xs text-balance text-muted-foreground lg:hidden"
      >
        <ShieldCheck class="mt-px size-3.5 shrink-0" />
        Payments are processed securely by PayHere. You will receive a receipt
        reference after payment.
      </p>
    </div>
  </div>
</template>
