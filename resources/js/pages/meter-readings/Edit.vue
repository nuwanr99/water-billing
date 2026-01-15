<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AccountSummaryCard from '@/components/meter-readings/AccountSummaryCard.vue';
import MeterReadingForm from '@/components/meter-readings/MeterReadingForm.vue';
import { history, update } from '@/routes/meter-readings';
import type { MeterReadingAccountSummary, PreviousReading } from '@/types';

const props = defineProps<{
  account: MeterReadingAccountSummary;
  previous: PreviousReading;
  reading: { id: number; value: number; month: string };
}>();

setLayoutProps({
  title: `Edit reading · ${props.reading.month}`,
  backHref: history(props.account.id).url,
});
</script>

<template>
  <Head :title="`Edit reading — ${account.account_number}`" />

  <div class="flex flex-1 flex-col gap-6 pt-3">
    <div class="px-4">
      <AccountSummaryCard :account="account" />
    </div>

    <MeterReadingForm
      mode="edit"
      :previous="previous"
      :initial-value="reading.value"
      :action="update(reading.id)"
    />
  </div>
</template>
