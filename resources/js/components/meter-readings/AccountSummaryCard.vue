<script setup lang="ts">
import { FileText, Gauge, UserRound } from '@lucide/vue';
import type { MeterReadingAccountSummary } from '@/types';

defineProps<{
  account: MeterReadingAccountSummary;
}>();
</script>

<template>
  <div class="flex flex-col gap-2.5 rounded-2xl border bg-muted/40 p-4">
    <div class="flex items-center gap-2.5">
      <UserRound class="size-4 shrink-0 text-muted-foreground" />
      <p class="truncate font-semibold">{{ account.owner_name }}</p>
    </div>
    <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-muted-foreground">
      <span class="flex items-center gap-1.5">
        <FileText class="size-3.5 shrink-0" />
        {{ account.account_number }}
      </span>
      <span class="flex items-center gap-1.5">
        <Gauge class="size-3.5 shrink-0" />
        {{ account.meter_number }}
      </span>
    </div>
  </div>
</template>
