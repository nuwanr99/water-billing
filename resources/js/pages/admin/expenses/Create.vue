<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SearchableCombobox from '@/components/SearchableCombobox.vue';
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
import { Textarea } from '@/components/ui/textarea';
import {
  create,
  index,
  jobs as jobsRoute,
  store,
} from '@/routes/admin/expenses';
import type { ComboboxOption } from '@/types';

type AccountOption = { id: number; code: string; name: string };
type PaidFromOption = AccountOption & { balance: number };
type JobResult = { id: number; job_number: string; title: string };

const props = defineProps<{
  categoryAccounts: AccountOption[];
  paidFromAccounts: PaidFromOption[];
  job: { id: number; job_number: string; title: string } | null;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Expenses', href: index() },
      { title: 'New expense', href: create() },
    ],
  },
});

const formatRs = (value: number): string =>
  `Rs ${value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

const today = new Date().toISOString().slice(0, 10);

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
  expense_date: string;
  amount: number | string;
  category_account_id: number | null;
  paid_from_account_id: number | null;
  maintenance_job_id: number | null;
  description: string;
  reference_image: File | null;
}>({
  expense_date: today,
  amount: '',
  category_account_id: null,
  paid_from_account_id: null,
  maintenance_job_id: props.job?.id ?? null,
  description: '',
  reference_image: null,
});

const onImagePicked = (event: Event): void => {
  const input = event.target as HTMLInputElement;
  form.reference_image = input.files?.[0] ?? null;
};

form.transform((data) => ({
  ...data,
  maintenance_job_id: selectedJob.value
    ? Number(selectedJob.value.value)
    : null,
}));

// The paid-from account's current balance, shown to warn on an overspend
// without blocking the record.
const selectedPaidFrom = computed<PaidFromOption | null>(
  () =>
    props.paidFromAccounts.find(
      (account) => account.id === form.paid_from_account_id,
    ) ?? null,
);

const wouldOverspend = computed<boolean>(() => {
  const amount = Number(form.amount);

  return (
    selectedPaidFrom.value !== null &&
    Number.isFinite(amount) &&
    amount > selectedPaidFrom.value.balance
  );
});

// Job typeahead — an optional link back to the work (and its complaint).
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
  form.post(store().url);
};
</script>

<template>
  <Head title="New expense" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="New expense"
      description="Record a society expense — it posts to the system ledger"
    />

    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
      <div class="grid gap-2">
        <Label for="expense_date">Expense date</Label>
        <Input
          id="expense_date"
          v-model="form.expense_date"
          type="date"
          :max="today"
          required
          class="max-w-48"
        />
        <InputError :message="form.errors.expense_date" />
      </div>

      <div class="grid gap-2">
        <Label for="amount">Amount (Rs)</Label>
        <Input
          id="amount"
          v-model="form.amount"
          type="number"
          step="0.01"
          min="0.01"
          required
          class="max-w-48"
          placeholder="0.00"
        />
        <InputError :message="form.errors.amount" />
      </div>

      <div class="grid gap-2">
        <Label for="category_account_id">Expense category</Label>
        <Select v-model="form.category_account_id">
          <SelectTrigger id="category_account_id" class="w-full">
            <SelectValue placeholder="What was it spent on?" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="account in categoryAccounts"
              :key="account.id"
              :value="account.id"
            >
              {{ account.code }} — {{ account.name }}
            </SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="form.errors.category_account_id" />
      </div>

      <div class="grid gap-2">
        <Label for="paid_from_account_id">Paid from</Label>
        <Select v-model="form.paid_from_account_id">
          <SelectTrigger id="paid_from_account_id" class="w-full">
            <SelectValue placeholder="Which cash or bank account?" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="account in paidFromAccounts"
              :key="account.id"
              :value="account.id"
            >
              {{ account.code }} — {{ account.name }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p
          v-if="selectedPaidFrom"
          class="text-xs"
          :class="wouldOverspend ? 'text-destructive' : 'text-muted-foreground'"
        >
          {{ selectedPaidFrom.name }} balance:
          {{ formatRs(selectedPaidFrom.balance) }}
          <span v-if="wouldOverspend">
            — this expense exceeds the available balance.
          </span>
        </p>
        <InputError :message="form.errors.paid_from_account_id" />
      </div>

      <div class="grid gap-2">
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
          Link the spend to a job to trace it back to the complaint that raised
          it.
        </p>
        <InputError :message="form.errors.maintenance_job_id" />
      </div>

      <div class="grid gap-2">
        <Label for="description">Description</Label>
        <Textarea
          id="description"
          v-model="form.description"
          rows="3"
          required
          placeholder="e.g. Replacement valve and fittings"
        />
        <InputError :message="form.errors.description" />
      </div>

      <div class="grid gap-2">
        <Label for="reference_image">Reference image (optional)</Label>
        <Input
          id="reference_image"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="max-w-md"
          @change="onImagePicked"
        />
        <p class="text-xs text-muted-foreground">
          Attach a receipt or a photo of what was bought — JPG, PNG, or WebP up
          to 5&nbsp;MB.
        </p>
        <InputError :message="form.errors.reference_image" />
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing">
          Record expense
        </Button>
        <Button variant="secondary" as-child>
          <Link :href="index()">Cancel</Link>
        </Button>
      </div>
    </form>
  </div>
</template>
