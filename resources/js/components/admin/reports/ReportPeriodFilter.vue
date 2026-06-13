<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import type { ReportPeriodFilters } from '@/lib/reports';

const props = defineProps<{
  /** The report index URL the filter reloads with new period params. */
  action: string;
  filters: ReportPeriodFilters;
  /** Extra query params to preserve when the period changes. */
  extraParams?: Record<string, string | number | null>;
}>();

const state = reactive({
  period_type: props.filters.period_type,
  month: props.filters.month,
  quarter: String(props.filters.quarter),
  year: String(props.filters.year),
  from: props.filters.from,
  to: props.filters.to,
});

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 6 }, (_, i) => String(currentYear - i));

const apply = () => {
  const params: Record<string, string | number> = {
    period_type: state.period_type,
  };

  if (state.period_type === 'month') {
    if (!/^\d{4}-\d{2}$/.test(state.month)) {
      return;
    }

    params.month = state.month;
  } else if (state.period_type === 'quarter') {
    params.year = state.year;
    params.quarter = state.quarter;
  } else if (state.period_type === 'year') {
    params.year = state.year;
  } else {
    if (!state.from || !state.to) {
      return;
    }

    params.from = state.from;
    params.to = state.to;
  }

  for (const [key, value] of Object.entries(props.extraParams ?? {})) {
    if (value !== null && value !== '') {
      params[key] = value;
    }
  }

  router.get(props.action, params, {
    preserveState: true,
    preserveScroll: true,
  });
};

watch(state, apply);
</script>

<template>
  <div class="flex flex-wrap items-end gap-3">
    <div class="grid gap-1.5">
      <Label class="text-xs text-muted-foreground">Period</Label>
      <Select v-model="state.period_type">
        <SelectTrigger class="w-32">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="month">Month</SelectItem>
          <SelectItem value="quarter">Quarter</SelectItem>
          <SelectItem value="year">Year</SelectItem>
          <SelectItem value="custom">Custom</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="state.period_type === 'month'" class="grid gap-1.5">
      <Label class="text-xs text-muted-foreground">Month</Label>
      <Input v-model="state.month" type="month" class="w-40" />
    </div>

    <div
      v-if="state.period_type === 'quarter' || state.period_type === 'year'"
      class="grid gap-1.5"
    >
      <Label class="text-xs text-muted-foreground">Year</Label>
      <Select v-model="state.year">
        <SelectTrigger class="w-28">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="year in years" :key="year" :value="year">
            {{ year }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="state.period_type === 'quarter'" class="grid gap-1.5">
      <Label class="text-xs text-muted-foreground">Quarter</Label>
      <Select v-model="state.quarter">
        <SelectTrigger class="w-24">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="q in ['1', '2', '3', '4']" :key="q" :value="q">
            Q{{ q }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <template v-if="state.period_type === 'custom'">
      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">From</Label>
        <Input v-model="state.from" type="date" class="w-40" />
      </div>
      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">To</Label>
        <Input v-model="state.to" type="date" class="w-40" />
      </div>
    </template>

    <slot />
  </div>
</template>
