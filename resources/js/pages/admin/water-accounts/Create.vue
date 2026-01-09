<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import WaterAccountForm from '@/components/admin/WaterAccountForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index } from '@/routes/admin/water-accounts';

defineProps<{
  suggestedAccountNumber: string;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Water accounts',
        href: index(),
      },
      {
        title: 'Create',
        href: create(),
      },
    ],
  },
});
</script>

<template>
  <Head title="Create water account" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Create water account"
      description="Register a new water connection and assign its owner"
    />

    <WaterAccountForm
      mode="create"
      :suggested-account-number="suggestedAccountNumber"
    />
  </div>
</template>
