<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
  ChevronRight,
  CircleCheck,
  FileText,
  Gauge,
  SearchX,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import { formatReading } from '@/lib/utils';
import { create, history, index } from '@/routes/meter-readings';
import type { MeterReadingAccountCard, MeterReadingProgress } from '@/types';

const props = defineProps<{
  accounts: MeterReadingAccountCard[];
  progress: MeterReadingProgress;
  monthLabel: string;
  filters: { search: string | null };
}>();

setLayoutProps({ title: 'Meter readings' });

const search = ref(props.filters.search ?? '');

watchDebounced(
  search,
  () => {
    router.get(index().url, search.value ? { search: search.value } : {}, {
      preserveState: true,
      replace: true,
    });
  },
  { debounce: 300 },
);

const progressPercent = computed(() =>
  props.progress.total === 0
    ? 0
    : Math.round((props.progress.read / props.progress.total) * 100),
);
</script>

<template>
  <Head title="Meter readings" />

  <div class="flex flex-1 flex-col gap-4 px-4 pt-3 pb-6">
    <div class="flex flex-col gap-2">
      <div
        class="flex items-baseline justify-between gap-2 text-sm text-muted-foreground"
      >
        <span>
          <span class="font-semibold text-foreground">
            {{ progress.read }} of {{ progress.total }}
          </span>
          read · {{ monthLabel }}
        </span>
        <span class="tabular-nums">{{ progressPercent }}%</span>
      </div>
      <div class="h-1.5 overflow-hidden rounded-full bg-muted">
        <div
          class="h-full rounded-full bg-primary transition-[width] duration-500"
          :style="{ width: `${progressPercent}%` }"
        />
      </div>
    </div>

    <SearchFilter
      v-model="search"
      class="max-w-none"
      placeholder="Name, account, or meter number..."
    />

    <div
      v-if="accounts.length === 0"
      class="flex flex-col items-center gap-3 rounded-2xl border border-dashed py-14 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-muted"
      >
        <SearchX class="size-6 text-muted-foreground" />
      </div>
      <div class="space-y-1 px-6">
        <p class="font-medium">No accounts found</p>
        <p class="text-sm text-muted-foreground">
          Try a different name, account number, or meter number.
        </p>
      </div>
    </div>

    <ul v-else class="flex flex-col gap-3">
      <li v-for="account in accounts" :key="account.id">
        <Link
          :href="
            account.read_this_month
              ? history(account.id).url
              : create(account.id).url
          "
          class="flex items-center gap-3 rounded-2xl border bg-card p-4 transition-[background-color,transform] select-none hover:bg-accent/50 active:scale-[0.99] active:bg-accent/50"
        >
          <div class="flex min-w-0 flex-1 flex-col gap-1.5">
            <p class="truncate font-semibold">{{ account.owner_name }}</p>
            <div
              class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
            >
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

          <div class="flex shrink-0 items-center gap-2">
            <div v-if="account.read_this_month" class="text-right">
              <p
                class="flex items-center justify-end gap-1 text-sm font-medium text-emerald-600 dark:text-emerald-400"
              >
                <CircleCheck class="size-4" />
                {{ formatReading(account.latest_reading?.value ?? 0) }}
              </p>
              <p class="text-xs text-muted-foreground">Recorded</p>
            </div>
            <div v-else class="text-right">
              <p
                class="rounded-full bg-primary px-3 py-1 text-xs font-medium text-primary-foreground"
              >
                Enter reading
              </p>
              <p
                v-if="account.latest_reading"
                class="mt-1 text-xs text-muted-foreground tabular-nums"
              >
                Last {{ formatReading(account.latest_reading.value) }}
              </p>
            </div>
            <ChevronRight class="size-4 text-muted-foreground" />
          </div>
        </Link>
      </li>
    </ul>
  </div>
</template>
