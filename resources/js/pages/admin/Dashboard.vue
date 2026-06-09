<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
  Boxes,
  Droplets,
  Gauge,
  MessageSquareWarning,
  ReceiptText,
  TriangleAlert,
  Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import ComplaintStatusBadge from '@/components/ComplaintStatusBadge.vue';
import JobStatusBadge from '@/components/JobStatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import type { ComplaintStatus } from '@/lib/complaints';
import type { MaintenanceJobStatus } from '@/lib/jobs';
import { dashboard } from '@/routes/admin';
import { index as billsIndex } from '@/routes/admin/bills';
import {
  index as complaintsIndex,
  show as showComplaint,
} from '@/routes/admin/complaints';
import { index as expensesIndex } from '@/routes/admin/expenses';
import {
  index as inventoryIndex,
  show as showInventoryItem,
} from '@/routes/admin/inventory';
import {
  index as jobsIndex,
  show as showJob,
} from '@/routes/admin/maintenance-jobs';
import { index as paymentsIndex } from '@/routes/admin/payments';
import { index as waterAccountsIndex } from '@/routes/admin/water-accounts';
import { index as meterReadingsIndex } from '@/routes/meter-readings';
import { index as myJobsIndex, show as showMyJob } from '@/routes/my-jobs';

type Billing = {
  month_total: number;
  month_count: number;
  unpaid_count: number;
  overdue_count: number;
};

type RecentPayment = {
  id: number;
  receipt_number: string | null;
  account_number: string;
  amount: number;
  method: 'manual' | 'payhere';
  paid_at: string;
  receipt_url: string;
};

type Collections = {
  month_total: number;
  today_total: number;
  recent: RecentPayment[];
};

type RecentComplaint = {
  id: number;
  complaint_number: string;
  subject: string;
  status: ComplaintStatus;
  member_name: string;
  submitted_at: string;
};

type UpcomingJob = {
  id: number;
  job_number: string;
  title: string;
  status: MaintenanceJobStatus;
  scheduled_date: string;
};

type OwnJob = UpcomingJob & {
  complaint_number: string | null;
};

type LowStockItem = {
  id: number;
  name: string;
  quantity_in_stock: number;
  unit: string;
  reorder_level: number;
};

