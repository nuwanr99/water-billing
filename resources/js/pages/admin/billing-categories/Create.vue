<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BillingCategoryForm from '@/components/admin/BillingCategoryForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index } from '@/routes/admin/billing-categories';

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Billing categories',
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
  <Head title="Create billing category" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Create billing category"
      description="Define a tariff category and its consumption slabs"
    />

    <BillingCategoryForm mode="create" />
  </div>
</template>
