<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AccountSummaryCard from '@/components/meter-readings/AccountSummaryCard.vue';
import MeterReadingForm from '@/components/meter-readings/MeterReadingForm.vue';
import { index, store } from '@/routes/meter-readings';
import type { MeterReadingAccountSummary, PreviousReading } from '@/types';

const props = defineProps<{
  account: MeterReadingAccountSummary;
  previous: PreviousReading;
  monthLabel: string;
}>();

setLayoutProps({
  title: `New reading · ${props.monthLabel}`,
  backHref: index().url,
});
</script>

<template>
  <Head :title="`New reading — ${account.account_number}`" />

  <div class="flex flex-1 flex-col gap-6 pt-3">
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