const props = defineProps<{
  monthLabel: string;
  billing: Billing | null;
  collections: Collections | null;
  outstanding: { total_due: number; accounts_in_arrears: number } | null;
  accounts: { active: number; inactive: number } | null;
  readings: { recorded: number; active_accounts: number } | null;
  complaints: {
    open_count: number;
    in_progress_count: number;
    recent: RecentComplaint[];
  } | null;
  maintenanceJobs: {
    open_count: number;
    due_count: number;
    upcoming: UpcomingJob[];
  } | null;
  myJobs: { open_count: number; open: OwnJob[] } | null;
  expenses: { month_total: number; month_count: number } | null;
  inventory: {
    low_stock_count: number;
    low_stock_items: LowStockItem[];
  } | null;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Admin dashboard',
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

const hasOwnOpenJobs = computed(
  () => props.myJobs !== null && props.myJobs.open.length > 0,
);

const readingsProgress = computed(() => {
  if (props.readings === null || props.readings.active_accounts === 0) {
    return 0;
  }

  return Math.min(
    100,
    Math.round(
      (props.readings.recorded / props.readings.active_accounts) * 100,
    ),
  );
});

/**
 * Amounts render as "Rs 1,234.56" with two decimals; negative values are
 * conveyed by the surrounding styling rather than a minus sign.
 */
const formatAmount = (value: number): string =>
  Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
</script>

<template>
  <Head title="Admin dashboard" />

  <div class="flex flex-col gap-6 p-4">
    <div>
      <h2 class="text-xl font-semibold tracking-tight">
        Welcome back, {{ firstName }}
      </h2>
      <p class="text-sm text-muted-foreground">
        Society operations overview for {{ monthLabel }}
      </p>
    </div>

    <Alert
      v-if="billing && billing.overdue_count > 0"
      variant="destructive"
      class="border-destructive/50"
    >
      <TriangleAlert />
      <AlertTitle>
        {{ billing.overdue_count }}
        {{ billing.overdue_count === 1 ? 'bill is' : 'bills are' }} past their
        due date
      </AlertTitle>
      <AlertDescription
        class="flex flex-wrap items-center justify-between gap-3"
      >
        <span>
          Unpaid bills keep member connections at risk of disconnection.
        </span>
        <Button size="sm" variant="destructive" as-child>
          <Link :href="billsIndex()">Review bills</Link>
        </Button>
      </AlertDescription>
    </Alert>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <Card v-if="billing" class="gap-2">
        <CardHeader>
          <CardDescription>Billed this month</CardDescription>
          <CardTitle class="text-2xl tabular-nums">
            Rs {{ formatAmount(billing.month_total) }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-1 text-xs text-muted-foreground">
          <p>
            {{ billing.month_count }}
            {{ billing.month_count === 1 ? 'bill' : 'bills' }} ·
            {{ billing.unpaid_count }} unpaid
          </p>
          <Link
            :href="billsIndex()"
            class="font-medium text-primary hover:underline"
          >
            View all bills
          </Link>
        </CardContent>
      </Card>

      <Card v-if="collections" class="gap-2">
        <CardHeader>
          <CardDescription>Collected this month</CardDescription>
          <CardTitle class="text-2xl tabular-nums">
            Rs {{ formatAmount(collections.month_total) }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-1 text-xs text-muted-foreground">
          <p>Rs {{ formatAmount(collections.today_total) }} today</p>
          <Link
            :href="paymentsIndex()"
            class="font-medium text-primary hover:underline"
          >
            View all payments
          </Link>
        </CardContent>
      </Card>

      <Card v-if="outstanding" class="gap-2">
        <CardHeader>
          <CardDescription>Outstanding dues</CardDescription>
          <CardTitle
            class="text-2xl tabular-nums"
            :class="
              outstanding.total_due > 0 ? 'text-red-600 dark:text-red-400' : ''
            "
          >
            Rs {{ formatAmount(outstanding.total_due) }}
          </CardTitle>
        </CardHeader>
        <CardContent class="text-xs text-muted-foreground">
          {{ outstanding.accounts_in_arrears }}
          {{ outstanding.accounts_in_arrears === 1 ? 'account' : 'accounts' }}
          in arrears
        </CardContent>
      </Card>

      <Card v-if="accounts" class="gap-2">
        <CardHeader>
          <CardDescription>Active connections</CardDescription>
          <CardTitle class="text-2xl tabular-nums">
            {{ accounts.active }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-1 text-xs text-muted-foreground">
          <p>{{ accounts.inactive }} inactive</p>
          <Link
            :href="waterAccountsIndex()"
            class="font-medium text-primary hover:underline"
          >
            View water accounts
          </Link>
        </CardContent>
      </Card>

      <Card v-if="readings" class="gap-2">
        <CardHeader>
          <CardDescription class="flex items-center gap-2">
            <Gauge class="size-3.5" />
            Readings this month
          </CardDescription>
          <CardTitle class="text-2xl tabular-nums">
            {{ readings.recorded }} / {{ readings.active_accounts }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-xs text-muted-foreground">
          <div class="h-1.5 overflow-hidden rounded-full bg-muted">
            <div
              class="h-full rounded-full bg-primary/70"
              :style="{ width: `${readingsProgress}%` }"
            />
          </div>
          <Link
            :href="meterReadingsIndex()"
            class="font-medium text-primary hover:underline"
          >
            Record readings
          </Link>
        </CardContent>
      </Card>

      <Card v-if="expenses" class="gap-2">
        <CardHeader>
          <CardDescription>Expenses this month</CardDescription>
          <CardTitle class="text-2xl tabular-nums">
            Rs {{ formatAmount(expenses.month_total) }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-1 text-xs text-muted-foreground">
          <p>
            {{ expenses.month_count }}
            {{ expenses.month_count === 1 ? 'expense' : 'expenses' }} recorded
          </p>
          <Link
            :href="expensesIndex()"
            class="font-medium text-primary hover:underline"
          >
            View expenses
          </Link>
        </CardContent>
      </Card>

      <Card v-if="complaints" class="gap-2">
        <CardHeader>
          <CardDescription class="flex items-center gap-2">
            <MessageSquareWarning class="size-3.5" />
            Open complaints
          </CardDescription>
          <CardTitle class="text-2xl tabular-nums">
            {{ complaints.open_count + complaints.in_progress_count }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-1 text-xs text-muted-foreground">
          <p>{{ complaints.in_progress_count }} in progress</p>
          <Link
            :href="complaintsIndex()"
            class="font-medium text-primary hover:underline"
          >
            View complaints
          </Link>
        </CardContent>
      </Card>

      <Card v-if="maintenanceJobs" class="gap-2">
        <CardHeader>
          <CardDescription class="flex items-center gap-2">
            <Wrench class="size-3.5" />
            Open jobs
          </CardDescription>
          <CardTitle class="text-2xl tabular-nums">
            {{ maintenanceJobs.open_count }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-1 text-xs text-muted-foreground">
          <p>{{ maintenanceJobs.due_count }} due or overdue today</p>
          <Link
            :href="jobsIndex()"
            class="font-medium text-primary hover:underline"
          >
            View jobs
          </Link>
        </CardContent>
      </Card>
    </div>

    <div
      v-if="
        hasOwnOpenJobs ||
        collections ||
        complaints ||
        maintenanceJobs ||
        inventory
      "
      class="grid items-start gap-4 lg:grid-cols-2"
    >
      <Card v-if="myJobs && hasOwnOpenJobs">
        <CardHeader>
          <CardTitle class="text-base">Your assigned jobs</CardTitle>
          <CardDescription>
            Maintenance work assigned to you personally
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ul class="divide-y">
            <li
              v-for="job in myJobs.open"
              :key="job.id"
              class="py-3 first:pt-0 last:pb-0"
            >
              <Link :href="showMyJob(job.id)" class="flex items-center gap-3">
                <div
                  class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted"
                >
                  <Wrench class="size-4 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">{{ job.title }}</p>
                  <p class="truncate text-xs text-muted-foreground">
                    {{ job.job_number }}
                    <template v-if="job.complaint_number">
                      · {{ job.complaint_number }}
                    </template>
                    · Scheduled {{ job.scheduled_date }}
                  </p>
                </div>
                <JobStatusBadge :status="job.status" />
              </Link>
            </li>
          </ul>
        </CardContent>
        <CardContent class="border-t pt-4">
          <Link
            :href="myJobsIndex()"
            class="text-sm font-medium text-primary hover:underline"
          >
            Go to my jobs
          </Link>
        </CardContent>
      </Card>

      <Card v-if="collections">
        <CardHeader>
          <CardTitle class="text-base">Recent payments</CardTitle>
          <CardDescription>
            The latest receipts across all accounts
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ul v-if="collections.recent.length > 0" class="divide-y">
            <li
              v-for="payment in collections.recent"
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
                  {{ payment.receipt_number ?? payment.account_number }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                  {{ payment.account_number }} · {{ payment.paid_at }} ·
                  {{ paymentMethodLabels[payment.method] }}
                </p>
              </div>
              <a
                :href="payment.receipt_url"
                target="_blank"
                rel="noopener"
                class="text-sm font-medium tabular-nums hover:underline"
              >
                Rs {{ formatAmount(payment.amount) }}
              </a>
            </li>
          </ul>
          <div v-else class="flex flex-col items-center gap-1 py-6 text-center">
            <p class="text-sm font-medium">No payments yet</p>
            <p class="max-w-sm text-xs text-muted-foreground">
              Receipts will appear here once bills are paid at the office or
              online.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-if="complaints">
        <CardHeader>
          <CardTitle class="text-base">Unresolved complaints</CardTitle>
          <CardDescription>
            The latest member issues waiting on the society
          </CardDescription>
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
                    {{ complaint.member_name }} · {{ complaint.submitted_at }}
                  </p>
                </div>
                <ComplaintStatusBadge :status="complaint.status" />
              </Link>
            </li>
          </ul>
          <div v-else class="flex flex-col items-center gap-1 py-6 text-center">
            <p class="text-sm font-medium">No open complaints</p>
            <p class="max-w-sm text-xs text-muted-foreground">
              Member complaints that need attention will show up here.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-if="maintenanceJobs">
        <CardHeader>
          <CardTitle class="text-base">Upcoming jobs</CardTitle>
          <CardDescription>
            Open maintenance work, earliest scheduled first
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ul v-if="maintenanceJobs.upcoming.length > 0" class="divide-y">
            <li
              v-for="job in maintenanceJobs.upcoming"
              :key="job.id"
              class="py-3 first:pt-0 last:pb-0"
            >
              <Link :href="showJob(job.id)" class="flex items-center gap-3">
                <div
                  class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted"
                >
                  <Wrench class="size-4 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">{{ job.title }}</p>
                  <p class="truncate text-xs text-muted-foreground">
                    {{ job.job_number }} · Scheduled {{ job.scheduled_date }}
                  </p>
                </div>
                <JobStatusBadge :status="job.status" />
              </Link>
            </li>
          </ul>
          <div v-else class="flex flex-col items-center gap-1 py-6 text-center">
            <p class="text-sm font-medium">No open jobs</p>
            <p class="max-w-sm text-xs text-muted-foreground">
              Maintenance jobs that are assigned or in progress will appear
              here.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-if="inventory">
        <CardHeader>
          <CardTitle class="text-base">Low stock</CardTitle>
          <CardDescription>
            Items at or below their reorder level
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ul v-if="inventory.low_stock_items.length > 0" class="divide-y">
            <li
              v-for="item in inventory.low_stock_items"
              :key="item.id"
              class="py-3 first:pt-0 last:pb-0"
            >
              <Link
                :href="showInventoryItem(item.id)"
                class="flex items-center gap-3"
              >
                <div
                  class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted"
                >
                  <Boxes class="size-4 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">{{ item.name }}</p>
                  <p class="truncate text-xs text-muted-foreground">
                    Reorder at {{ item.reorder_level }} {{ item.unit }}
                  </p>
                </div>
                <span
                  class="text-sm font-medium text-red-600 tabular-nums dark:text-red-400"
                >
                  {{ item.quantity_in_stock }} {{ item.unit }}
                </span>
              </Link>
            </li>
          </ul>
          <div v-else class="flex flex-col items-center gap-1 py-6 text-center">
            <div
              class="flex size-9 items-center justify-center rounded-full bg-muted"
            >
              <Droplets class="size-4 text-muted-foreground" />
            </div>
            <p class="text-sm font-medium">Stock levels are healthy</p>
            <p class="max-w-sm text-xs text-muted-foreground">
              Items that fall to their reorder level will be flagged here.
            </p>
          </div>
        </CardContent>
        <CardContent
          v-if="inventory.low_stock_items.length > 0"
          class="border-t pt-4"
        >
          <Link
            :href="inventoryIndex()"
            class="text-sm font-medium text-primary hover:underline"
          >
            View inventory
          </Link>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
