<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import {
  CreditCard,
  Download,
  Droplets,
  FileText,
  Gauge,
  MapPin,
  MessageSquareWarning,
  Plus,
  ReceiptText,
  TrendingDown,
  TrendingUp,
  TriangleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import BillStatusBadge from '@/components/BillStatusBadge.vue';
import ComplaintStatusBadge from '@/components/ComplaintStatusBadge.vue';
import JobStatusBadge from '@/components/JobStatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import WaterAccountStatusBadge from '@/components/WaterAccountStatusBadge.vue';
import type { BillStatus } from '@/lib/bills';
import type { ComplaintStatus } from '@/lib/complaints';
import type { MaintenanceJobStatus } from '@/lib/jobs';
import { formatReading } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as myBillsIndex } from '@/routes/my/bills';
import {
  create as createComplaint,
  index as complaintsIndex,
  show as showComplaint,
} from '@/routes/my/complaints';
import { index as myPaymentsIndex } from '@/routes/my/payments';
import { index as waterAccountsIndex } from '@/routes/water-accounts';
import type { WaterAccountStatus } from '@/types';

type CurrentAccount = {
  id: number;
  account_number: string;
  meter_number: string;
  connection_address: string | null;
  status: WaterAccountStatus;
  connected_at: string | null;
  balance: number;
  pay_url: string;
};

type CurrentBill = {
  id: number;
  bill_number: string;
  month_label: string;
  total_due: number;
  status: BillStatus;
  due_date: string;
  is_overdue: boolean;
  days_until_due: number;
  pdf_url: string | null;
};

type UsageEntry = {
  month: string;
  label: string;
  consumption: number;
};

type RecentPayment = {
  id: number;
  receipt_number: string;
  amount: number;
  method: 'manual' | 'payhere';
  paid_at: string;
  receipt_url: string;
};

type RecentComplaint = {
  id: number;
  complaint_number: string;
  subject: string;
  status: ComplaintStatus;
  job_status: MaintenanceJobStatus | null;
  submitted_at: string;
};

const props = defineProps<{
  currentAccount: CurrentAccount | null;
  currentBill: CurrentBill | null;
  latestReading: {
    value: number;
    consumption: number;
    previous_consumption: number | null;
    date: string;
  } | null;
  usageHistory?: UsageEntry[];
  recentPayments: RecentPayment[];
  activeAccountsCount: number;
  can: {
    view_bills: boolean;
    view_payments: boolean;
  };
  complaints: {
    can_submit: boolean;
    open_count: number;
    recent: RecentComplaint[];
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Dashboard',
        href: dashboard(),
      },
    ],
  },
});

const page = usePage();
const firstName = computed(() => page.props.auth.user.first_name);

const paymentMethodLabels: Record<RecentPayment['method'], string> = {
  manual: 'Office receipt',
  payhere: 'Online',
};

const showDueSoonBanner = computed(
  () =>
    props.currentBill !== null &&
    !props.currentBill.is_overdue &&
    props.currentBill.status !== 'paid' &&
    props.currentBill.days_until_due <= 7,
);

/**
 * Percent change of the latest consumption against the previous one.
 * Null when there is no previous reading to compare against.
 */
const usageDelta = computed(() => {
  if (
    props.latestReading === null ||
    props.latestReading.previous_consumption === null ||
    props.latestReading.previous_consumption <= 0
  ) {
    return null;
  }

  const previous = props.latestReading.previous_consumption;
  const percent =
    ((props.latestReading.consumption - previous) / previous) * 100;

  return {
    percent: Math.abs(percent).toFixed(1),
    increased: percent > 0,
    unchanged: percent === 0,
  };
});

const maxConsumption = computed(() =>
  Math.max(...(props.usageHistory ?? []).map((entry) => entry.consumption), 1),
);

const usageInsights = computed(() => {
  const history = props.usageHistory ?? [];

  if (history.length < 2) {
    return null;
  }

  const total = history.reduce((sum, entry) => sum + entry.consumption, 0);
  const highest = history.reduce((peak, entry) =>
    entry.consumption > peak.consumption ? entry : peak,
  );

  return {
    average: formatReading(total / history.length),
    highestLabel: highest.label,
    highestValue: formatReading(highest.consumption),
  };
});

const skeletonBarHeights = [45, 70, 55, 85, 60, 75, 50, 65, 80, 55, 90, 68];

