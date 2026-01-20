<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/admin/billing-categories';

type TierRow = {
  upper_units: number | string | null;
  rate_per_unit: number | string;
  service_charge: number | string;
};

type BillingCategoryFormData = {
  id: number;
  name: string;
  description: string | null;
  late_fee_percent: number;
  is_active: boolean;
  tiers: {
    lower_units: number;
    upper_units: number | null;
    rate_per_unit: number;
    service_charge: number;
  }[];
};

const props = defineProps<{
  mode: 'create' | 'edit';
  billingCategory?: BillingCategoryFormData;
}>();

const form = useForm<{
  name: string;
  description: string;
  late_fee_percent: number | string;
  is_active: boolean;
  tiers: TierRow[];
}>({
  name: props.billingCategory?.name ?? '',
  description: props.billingCategory?.description ?? '',
  late_fee_percent: props.billingCategory?.late_fee_percent ?? 2.5,
  is_active: props.billingCategory?.is_active ?? true,
  tiers: props.billingCategory?.tiers.map((tier) => ({
    upper_units: tier.upper_units,
    rate_per_unit: tier.rate_per_unit,
    service_charge: tier.service_charge,
  })) ?? [{ upper_units: null, rate_per_unit: '', service_charge: '' }],
});

/**
 * Slabs are stored as contiguous (lower, upper] ranges; the form only asks
 * for each slab's upper bound and derives the lower bounds on submit.
 */
form.transform((data) => ({
  ...data,
  tiers: data.tiers.map((tier, index) => {
    const isLast = index === data.tiers.length - 1;
    const upper =
      isLast || tier.upper_units === null || tier.upper_units === ''
        ? null
        : Number(tier.upper_units);

    return {
      lower_units: index === 0 ? 0 : lowerBoundOf(data.tiers, index),
      upper_units: upper,
      rate_per_unit: tier.rate_per_unit,
      service_charge: tier.service_charge,
    };
  }),
}));

const lowerBoundOf = (tiers: TierRow[], index: number): number => {
  const previousUpper = tiers[index - 1]?.upper_units;

  return previousUpper === null || previousUpper === '' || previousUpper === undefined
    ? 0
    : Number(previousUpper);
};

const activeState = computed({
  get: () => (form.is_active ? 'active' : 'inactive'),
  set: (value: string) => {
    form.is_active = value === 'active';
  },
});

const fromLabel = (index: number): string => {
  if (index === 0) {
    return '0';
  }

  const previousUpper = form.tiers[index - 1]?.upper_units;

  if (previousUpper === null || previousUpper === '' || previousUpper === undefined) {
    return '—';
  }

  return String(Number(previousUpper) + 1);
};

const addTier = () => {
  form.tiers.push({ upper_units: null, rate_per_unit: '', service_charge: '' });
};

const removeTier = (index: number) => {
  if (form.tiers.length > 1) {
    form.tiers.splice(index, 1);
  }
};

const tierError = (index: number, field: string): string | undefined =>
  (form.errors as Record<string, string>)[`tiers.${index}.${field}`];

const submit = () => {
  if (props.mode === 'create') {
    form.post(store().url);

    return;
  }

  form.put(update(props.billingCategory!.id).url);
};
</script>

<template>
  <form class="max-w-3xl space-y-6" @submit.prevent="submit">
    <div class="grid gap-6 sm:grid-cols-2">
      <div class="grid gap-2">
        <Label for="name">Name</Label>
        <Input id="name" v-model="form.name" required placeholder="Domestic" />
        <InputError :message="form.errors.name" />
      </div>

      <div class="grid gap-2">
        <Label for="status">Status</Label>
        <Select v-model="activeState">
          <SelectTrigger id="status" class="w-full">
            <SelectValue placeholder="Select a status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="form.errors.is_active" />
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="description">Description (optional)</Label>
      <Input
        id="description"
        v-model="form.description"
        placeholder="Household water connections"
      />
      <InputError :message="form.errors.description" />
    </div>

    <div class="grid gap-2">
      <Label for="late_fee_percent">Late fee (% per month)</Label>
      <Input
        id="late_fee_percent"
        v-model="form.late_fee_percent"
        type="number"
        min="0"
        max="100"
        step="0.01"
        required
        class="max-w-48"
      />
      <p class="text-xs text-muted-foreground">
        Surcharge applied to the balance of bills unpaid past their due date.
      </p>
      <InputError :message="form.errors.late_fee_percent" />
    </div>

    <div class="space-y-3">
      <div>
        <Label>Tariff slabs</Label>
        <p class="mt-1 text-xs text-muted-foreground">
          Usage is charged progressively across slabs. The monthly service
          charge applied is the one on the slab the month's total consumption
          falls in; a month with no usage pays the first slab's service
          charge. The last slab is open-ended.
        </p>
      </div>

      <div class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/50 text-left">
              <th class="p-3 font-medium">Units from</th>
              <th class="p-3 font-medium">Units to</th>
              <th class="p-3 font-medium">Rate (Rs./unit)</th>
              <th class="p-3 font-medium">Service charge (Rs./month)</th>
              <th class="w-0 p-3">
                <span class="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(tier, tierIndex) in form.tiers"
              :key="tierIndex"
              class="border-b last:border-0"
            >
              <td class="p-3 tabular-nums text-muted-foreground">
                {{ fromLabel(tierIndex) }}
              </td>
              <td class="p-3">
                <template v-if="tierIndex === form.tiers.length - 1">
                  <span class="text-muted-foreground">and above</span>
                </template>
                <template v-else>
                  <Input
                    v-model="tier.upper_units"
                    type="number"
                    min="1"
                    step="1"
                    required
                    class="max-w-28"
                    :aria-label="`Slab ${tierIndex + 1} upper bound`"
                  />
                  <InputError
                    class="mt-1"
                    :message="tierError(tierIndex, 'upper_units')"
                  />
                </template>
              </td>
              <td class="p-3">
                <Input
                  v-model="tier.rate_per_unit"
                  type="number"
                  min="0"
                  step="0.01"
                  required
                  class="max-w-32"
                  :aria-label="`Slab ${tierIndex + 1} rate per unit`"
                />
                <InputError
                  class="mt-1"
                  :message="tierError(tierIndex, 'rate_per_unit')"
                />
              </td>
              <td class="p-3">
                <Input
                  v-model="tier.service_charge"
                  type="number"
                  min="0"
                  step="0.01"
                  required
                  class="max-w-32"
                  :aria-label="`Slab ${tierIndex + 1} service charge`"
                />
                <InputError
                  class="mt-1"
                  :message="tierError(tierIndex, 'service_charge')"
                />
              </td>
              <td class="p-3">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  :disabled="form.tiers.length === 1"
                  :aria-label="`Remove slab ${tierIndex + 1}`"
                  @click="removeTier(tierIndex)"
                >
                  <Trash2 class="size-4" />
                </Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <InputError :message="form.errors.tiers" />

      <Button type="button" variant="outline" size="sm" @click="addTier">
        <Plus class="size-4" />
        Add slab
      </Button>
    </div>

    <div class="flex items-center gap-4">
      <Button type="submit" :disabled="form.processing">
        {{ mode === 'create' ? 'Create billing category' : 'Save changes' }}
      </Button>
      <Button variant="secondary" as-child>
        <Link :href="index()">Cancel</Link>
      </Button>
    </div>
  </form>
</template>
