<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import AccountSummaryCard from '@/components/meter-readings/AccountSummaryCard.vue';
import { Button } from '@/components/ui/button';
import { formatReading } from '@/lib/utils';
import { edit, index } from '@/routes/meter-readings';
import type {
  MeterReadingAccountSummary,
  MeterReadingHistoryRow,
} from '@/types';

defineProps<{
  account: MeterReadingAccountSummary;
  readings: MeterReadingHistoryRow[];
  initialReading: number;
}>();

setLayoutProps({
  title: 'Reading history',
  backHref: index().url,
});
</script>

<template>
  <Head :title="`History — ${account.account_number}`" />

  <div class="flex flex-1 flex-col gap-4 px-4 pt-3 pb-6">
    <AccountSummaryCard :account="account" />

    <div class="rounded-2xl border">
      <ul class="divide-y">
        <li
          v-for="reading in readings"
          :key="reading.id"
          class="flex items-center gap-3 p-4"
        >
          <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <p class="font-medium">{{ reading.month }}</p>
            <p class="truncate text-xs text-muted-foreground">
              Recorded {{ reading.date }} · by {{ reading.recorded_by }}
            </p>
          </div>
          <div class="shrink-0 text-right">
            <p class="font-semibold tabular-nums">
              {{ formatReading(reading.value) }}
            </p>
            <p class="text-xs text-muted-foreground tabular-nums">
              +{{ formatReading(reading.consumption) }} units
            </p>
          </div>
          <Button
            v-if="reading.is_latest"
            variant="outline"
            size="sm"
            class="shrink-0"
            as-child
          >
            <Link :href="edit(reading.id).url">
              <Pencil class="size-3.5" />
              Edit
            </Link>
          </Button>
        </li>

        <li class="flex items-center gap-3 p-4">
          <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <p class="font-medium">Starting reading</p>
            <p class="text-xs text-muted-foreground">Meter baseline</p>
          </div>
          <p class="shrink-0 font-semibold tabular-nums">
            {{ formatReading(initialReading) }}
          </p>
        </li>
      </ul>
    </div>

    <p class="text-center text-xs text-muted-foreground">
      Only the most recent reading can be corrected.
    </p>
  </div>
</template>
