<script setup lang="ts">
import { computed } from 'vue';
import { formatReading } from '@/lib/utils';

const { value, minIntegerDigits = 5 } = defineProps<{
  value: number;
  minIntegerDigits?: number;
}>();

const parts = computed(() => {
  const [integerPart, decimalPart] = value.toFixed(2).split('.');

  return {
    integer: integerPart.padStart(minIntegerDigits, '0').split(''),
    decimal: decimalPart.split(''),
  };
});
</script>

<template>
  <div
    class="flex items-center gap-1"
    role="img"
    :aria-label="`Meter shows ${formatReading(value)}`"
  >
    <span
      v-for="(digit, digitIndex) in parts.integer"
      :key="`int-${digitIndex}`"
      class="flex h-11 w-8 items-center justify-center rounded-md border bg-muted/50 text-xl font-semibold tabular-nums"
    >
      {{ digit }}
    </span>
    <span class="pb-1 text-xl font-bold text-muted-foreground">.</span>
    <span
      v-for="(digit, digitIndex) in parts.decimal"
      :key="`dec-${digitIndex}`"
      class="flex h-11 w-8 items-center justify-center rounded-md border border-destructive/30 bg-destructive/5 text-xl font-semibold text-destructive tabular-nums"
    >
      {{ digit }}
    </span>
  </div>
</template>
