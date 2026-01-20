<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BillingCategoryForm from '@/components/admin/BillingCategoryForm.vue';
import DeleteConfirmDialog from '@/components/admin/DeleteConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { destroy, index } from '@/routes/admin/billing-categories';

defineProps<{
  billingCategory: {
    id: number;
    name: string;
    description: string | null;
    late_fee_percent: number;
    is_active: boolean;
    water_accounts_count: number;
    tiers: {
      lower_units: number;
      upper_units: number | null;
      rate_per_unit: number;
      service_charge: number;
    }[];
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Billing categories',
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
  <Head :title="`Edit ${billingCategory.name}`" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      :title="`Edit ${billingCategory.name}`"
      description="Update the category, its tariff slabs, and late fee. Existing bills keep the tariff they were generated with."
    />

    <BillingCategoryForm mode="edit" :billing-category="billingCategory" />

    <Separator class="max-w-3xl" />

    <div class="max-w-3xl space-y-4">
      <Heading
        variant="small"
        title="Delete billing category"
        :description="
          billingCategory.water_accounts_count > 0
            ? `This category is assigned to ${billingCategory.water_accounts_count} water account(s) and cannot be deleted. Deactivate it to stop new assignments.`
            : 'This will permanently delete the category and its tariff slabs.'
        "
      />

      <DeleteConfirmDialog
        v-if="billingCategory.water_accounts_count === 0"
        :url="destroy(billingCategory.id).url"
        :title="`Delete ${billingCategory.name}?`"
        description="This will permanently delete the billing category and its tariff slabs. This action cannot be undone."
        :confirm-name="billingCategory.name"
        trigger-label="Delete billing category"
      />
    </div>
  </div>
</template>
