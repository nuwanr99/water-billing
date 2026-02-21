<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DeleteConfirmDialog from '@/components/admin/DeleteConfirmDialog.vue';
import SystemLedgerAccountForm from '@/components/admin/SystemLedgerAccountForm.vue';
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { destroy, index } from '@/routes/admin/ledger-accounts';

defineProps<{
  account: {
    id: number;
    code: string;
    name: string;
    type: 'asset' | 'liability' | 'equity' | 'income' | 'expense';
    is_active: boolean;
    lines_count: number;
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Chart of accounts',
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
  <Head :title="`Edit ${account.name}`" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      :title="`Edit ${account.code} — ${account.name}`"
      description="Update the account's details. Code and type are frozen once journal entries reference it."
    />

    <SystemLedgerAccountForm mode="edit" :account="account" />

    <Separator class="max-w-3xl" />

    <div class="max-w-3xl space-y-4">
      <Heading
        variant="small"
        title="Delete ledger account"
        :description="
          account.lines_count > 0
            ? `This account is referenced by ${account.lines_count} journal line(s) and cannot be deleted. Deactivate it to stop new entries.`
            : 'This will permanently delete the account from the chart of accounts.'
        "
      />

      <DeleteConfirmDialog
        v-if="account.lines_count === 0"
        :url="destroy(account.id).url"
        :title="`Delete ${account.name}?`"
        description="This will permanently delete the ledger account. This action cannot be undone."
        :confirm-name="account.name"
        trigger-label="Delete ledger account"
      />
    </div>
  </div>
</template>
