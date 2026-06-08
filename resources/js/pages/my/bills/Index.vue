<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import BillStatusBadge from '@/components/BillStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { BillStatus } from '@/lib/bills';
import { pdf } from '@/routes/bills';
import { index } from '@/routes/my/bills';
import type { Paginated } from '@/types';

type MyBillRow = {
  id: number;
  bill_number: string;
  month_label: string;
  account_number: string;
  total_due: number;
  status: BillStatus;
  due_date: string;
  is_reissue: boolean;
};

defineProps<{
  bills: Paginated<MyBillRow>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'My bills',
        href: index(),
      },
    ],
  },
});

function formatCurrency(amount: number): string {
  const formatted = Math.abs(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return amount < 0 ? `Rs ${formatted} CR` : `Rs ${formatted}`;
}
</script>

<template>
  <Head title="My bills" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="My bills"
        description="Monthly water bills for your accounts"
      />
    </div>

    <div
      v-if="bills.data.length === 0"
      class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-16 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-muted"
      >
        <Receipt class="size-6 text-muted-foreground" />
      </div>
      <div class="space-y-1">
        <p class="font-medium">No bills yet</p>
        <p class="max-w-sm text-sm text-muted-foreground">
          Bills appear here after each meter reading.
        </p>
      </div>
    </div>

    <div v-else class="flex flex-col gap-3">
      <a
        v-for="bill in bills.data"
        :key="bill.id"
        :href="pdf(bill.id).url"
        target="_blank"
        rel="noopener"
      >
        <Card class="transition-colors hover:bg-muted/50">
          <CardContent class="flex flex-col gap-1.5 py-4">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <p class="font-medium">{{ bill.bill_number }}</p>
                <Badge v-if="bill.is_reissue" variant="secondary">
                  Reissue
                </Badge>
              </div>
              <BillStatusBadge :status="bill.status" />
            </div>
            <p class="text-sm text-muted-foreground">
              {{ bill.month_label }} · {{ bill.account_number }} ·
              {{ formatCurrency(bill.total_due) }} · Due {{ bill.due_date }}
            </p>
          </CardContent>
        </Card>
      </a>
    </div>

    <Pagination :paginator="bills" />
  </div>
</template>
