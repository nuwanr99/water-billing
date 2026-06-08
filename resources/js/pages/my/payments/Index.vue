<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Banknote, Download } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/my/payments';
import type { Paginated } from '@/types';

type MyPaymentRow = {
  id: number;
  receipt_number: string;
  account_number: string;
  amount: number;
  method: 'manual' | 'payhere';
  paid_at: string;
  receipt_url: string;
};

defineProps<{
  payments: Paginated<MyPaymentRow>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'My payments',
        href: index(),
      },
    ],
  },
});

const methodLabels: Record<MyPaymentRow['method'], string> = {
  manual: 'Office receipt',
  payhere: 'Online',
};

function formatCurrency(amount: number): string {
  const formatted = Math.abs(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return amount < 0 ? `Rs ${formatted} CR` : `Rs ${formatted}`;
}
</script>

<template>
  <Head title="My payments" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="My payments"
        description="Payments recorded against your accounts"
      />
    </div>

    <div
      v-if="payments.data.length === 0"
      class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-16 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-muted"
      >
        <Banknote class="size-6 text-muted-foreground" />
      </div>
      <div class="space-y-1">
        <p class="font-medium">No payments yet</p>
        <p class="max-w-sm text-sm text-muted-foreground">
          Payments made at the office or online will appear here.
        </p>
      </div>
    </div>

    <div v-else class="flex flex-col gap-3">
      <Card v-for="payment in payments.data" :key="payment.id">
        <CardContent class="flex items-center justify-between gap-4 py-4">
          <div class="flex flex-col gap-1.5">
            <p class="font-medium">{{ payment.receipt_number }}</p>
            <p class="text-sm text-muted-foreground">
              {{ payment.paid_at }} · {{ payment.account_number }} ·
              {{ methodLabels[payment.method] }} ·
              {{ formatCurrency(payment.amount) }}
            </p>
          </div>
          <Button variant="outline" size="sm" as-child>
            <a :href="payment.receipt_url" target="_blank" rel="noopener">
              <Download class="size-4" />
              Receipt
            </a>
          </Button>
        </CardContent>
      </Card>
    </div>

    <Pagination :paginator="payments" />
  </div>
</template>
