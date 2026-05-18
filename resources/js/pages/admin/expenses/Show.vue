<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/admin/expenses';
import { show as jobShow } from '@/routes/admin/maintenance-jobs';
import { show as journalShow } from '@/routes/admin/system-ledger';

defineProps<{
  expense: {
    id: number;
    expense_number: string;
    expense_date: string;
    amount: number;
    category: { code: string; name: string };
    paid_from: { code: string; name: string };
    description: string;
    recorded_by: string | null;
    recorded_at: string | null;
    job: { id: number; job_number: string; title: string } | null;
    journal: { id: number; reference_number: string } | null;
    reference_image: { name: string; is_image: boolean; url: string } | null;
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Expenses', href: index() },
      { title: '', href: '#' },
    ],
  },
});

const formatRs = (value: number): string =>
  `Rs ${value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
</script>

<template>
  <Head :title="expense.expense_number" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        :title="expense.expense_number"
        :description="expense.description"
      />
      <Button variant="secondary" as-child>
        <Link :href="index()">Back to expenses</Link>
      </Button>
    </div>

    <Card class="max-w-2xl">
      <CardContent>
        <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs text-muted-foreground">Date</dt>
            <dd class="text-sm font-medium">{{ expense.expense_date }}</dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Amount</dt>
            <dd class="text-sm font-medium tabular-nums">
              {{ formatRs(expense.amount) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Expense category</dt>
            <dd class="text-sm font-medium">
              {{ expense.category.code }} — {{ expense.category.name }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Paid from</dt>
            <dd class="text-sm font-medium">
              {{ expense.paid_from.code }} — {{ expense.paid_from.name }}
            </dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs text-muted-foreground">Description</dt>
            <dd class="text-sm">{{ expense.description }}</dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Linked job</dt>
            <dd class="text-sm font-medium">
              <Link
                v-if="expense.job"
                :href="jobShow(expense.job.id)"
                class="text-primary underline-offset-4 hover:underline"
              >
                {{ expense.job.job_number }} — {{ expense.job.title }}
              </Link>
              <span v-else class="text-muted-foreground">—</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Journal entry</dt>
            <dd class="text-sm font-medium">
              <Link
                v-if="expense.journal"
                :href="journalShow(expense.journal.id)"
                class="text-primary underline-offset-4 hover:underline"
              >
                {{ expense.journal.reference_number }}
              </Link>
              <span v-else class="text-muted-foreground">—</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Recorded by</dt>
            <dd class="text-sm font-medium">
              {{ expense.recorded_by ?? '—' }}
              <span v-if="expense.recorded_at" class="text-muted-foreground">
                · {{ expense.recorded_at }}
              </span>
            </dd>
          </div>
          <div v-if="expense.reference_image" class="sm:col-span-2">
            <dt class="text-xs text-muted-foreground">Reference image</dt>
            <dd class="mt-1">
              <a
                :href="expense.reference_image.url"
                target="_blank"
                rel="noopener"
                class="inline-block"
              >
                <img
                  v-if="expense.reference_image.is_image"
                  :src="expense.reference_image.url"
                  :alt="expense.reference_image.name"
                  class="max-h-64 rounded-lg border"
                />
                <span v-else class="text-sm text-primary hover:underline">
                  {{ expense.reference_image.name }}
                </span>
              </a>
            </dd>
          </div>
        </dl>
      </CardContent>
    </Card>
  </div>
</template>
