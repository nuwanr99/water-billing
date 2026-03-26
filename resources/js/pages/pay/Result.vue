<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { CircleCheck, CircleX, Download, RefreshCw } from '@lucide/vue';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

interface PaymentResult {
  public_token: string;
  status: 'pending' | 'completed' | 'failed';
  receipt_number: string | null;
  amount: number;
  paid_at: string;
  receipt_pdf_url: string | null;
}

interface AccountSummary {
  account_number: string;
  owner_name: string;
  balance: number;
  pay_again_url: string;
}

const props = defineProps<{
  payment: PaymentResult;
  account: AccountSummary;
}>();

/**
 * The webhook confirmation may still be in flight when the customer lands
 * back here, so while the payment is pending the page reloads its props
 * every three seconds and stops as soon as a final status arrives.
 */
const { stop } = usePoll(
  3000,
  {},
  { autoStart: props.payment.status === 'pending', keepAlive: true },
);

watch(
  () => props.payment.status,
  (status) => {
    if (status !== 'pending') {
      stop();
    }
  },
);

function formatCurrency(value: number): string {
  const absolute = Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return value < 0 ? `Rs ${absolute} CR` : `Rs ${absolute}`;
}
</script>

<template>
  <Head title="Payment status" />

  <div
    class="flex min-h-svh flex-col items-center justify-center bg-background p-4 sm:p-6"
  >
    <div class="flex w-full max-w-md flex-col gap-4">
      <!-- Pending: the webhook confirmation hasn't landed yet -->
      <Card v-if="payment.status === 'pending'" class="text-center">
        <CardContent class="flex flex-col items-center gap-4 py-8">
          <span
            class="flex size-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950"
          >
            <Spinner class="size-7 text-amber-600 dark:text-amber-400" />
          </span>
          <div class="space-y-1.5">
            <h1 class="text-lg font-semibold text-foreground">
              Confirming your payment…
            </h1>
            <p class="text-sm text-muted-foreground">
              We're waiting for the gateway to confirm. This page updates
              automatically — no need to refresh.
            </p>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ account.account_number }} · {{ account.owner_name }}
          </p>
        </CardContent>
      </Card>

      <!-- Completed: receipt issued -->
      <Card v-else-if="payment.status === 'completed'" class="text-center">
        <CardContent class="flex flex-col items-center gap-5 py-8">
          <span
            class="flex size-14 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950"
          >
            <CircleCheck
              class="size-8 text-emerald-600 dark:text-emerald-400"
            />
          </span>

          <div class="space-y-1">
            <h1 class="text-lg font-semibold text-foreground">
              Payment received
            </h1>
            <p
              class="text-3xl font-bold tracking-tight text-foreground tabular-nums"
            >
              {{ formatCurrency(payment.amount) }}
            </p>
            <p class="text-sm text-muted-foreground">
              {{ payment.paid_at }} · {{ account.account_number }} ·
              {{ account.owner_name }}
            </p>
          </div>

          <div
            class="w-full rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/50"
          >
            <p class="text-xs text-emerald-700 dark:text-emerald-400">
              Your reference
            </p>
            <p
              class="text-lg font-semibold tracking-wide text-emerald-800 dark:text-emerald-300"
            >
              {{ payment.receipt_number }}
            </p>
          </div>

          <div
            class="flex w-full items-center justify-between rounded-lg bg-muted px-4 py-3 text-sm"
          >
            <span class="text-muted-foreground">Remaining balance</span>
            <span
              class="font-semibold tabular-nums"
              :class="
                account.balance < 0
                  ? 'text-emerald-600 dark:text-emerald-400'
                  : 'text-foreground'
              "
            >
              {{ formatCurrency(account.balance) }}
            </span>
          </div>

          <div class="flex w-full flex-col gap-2">
            <Button v-if="payment.receipt_pdf_url" as-child class="w-full">
              <a :href="payment.receipt_pdf_url">
                <Download class="size-4" />
                Download receipt (PDF)
              </a>
            </Button>
            <Button as-child variant="ghost" class="w-full">
              <Link :href="account.pay_again_url">Back to payment page</Link>
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Failed / canceled: nothing was charged -->
      <Card v-else class="text-center">
        <CardContent class="flex flex-col items-center gap-5 py-8">
          <span
            class="flex size-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-950"
          >
            <CircleX class="size-8 text-red-600 dark:text-red-400" />
          </span>

          <div class="space-y-1.5">
            <h1 class="text-lg font-semibold text-foreground">
              Payment was not completed
            </h1>
            <p class="text-sm text-muted-foreground">
              The payment was canceled or failed — no receipt was issued. You
              can safely try again.
            </p>
          </div>

          <p class="text-sm text-muted-foreground">
            {{ account.account_number }} · {{ account.owner_name }}
          </p>

          <Button as-child class="w-full">
            <Link :href="account.pay_again_url">
              <RefreshCw class="size-4" />
              Try again
            </Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
