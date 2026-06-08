<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import AccountSummaryCard from '@/components/meter-readings/AccountSummaryCard.vue';
import MeterReadingForm from '@/components/meter-readings/MeterReadingForm.vue';
import { index, store } from '@/routes/meter-readings';
import type { MeterReadingAccountSummary, PreviousReading } from '@/types';

defineProps<{
  account: MeterReadingAccountSummary;
  previous: PreviousReading;
  monthLabel: string;
}>();

defineOptions({
  layout: { breadcrumbs: [{ title: 'Meter readings', href: index() }] },
});
</script>

<template>
  <Head :title="`New reading — ${account.account_number}`" />

  <div class="mx-auto flex w-full max-w-md flex-1 flex-col gap-6 pt-3">
    <div class="px-4">
      <Heading variant="small" :title="`New reading · ${monthLabel}`" />
    </div>

    <div class="px-4">
      <AccountSummaryCard :account="account" />
    </div>

    <MeterReadingForm
      mode="create"
      :previous="previous"
      :action="store(account.id)"
    />
  </div>
</template>
