<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/admin/ledger-accounts';

type AccountType = 'asset' | 'liability' | 'equity' | 'income' | 'expense';

type LedgerAccountFormData = {
  id: number;
  code: string;
  name: string;
  type: AccountType;
  is_active: boolean;
  lines_count: number;
};

const props = defineProps<{
  mode: 'create' | 'edit';
  account?: LedgerAccountFormData;
}>();

const form = useForm<{
  code: string;
  name: string;
  type: AccountType | '';
  is_active: boolean;
}>({
  code: props.account?.code ?? '',
  name: props.account?.name ?? '',
  type: props.account?.type ?? '',
  is_active: props.account?.is_active ?? true,
});

const typeLabels: Record<AccountType, string> = {
  asset: 'Asset',
  liability: 'Liability',
  equity: 'Equity',
  income: 'Income',
  expense: 'Expense',
};

/**
 * Once journal lines reference the account, its code and type define what
 * historical journals mean, so they are frozen.
 */
const isFrozen = computed(
  () => props.mode === 'edit' && (props.account?.lines_count ?? 0) > 0,
);

const activeState = computed({
  get: () => (form.is_active ? 'active' : 'inactive'),
  set: (value: string) => {
    form.is_active = value === 'active';
  },
});

const submit = () => {
  if (props.mode === 'create') {
    form.post(store().url);

    return;
  }

  form.put(update(props.account!.id).url);
};
</script>

<template>
  <form class="max-w-3xl space-y-6" @submit.prevent="submit">
    <div class="grid gap-6 sm:grid-cols-2">
      <div class="grid gap-2">
        <Label for="code">Code</Label>
        <Input
          id="code"
          v-model="form.code"
          required
          inputmode="numeric"
          placeholder="1000"
          :disabled="isFrozen"
        />
        <InputError :message="form.errors.code" />
      </div>

      <div class="grid gap-2">
        <Label for="name">Name</Label>
        <Input id="name" v-model="form.name" required placeholder="Cash" />
        <InputError :message="form.errors.name" />
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="type">Type</Label>
      <Select v-model="form.type" :disabled="isFrozen">
        <SelectTrigger id="type" class="w-full sm:max-w-64">
          <SelectValue placeholder="Select a type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="(label, value) in typeLabels"
            :key="value"
            :value="value"
          >
            {{ label }}
          </SelectItem>
        </SelectContent>
      </Select>
      <p class="text-xs text-muted-foreground">
        Asset accounts say where money is (cash, bank); income and expense
        accounts say why it moved.
      </p>
      <InputError :message="form.errors.type" />
    </div>

    <p v-if="isFrozen" class="text-xs text-muted-foreground">
      This account has journal entries; code and type are frozen.
    </p>

    <div class="grid gap-2">
      <Label for="status">Status</Label>
      <Select v-model="activeState">
        <SelectTrigger id="status" class="w-full sm:max-w-64">
          <SelectValue placeholder="Select a status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="active">Active</SelectItem>
          <SelectItem value="inactive">Inactive</SelectItem>
        </SelectContent>
      </Select>
      <p class="text-xs text-muted-foreground">
        Inactive accounts cannot be used on new journal entries.
      </p>
      <InputError :message="form.errors.is_active" />
    </div>

    <div class="flex items-center gap-4">
      <Button type="submit" :disabled="form.processing">
        {{ mode === 'create' ? 'Create account' : 'Save changes' }}
      </Button>
      <Button variant="secondary" as-child>
        <Link :href="index()">Cancel</Link>
      </Button>
    </div>
  </form>
</template>
