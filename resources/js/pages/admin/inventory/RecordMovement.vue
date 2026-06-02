<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableCombobox from '@/components/SearchableCombobox.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { jobs as jobsRoute, show } from '@/routes/admin/inventory';
import { store } from '@/routes/admin/inventory/movements';
import type { ComboboxOption } from '@/types';

type JobResult = { id: number; job_number: string; title: string };

const props = defineProps<{
  item: {
    id: number;
    name: string;
    unit: string;
    unit_rate: number;
    quantity_in_stock: number;
  };
  job: { id: number; job_number: string; title: string } | null;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inventory', href: '#' },
      { title: 'Record movement', href: '#' },
    ],
  },
});

const movementTypes = [
  { value: 'purchase', label: 'Purchase (add stock)' },
  { value: 'usage', label: 'Usage (remove stock)' },
  { value: 'adjustment', label: 'Adjustment (correct stock)' },
];

const selectedJob = ref<ComboboxOption | null>(
  props.job
    ? {
        value: props.job.id,
        label: props.job.job_number,
        description: props.job.title,
      }
    : null,
);

const form = useForm<{
  movement_type: string;
  quantity: number | string;
  unit_rate: number | string;
  maintenance_job_id: number | null;
  note: string;
}>({
  movement_type: 'purchase',
  quantity: '',
  unit_rate: props.item.unit_rate || '',
  maintenance_job_id: null,
  note: '',
});

const isPurchase = computed(() => form.movement_type === 'purchase');
const isUsage = computed(() => form.movement_type === 'usage');
const isAdjustment = computed(() => form.movement_type === 'adjustment');

form.transform((data) => ({
  ...data,
  unit_rate: isPurchase.value ? data.unit_rate : null,
  maintenance_job_id:
    isUsage.value && selectedJob.value ? Number(selectedJob.value.value) : null,
}));

// Job typeahead — attribute usage to the job that consumed the stock.
const jobSearch = ref('');
const jobOptions = ref<ComboboxOption[]>([]);
const jobLookup = useHttp<{ search: string }, JobResult[]>({ search: '' });

const searchJobs = (): void => {
  jobLookup.search = jobSearch.value;

  jobLookup.get(jobsRoute().url, {
    onSuccess: (response) => {
      jobOptions.value = (response ?? []).map((job): ComboboxOption => ({
        value: job.id,
        label: job.job_number,
        description: job.title,
      }));
    },
  });
};

watchDebounced(jobSearch, searchJobs, { debounce: 250 });

const submit = (): void => {
  form.post(store(props.item.id).url);
};
</script>

<template>
  <Head :title="`Record movement — ${item.name}`" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Record stock movement"
      description="Add, remove, or correct this item's stock"
    />

    <Card class="max-w-2xl">
      <CardContent>
        <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs text-muted-foreground">Item</dt>
            <dd class="text-sm font-medium">{{ item.name }}</dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">On hand</dt>
            <dd class="text-sm font-medium tabular-nums">
              {{ item.quantity_in_stock }} {{ item.unit }}
            </dd>
          </div>
        </dl>
      </CardContent>
    </Card>

    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
      <div class="grid gap-2">
        <Label for="movement_type">Movement type</Label>
        <Select v-model="form.movement_type">
          <SelectTrigger id="movement_type" class="w-full">
            <SelectValue placeholder="Select a movement type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="type in movementTypes"
              :key="type.value"
              :value="type.value"
            >
              {{ type.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="form.errors.movement_type" />
      </div>

      <div class="grid gap-2">
        <Label for="quantity"> Quantity ({{ item.unit }}) </Label>
        <Input
          id="quantity"
          v-model="form.quantity"
          type="number"
          step="1"
          :min="isAdjustment ? undefined : 1"
          required
          class="max-w-48"
          placeholder="0"
        />
        <p v-if="isAdjustment" class="text-xs text-muted-foreground">
          Use a positive number to add stock or a negative number (e.g. -3) to
          remove it.
        </p>
        <InputError :message="form.errors.quantity" />
      </div>

      <div v-if="isPurchase" class="grid gap-2">
        <Label for="unit_rate">Purchase unit rate (Rs, optional)</Label>
        <Input
          id="unit_rate"
          v-model="form.unit_rate"
          type="number"
          step="0.01"
          min="0"
          class="max-w-48"
          placeholder="0.00"
        />
        <InputError :message="form.errors.unit_rate" />
      </div>

      <div v-if="isUsage" class="grid gap-2">
        <Label>Linked job (optional)</Label>
        <SearchableCombobox
          v-model="selectedJob"
          v-model:search-term="jobSearch"
          :options="jobOptions"
          :loading="jobLookup.processing"
          placeholder="Search a maintenance job by number or title..."
          empty-text="No jobs found."
        />
        <p class="text-xs text-muted-foreground">
          Attribute the usage to the job that consumed it.
        </p>
        <InputError :message="form.errors.maintenance_job_id" />
      </div>

      <div class="grid gap-2">
        <Label for="note"> Note{{ isAdjustment ? '' : ' (optional)' }} </Label>
        <Textarea
          id="note"
          v-model="form.note"
          rows="2"
          :required="isAdjustment"
          placeholder="e.g. Damaged in storage"
        />
        <InputError :message="form.errors.note" />
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing">
          Record movement
        </Button>
        <Button variant="secondary" as-child>
          <Link :href="show(item.id)">Cancel</Link>
        </Button>
      </div>
    </form>
  </div>
</template>