/**
 * Amounts render as "Rs 1,234.56" with two decimals; the sign is conveyed
 * by the surrounding Total due / CR (credit) styling rather than a minus sign.
 */
const formatAmount = (value: number): string =>
  Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
</script>

<template>
  <Head title="Dashboard" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold tracking-tight">
          Welcome back, {{ firstName }}
        </h2>
        <p class="text-sm text-muted-foreground">
          {{
            currentAccount
              ? `Showing account ${currentAccount.account_number}`
              : 'Your water supply overview'
          }}
        </p>
      </div>
      <Button variant="outline" as-child>
        <Link :href="waterAccountsIndex()">
          <Droplets class="size-4" />
          My water accounts
        </Link>
      </Button>
    </div>

    <div
      v-if="currentAccount === null"
      class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-16 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-muted"
      >
        <Droplets class="size-6 text-muted-foreground" />
      </div>
      <div class="space-y-1">
        <p class="font-medium">No water accounts yet</p>
        <p class="max-w-sm text-sm text-muted-foreground">
          Once the society office registers a water connection under your
          membership, your bills and usage will appear here.
        </p>
      </div>
    </div>

    <template v-else>
      <Alert
        v-if="currentBill && currentBill.is_overdue"
        variant="destructive"
        class="border-destructive/50"
      >
        <TriangleAlert />
        <AlertTitle>
          Bill {{ currentBill.bill_number }} for
          {{ currentBill.month_label }} is overdue
        </AlertTitle>
        <AlertDescription
          class="flex flex-wrap items-center justify-between gap-3"
        >
          <span>
            Rs {{ formatAmount(currentBill.total_due) }} was due on
            {{ currentBill.due_date }}. Please settle it to keep your connection
            active.
          </span>
          <Button size="sm" variant="destructive" as-child>
            <Link :href="currentAccount.pay_url">
              <CreditCard class="size-4" />
              Pay online
            </Link>
          </Button>
        </AlertDescription>
      </Alert>

      <Alert
        v-else-if="currentBill && showDueSoonBanner"
        class="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
      >
        <TriangleAlert />
        <AlertTitle>
          Bill {{ currentBill.bill_number }} for
          {{ currentBill.month_label }} is
          {{
            currentBill.days_until_due === 0
              ? 'due today'
              : `due in ${currentBill.days_until_due} ${
                  currentBill.days_until_due === 1 ? 'day' : 'days'
                }`
          }}
        </AlertTitle>
        <AlertDescription class="text-amber-800/90 dark:text-amber-200/80">
          Rs {{ formatAmount(currentBill.total_due) }} is due by
          {{ currentBill.due_date }}.
        </AlertDescription>
      </Alert>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card class="gap-2">
          <CardHeader>
            <CardDescription class="flex items-center justify-between gap-2">
              Current bill
              <BillStatusBadge
                v-if="currentBill"
                :status="currentBill.status"
              />
            </CardDescription>
            <CardTitle class="text-2xl">
              <template v-if="currentBill">
                <a
                  v-if="currentBill.pdf_url"
                  :href="currentBill.pdf_url"
                  target="_blank"
                  rel="noopener"
                  class="hover:underline"
                >
                  Rs {{ formatAmount(currentBill.total_due) }}
                </a>
                <template v-else>
                  Rs {{ formatAmount(currentBill.total_due) }}
                </template>
              </template>
              <template v-else>—</template>
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-1 text-xs text-muted-foreground">
            <p>
              {{
                currentBill
                  ? `${currentBill.month_label} · Due ${currentBill.due_date}`
                  : 'No bills yet'
              }}
            </p>
            <Link
              v-if="can.view_bills"
              :href="myBillsIndex()"
              class="font-medium text-primary hover:underline"
            >
              View all bills
            </Link>
          </CardContent>
        </Card>

        <Card class="gap-2">
          <CardHeader>
            <CardDescription>Last meter reading</CardDescription>
            <CardTitle class="text-2xl">
              {{
                latestReading
                  ? `${formatReading(latestReading.value)} units`
                  : '—'
              }}
            </CardTitle>
          </CardHeader>
          <CardContent class="text-xs text-muted-foreground">
            {{
              latestReading ? `Read ${latestReading.date}` : 'No readings yet'
            }}
          </CardContent>
        </Card>

        <Card class="gap-2">
          <CardHeader>
            <CardDescription>Last recorded usage</CardDescription>
            <CardTitle class="text-2xl">
              {{
                latestReading
                  ? `${formatReading(latestReading.consumption)} units`
                  : '—'
              }}
            </CardTitle>
          </CardHeader>
          <CardContent class="text-xs text-muted-foreground">
            <div
              v-if="usageDelta && !usageDelta.unchanged"
              class="flex items-center gap-1 font-medium"
              :class="
                usageDelta.increased
                  ? 'text-red-600 dark:text-red-400'
                  : 'text-emerald-600 dark:text-emerald-400'
              "
            >
              <TrendingUp v-if="usageDelta.increased" class="size-3.5" />
              <TrendingDown v-else class="size-3.5" />
              {{ usageDelta.percent }}% vs previous reading
            </div>
            <template v-else>
              {{
                latestReading ? 'Since the previous reading' : 'No readings yet'
              }}
            </template>
          </CardContent>
        </Card>

        <Card class="gap-2">
          <CardHeader>
            <CardDescription>Active accounts</CardDescription>
            <CardTitle class="text-2xl">
              {{ activeAccountsCount }}
            </CardTitle>
          </CardHeader>
          <CardContent class="text-xs text-muted-foreground">
            Registered under your membership
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
          <CardHeader>
            <CardTitle class="text-base"> Monthly usage </CardTitle>
            <CardDescription>
              Water consumption recorded over the last 12 months
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Deferred data="usageHistory">
              <template #fallback>
                <div class="flex h-40 items-end gap-2">
                  <div
                    v-for="(height, barIndex) in skeletonBarHeights"
                    :key="barIndex"
                    class="flex-1 animate-pulse rounded-sm bg-muted"
                    :style="{ height: `${height}%` }"
                  />
                </div>
              </template>

              <template v-if="usageHistory && usageHistory.length > 0">
                <div class="flex h-40 items-end gap-2">
                  <div
                    v-for="(entry, entryIndex) in usageHistory"
                    :key="entry.month"
                    class="flex h-full flex-1 flex-col items-center justify-end gap-1.5"
                    :title="`${formatReading(entry.consumption)} units`"
                  >
                    <div
                      class="w-full rounded-sm"
                      :class="
                        entryIndex === usageHistory.length - 1
                          ? 'bg-primary/70'
                          : 'bg-primary/20'
                      "
                      :style="{
                        height: `${(entry.consumption / maxConsumption) * 100}%`,
                      }"
                    />
                    <span class="text-[10px] text-muted-foreground">
                      {{ entry.label }}
                    </span>
                  </div>
                </div>
                <p
                  v-if="usageInsights"
                  class="mt-4 border-t pt-3 text-xs text-muted-foreground"
                >
                  Avg {{ usageInsights.average }} units / mo · Highest
                  {{ usageInsights.highestLabel }} ({{
                    usageInsights.highestValue
                  }}
                  units)
                </p>
              </template>
              <div
                v-else
                class="flex h-40 flex-col items-center justify-center gap-1 text-center"
              >
                <p class="text-sm font-medium">No usage recorded</p>
                <p class="max-w-sm text-xs text-muted-foreground">
                  Usage will appear after the first meter reading
                </p>
              </div>
            </Deferred>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div class="flex items-center justify-between gap-2">
              <CardTitle class="text-base"> Account details </CardTitle>
              <WaterAccountStatusBadge :status="currentAccount.status" />
            </div>
          </CardHeader>
          <CardContent class="space-y-4 text-sm">
            <div class="flex items-center gap-3">
              <FileText class="size-4 shrink-0 text-muted-foreground" />
              <div>
                <p class="text-xs text-muted-foreground">Account number</p>
                <p class="font-medium">
                  {{ currentAccount.account_number }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <Gauge class="size-4 shrink-0 text-muted-foreground" />
              <div>
                <p class="text-xs text-muted-foreground">Meter number</p>
                <p class="font-medium">
                  {{ currentAccount.meter_number }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <MapPin class="size-4 shrink-0 text-muted-foreground" />
              <div>
                <p class="text-xs text-muted-foreground">Connection address</p>
                <p class="font-medium">
                  {{
                    currentAccount.connection_address ??
                    'Registered member address'
                  }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <Droplets class="size-4 shrink-0 text-muted-foreground" />
              <div>
                <p class="text-xs text-muted-foreground">Connected since</p>
                <p class="font-medium">
                  {{ currentAccount.connected_at ?? 'Not recorded' }}
                </p>
              </div>
            </div>
            <div class="flex items-center justify-between gap-3 border-t pt-4">
              <div>
                <p class="text-xs text-muted-foreground">Balance</p>
                <p
                  v-if="currentAccount.balance > 0"
                  class="font-semibold text-red-600 tabular-nums dark:text-red-400"
                >
                  Total due Rs {{ formatAmount(currentAccount.balance) }}
                </p>
                <p
                  v-else-if="currentAccount.balance < 0"
                  class="font-semibold text-emerald-600 tabular-nums dark:text-emerald-400"
                >
                  CR Rs {{ formatAmount(currentAccount.balance) }}
                </p>
                <p v-else class="font-medium text-muted-foreground">Settled</p>
              </div>
              <Button size="sm" as-child>
                <Link :href="currentAccount.pay_url">
                  <CreditCard class="size-4" />
                  Pay online
                </Link>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </template>

    <div class="grid gap-4" :class="can.view_payments ? 'lg:grid-cols-2' : ''">
      <Card>
        <CardHeader>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <CardTitle class="text-base">Your complaints</CardTitle>
              <CardDescription>
                Report a water supply issue and follow it to resolution
              </CardDescription>
            </div>
            <div class="flex items-center gap-2">
              <Badge v-if="complaints.open_count > 0" variant="secondary">
                {{ complaints.open_count }} open
              </Badge>
              <Button
                v-if="complaints.can_submit"
                size="sm"
                variant="outline"
                as-child
              >
                <Link :href="createComplaint()">
                  <Plus class="size-4" />
                  Report an issue
                </Link>
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <ul v-if="complaints.recent.length > 0" class="divide-y">
            <li
              v-for="complaint in complaints.recent"
              :key="complaint.id"
              class="py-3 first:pt-0 last:pb-0"
            >
              <Link
                :href="showComplaint(complaint.id)"
                class="flex items-center gap-3"
              >
                <div
                  class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted"
                >
                  <MessageSquareWarning class="size-4 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">
                    {{ complaint.subject }}
                  </p>
                  <p class="truncate text-xs text-muted-foreground">
                    {{ complaint.complaint_number }} ·
                    {{ complaint.submitted_at }}
                  </p>
                </div>
                <ComplaintStatusBadge :status="complaint.status" />
                <JobStatusBadge
                  v-if="complaint.job_status"
                  :status="complaint.job_status"
                />
              </Link>
            </li>
          </ul>
          <div v-else class="flex flex-col items-center gap-1 py-6 text-center">
            <p class="text-sm font-medium">No complaints yet</p>
            <p class="max-w-sm text-xs text-muted-foreground">
              If something is wrong with your water supply, let the society
              office know and track the fix here.
            </p>
          </div>
        </CardContent>
        <CardContent v-if="complaints.recent.length > 0" class="border-t pt-4">
          <Link
            :href="complaintsIndex()"
            class="text-sm font-medium text-primary hover:underline"
          >
            View all complaints
          </Link>
        </CardContent>
      </Card>

      <Card v-if="can.view_payments">
        <CardHeader>
          <CardTitle class="text-base">Recent payments</CardTitle>
          <CardDescription>
            Your latest receipts across all accounts
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ul v-if="recentPayments.length > 0" class="divide-y">
            <li
              v-for="payment in recentPayments"
              :key="payment.id"
              class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
            >
              <div
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted"
              >
                <ReceiptText class="size-4 text-muted-foreground" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                  {{ payment.receipt_number }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                  {{ payment.paid_at }} ·
                  {{ paymentMethodLabels[payment.method] }}
                </p>
              </div>
              <span class="text-sm font-medium tabular-nums">
                Rs {{ formatAmount(payment.amount) }}
              </span>
              <a
                :href="payment.receipt_url"
                target="_blank"
                rel="noopener"
                class="text-muted-foreground transition-colors hover:text-foreground"
                :aria-label="`Download receipt ${payment.receipt_number}`"
              >
                <Download class="size-4" />
              </a>
            </li>
          </ul>
          <div v-else class="flex flex-col items-center gap-1 py-6 text-center">
            <p class="text-sm font-medium">No payments yet</p>
            <p class="max-w-sm text-xs text-muted-foreground">
              Receipts will appear here once a bill is paid at the office or
              online.
            </p>
          </div>
        </CardContent>
        <CardContent v-if="recentPayments.length > 0" class="border-t pt-4">
          <Link
            :href="myPaymentsIndex()"
            class="text-sm font-medium text-primary hover:underline"
          >
            View all payments
          </Link>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
