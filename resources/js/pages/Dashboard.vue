<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
  CreditCard,
  Droplets,
  FileText,
  Gauge,
  MapPin,
  Receipt,
} from '@lucide/vue';
import { computed } from 'vue';
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
import { formatReading } from '@/lib/utils';
import { dashboard } from '@/routes';
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

defineProps<{
  currentAccount: CurrentAccount | null;
  latestReading: {
    value: number;
    consumption: number;
    date: string;
  } | null;
  activeAccountsCount: number;
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

/**
 * Placeholder values shown until the meter reading, billing, and payment
 * modules land. Widgets rendering these carry a "Sample" badge.
 */
const sample = {
  currentBill: 'Rs. 1,250.00',
  billDueDate: 'Due 15 Jul 2026',
  usageByMonth: [12, 15, 14, 18, 22, 19, 16, 20, 17, 15, 21, 18],
  activity: [
    {
      icon: Receipt,
      title: 'June bill generated',
      description: 'Rs. 1,250.00 · awaiting approval',
      date: '01 Jul 2026',
    },
    {
      icon: CreditCard,
      title: 'May bill paid',
      description: 'Rs. 1,180.00 · paid online',
      date: '12 Jun 2026',
    },
    {
      icon: Gauge,
      title: 'Meter reading recorded',
      description: '1,254 units · normal usage',
      date: '28 Jun 2026',
    },
  ],
};

const months = [
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
];

const maxUsage = Math.max(...sample.usageByMonth);

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
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Card class="gap-2">
          <CardHeader>
            <CardDescription class="flex items-center justify-between gap-2">
              Current bill
              <Badge variant="secondary">Sample</Badge>
            </CardDescription>
            <CardTitle class="text-2xl">
              {{ sample.currentBill }}
            </CardTitle>
          </CardHeader>
          <CardContent class="text-xs text-muted-foreground">
            {{ sample.billDueDate }}
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
            {{
              latestReading ? 'Since the previous reading' : 'No readings yet'
            }}
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
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <CardTitle class="text-base"> Monthly usage </CardTitle>
                <CardDescription>
                  Live data arrives with the meter reading module
                </CardDescription>
              </div>
              <Badge variant="secondary">Sample</Badge>
            </div>
          </CardHeader>
          <CardContent>
            <div class="flex h-40 items-end gap-2">
              <div
                v-for="(usage, monthIndex) in sample.usageByMonth"
                :key="monthIndex"
                class="flex flex-1 flex-col items-center gap-1.5"
              >
                <div
                  class="w-full rounded-sm bg-primary/20"
                  :class="
                    monthIndex === sample.usageByMonth.length - 1
                      ? 'bg-primary/70'
                      : ''
                  "
                  :style="{
                    height: `${(usage / maxUsage) * 100}%`,
                  }"
                />
                <span class="text-[10px] text-muted-foreground">
                  {{ months[monthIndex] }}
                </span>
              </div>
            </div>
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

      <Card>
        <CardHeader>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <CardTitle class="text-base"> Recent activity </CardTitle>
              <CardDescription>
                Bills, payments, and readings will appear here
              </CardDescription>
            </div>
            <Badge variant="secondary">Sample</Badge>
          </div>
        </CardHeader>
        <CardContent>
          <ul class="divide-y">
            <li
              v-for="item in sample.activity"
              :key="item.title"
              class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
            >
              <div
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted"
              >
                <component
                  :is="item.icon"
                  class="size-4 text-muted-foreground"
                />
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                  {{ item.title }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                  {{ item.description }}
                </p>
              </div>
              <span class="text-xs text-muted-foreground">
                {{ item.date }}
              </span>
            </li>
          </ul>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
