<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, ScrollText } from '@lucide/vue';
import WaterAccountForm from '@/components/admin/WaterAccountForm.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/composables/usePermission';
import { index, statement } from '@/routes/admin/water-accounts';
import { create as createCharge } from '@/routes/admin/water-accounts/charges';

type WaterAccountFormData = {
  id: number;
  billing_category_id: number | null;
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
  billingCategories: { id: number; name: string }[];
}>();

const { hasPermission } = usePermission();

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
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        :title="`Edit ${waterAccount.account_number}`"
        description="Update the water connection details or its owner"
      />
      <div class="flex flex-wrap items-center gap-2">
        <Button v-if="hasPermission('ledger.view')" variant="outline" as-child>
          <Link :href="statement(waterAccount.id)">
            <ScrollText class="size-4" />
            Ledger statement
          </Link>
        </Button>
        <Button
          v-if="hasPermission('ledger.record-charge')"
          variant="outline"
          as-child
        >
          <Link :href="createCharge(waterAccount.id)">
            <Plus class="size-4" />
            Add charge
          </Link>
        </Button>
      </div>
    </div>

    <WaterAccountForm
      mode="edit"
      :water-account="waterAccount"
      :billing-categories="billingCategories"
    />
  </div>
</template>
