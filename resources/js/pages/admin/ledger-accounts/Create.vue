<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SystemLedgerAccountForm from '@/components/admin/SystemLedgerAccountForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index } from '@/routes/admin/ledger-accounts';

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Chart of accounts',
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
  <Head title="Create ledger account" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Create ledger account"
      description="Add an account to the system ledger's chart of accounts"
    />

    <SystemLedgerAccountForm mode="create" />
  </div>
</template>
