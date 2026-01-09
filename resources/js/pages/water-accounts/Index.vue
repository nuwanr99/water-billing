<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Droplets, Gauge, MapPin } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import WaterAccountStatusBadge from '@/components/WaterAccountStatusBadge.vue';
import { index, switchMethod } from '@/routes/water-accounts';
import type { WaterAccountStatus } from '@/types';

type MemberWaterAccount = {
  id: number;
  account_number: string;
  meter_number: string;
  connection_address: string | null;
  status: WaterAccountStatus;
  connected_at: string | null;
};

defineProps<{
  waterAccounts: MemberWaterAccount[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'My water accounts',
        href: index(),
      },
    ],
  },
});

const page = usePage();

const isCurrent = (account: MemberWaterAccount) =>
  account.id === page.props.currentWaterAccountId;

const makeCurrent = (account: MemberWaterAccount) => {
  router.post(switchMethod(account.id).url, {}, { preserveScroll: true });
};
</script>

<template>
  <Head title="My water accounts" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="My water accounts"
      description="All water connections registered under your membership"
    />

    <div
      v-if="waterAccounts.length === 0"
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
          Contact the society office to register a water connection under your
          membership.
        </p>
      </div>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <Card
        v-for="account in waterAccounts"
        :key="account.id"
        :class="isCurrent(account) ? 'border-primary/50' : ''"
      >
        <CardHeader>
          <div class="flex items-center justify-between gap-2">
            <CardTitle class="text-base">
              {{ account.account_number }}
            </CardTitle>
            <WaterAccountStatusBadge :status="account.status" />
          </div>
        </CardHeader>
        <CardContent class="space-y-3 text-sm">
          <div class="flex items-center gap-2">
            <Gauge class="size-4 shrink-0 text-muted-foreground" />
            <span>Meter {{ account.meter_number }}</span>
          </div>
          <div class="flex items-center gap-2">
            <MapPin class="size-4 shrink-0 text-muted-foreground" />
            <span class="truncate">
              {{ account.connection_address ?? 'Registered member address' }}
            </span>
          </div>
          <p class="text-xs text-muted-foreground">
            {{
              account.connected_at
                ? `Connected ${account.connected_at}`
                : 'Connection date not recorded'
            }}
          </p>
        </CardContent>
        <CardFooter>
          <div
            v-if="isCurrent(account)"
            class="flex items-center gap-1.5 text-sm font-medium text-primary"
          >
            <Check class="size-4" />
            Current account
          </div>
          <Button
            v-else
            variant="outline"
            size="sm"
            @click="makeCurrent(account)"
          >
            Switch to this account
          </Button>
        </CardFooter>
      </Card>
    </div>
  </div>
</template>
