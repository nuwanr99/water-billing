<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import WaterAccountForm from '@/components/admin/WaterAccountForm.vue';
import Heading from '@/components/Heading.vue';
import { index } from '@/routes/admin/water-accounts';

type WaterAccountFormData = {
  id: number;
  account_number: string;
  meter_number: string;
  initial_reading: number;
  connection_address: string | null;
  status: string;
  connected_at: string | null;
  owner: { id: number; name: string; email: string | null };
};

defineProps<{
  waterAccount: WaterAccountFormData;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Water accounts',
        href: index(),
      },
      {
        title: 'Edit',
        href: '#',
      },
    ],
  },
});
</script>

<template>
  <Head :title="`Edit ${waterAccount.account_number}`" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      :title="`Edit ${waterAccount.account_number}`"
      description="Update the water connection details or its owner"
    />

    <WaterAccountForm mode="edit" :water-account="waterAccount" />
  </div>
</template>
