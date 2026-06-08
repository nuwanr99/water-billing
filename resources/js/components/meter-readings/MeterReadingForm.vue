<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Droplets, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import MeterDigits from '@/components/meter-readings/MeterDigits.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatReading } from '@/lib/utils';
import type { PreviousReading } from '@/types';

const props = defineProps<{
  mode: 'create' | 'edit';
  previous: PreviousReading;
  initialValue?: number;
  action: { url: string };
}>();

/**
 * Financial-style fixed-decimal entry: the decimal point is always present
 * and digits shift in from the right (typing 1, 2, 3 reads 0.01 → 0.12 →
 * 1.23), so the numeric keypad needs no dot key. `digitBuffer` holds the raw
 * digits; the input always displays the derived, formatted value.
 */
const MAX_DIGITS = 10; // decimal(10,2): up to 8 integer + 2 decimal digits

const digitBuffer = ref(
  props.initialValue === undefined
    ? ''
    : Math.round(props.initialValue * 100).toString(),
);

const enteredValue = computed(() =>
  digitBuffer.value === '' ? null : Number(digitBuffer.value) / 100,
);

const displayValue = computed(() =>
  enteredValue.value === null ? '' : formatReading(enteredValue.value),
);

const form = useForm<{ reading_value: string }>({
  reading_value: props.initialValue?.toFixed(2) ?? '',
});

const handleInput = (event: Event) => {
  const input = event.target as HTMLInputElement;

  digitBuffer.value = input.value
    .replace(/\D/g, '')
    .replace(/^0+(?=\d)/, '')
    .slice(0, MAX_DIGITS);

  input.value = displayValue.value;
  input.setSelectionRange(input.value.length, input.value.length);

  form.reading_value =
    enteredValue.value === null ? '' : enteredValue.value.toFixed(2);
};

const consumption = computed(() =>
  enteredValue.value === null
    ? null
    : Math.round((enteredValue.value - props.previous.value) * 100) / 100,
);
</script>

<template>
  <form
    class="flex flex-1 flex-col"
    @submit.prevent="
      mode === 'create' ? form.post(action.url) : form.put(action.url)
    "
  >
    <div class="flex flex-col gap-6 px-4">
      <div class="flex flex-col gap-2">
        <p class="text-sm text-muted-foreground">
          {{
            previous.is_initial
              ? 'Starting reading (meter baseline)'
              : `Previous reading · ${previous.date}`
          }}
        </p>
        <MeterDigits :value="previous.value" />
      </div>

      <div class="flex flex-col gap-2">
        <Label for="reading_value" class="text-sm text-muted-foreground">
          {{
            mode === 'create'
              ? 'Current meter reading'
              : 'Corrected meter reading'
          }}
        </Label>
        <input
          id="reading_value"
          :value="displayValue"
          type="text"
          inputmode="numeric"
          autocomplete="off"
          autofocus
          placeholder="0.00"
          class="h-16 w-full rounded-xl border bg-transparent text-center text-4xl font-semibold tracking-[0.1em] tabular-nums placeholder:text-muted-foreground/30 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none dark:bg-input/30"
          :aria-invalid="Boolean(form.errors.reading_value) || undefined"
          @input="handleInput"
        />
        <p v-if="form.errors.reading_value" class="text-sm text-destructive">
          {{ form.errors.reading_value }}
        </p>
      </div>

      <div
        class="flex min-h-16 items-center gap-3 rounded-xl border px-4 py-3"
        :class="
          consumption !== null && consumption < 0
            ? 'border-destructive/40 bg-destructive/5'
            : 'bg-muted/40'
        "
      >
        <template v-if="consumption === null">
          <Droplets class="size-5 shrink-0 text-muted-foreground" />
          <p class="text-sm text-muted-foreground">
            Type every digit on the dial, including the two red decimals — the
            decimal point is entered for you. Usage is calculated automatically.
          </p>
        </template>
        <template v-else-if="consumption < 0">
          <TriangleAlert class="size-5 shrink-0 text-destructive" />
          <p class="text-sm text-destructive">
            Lower than the previous reading. Please re-check the meter dial.
          </p>
        </template>
        <template v-else>
          <Droplets class="size-5 shrink-0 text-primary" />
          <div>
            <p class="text-lg font-semibold tabular-nums">
              {{ formatReading(consumption) }} units
            </p>
            <p class="text-xs text-muted-foreground">
              Usage since the previous reading
            </p>
          </div>
        </template>
      </div>
    </div>

    <div
      class="sticky bottom-0 mt-auto border-t bg-background/95 px-4 py-3 backdrop-blur"
    >
      <Button
        type="submit"
        class="h-12 w-full text-base"
        :disabled="form.processing || consumption === null || consumption < 0"
      >
        {{ mode === 'create' ? 'Save reading' : 'Save correction' }}
      </Button>
    </div>
  </form>
</template>
