<script setup lang="ts">
import { Link, useForm, useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import SearchableCombobox from '@/components/SearchableCombobox.vue';
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
import { index, owners, store, update } from '@/routes/admin/water-accounts';
import type { ComboboxOption } from '@/types';

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

type OwnerResult = {
  id: number;
  name: string;
  email: string | null;
};

const props = defineProps<{
  mode: 'create' | 'edit';
  waterAccount?: WaterAccountFormData;
  suggestedAccountNumber?: string;
  billingCategories: { id: number; name: string }[];
}>();

const selectedOwner = ref<ComboboxOption | null>(
  props.waterAccount
    ? {
        value: props.waterAccount.owner.id,
        label: props.waterAccount.owner.name,
        description: props.waterAccount.owner.email,
      }
    : null,
);

const form = useForm<{
  user_id: number | null;
  billing_category_id: string;
  account_number: string;
  meter_number: string;
  initial_reading: number | string;
  connection_address: string;
  status: string;
  connected_at: string;
}>({
  user_id: props.waterAccount?.owner.id ?? null,
  billing_category_id:
    props.waterAccount?.billing_category_id?.toString() ?? '',
  account_number:
    props.waterAccount?.account_number ?? props.suggestedAccountNumber ?? '',
  meter_number: props.waterAccount?.meter_number ?? '',
  initial_reading: props.waterAccount?.initial_reading ?? 0,
  connection_address: props.waterAccount?.connection_address ?? '',
  status: props.waterAccount?.status ?? 'active',
  connected_at: props.waterAccount?.connected_at ?? '',
});

watch(selectedOwner, (owner) => {
  form.user_id = owner?.value ?? null;
});

const searchTerm = ref('');
const ownerOptions = ref<ComboboxOption[]>(
  selectedOwner.value ? [selectedOwner.value] : [],
);

const ownerSearch = useHttp<{ search: string }, OwnerResult[]>({ search: '' });

const fetchOwners = () => {
  ownerSearch.search = searchTerm.value;

  ownerSearch.get(owners().url, {
    onSuccess: (response) => {
      ownerOptions.value = (response ?? []).map((owner): ComboboxOption => ({
        value: owner.id,
        label: owner.name,
        description: owner.email,
      }));
    },
  });
};

onMounted(fetchOwners);
watchDebounced(searchTerm, fetchOwners, { debounce: 300 });

const submit = () => {
  if (props.mode === 'create') {
    form.post(store().url);

    return;
  }

  form.put(update(props.waterAccount!.id).url);
};
</script>

<template>
  <form class="max-w-2xl space-y-6" @submit.prevent="submit">
    <div class="grid gap-2">
      <Label for="owner">Owner</Label>
      <SearchableCombobox
        id="owner"
        v-model="selectedOwner"
        v-model:search-term="searchTerm"
        :options="ownerOptions"
        :loading="ownerSearch.processing"
        placeholder="Search members by name or email..."
        empty-text="No members found."
      />
      <InputError :message="form.errors.user_id" />
    </div>

    <div class="grid gap-2">
      <Label for="billing_category">Billing category</Label>
      <Select v-model="form.billing_category_id">
        <SelectTrigger id="billing_category" class="w-full">
          <SelectValue placeholder="Select a billing category" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="billingCategory in billingCategories"
            :key="billingCategory.id"
            :value="billingCategory.id.toString()"
          >
            {{ billingCategory.name }}
          </SelectItem>
        </SelectContent>
      </Select>
      <p class="text-xs text-muted-foreground">
        Determines the tariff slabs and service charge the account is billed
        under.
      </p>
      <InputError :message="form.errors.billing_category_id" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
      <div class="grid gap-2">
        <Label for="account_number">Account number</Label>
        <Input
          id="account_number"
          v-model="form.account_number"
          required
          placeholder="ACC-0001"
        />
        <InputError :message="form.errors.account_number" />
      </div>

      <div class="grid gap-2">
        <Label for="meter_number">Meter number</Label>
        <Input
          id="meter_number"
          v-model="form.meter_number"
          required
          placeholder="MTR-000001"
        />
        <InputError :message="form.errors.meter_number" />
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="initial_reading">Initial meter reading</Label>
      <Input
        id="initial_reading"
        v-model="form.initial_reading"
        type="number"
        min="0"
        step="0.01"
        required
        class="max-w-48"
      />
      <p class="text-xs text-muted-foreground">
        {{
          mode === 'create'
            ? 'The value on the meter dial when the connection is installed.'
            : 'Changing the baseline re-derives the first recorded reading’s usage.'
        }}
      </p>
      <InputError :message="form.errors.initial_reading" />
    </div>

    <div class="grid gap-2">
      <Label for="connection_address"> Connection address (optional) </Label>
      <Input
        id="connection_address"
        v-model="form.connection_address"
        placeholder="Leave empty to use the member's address"
      />
      <InputError :message="form.errors.connection_address" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
      <div class="grid gap-2">
        <Label for="status">Status</Label>
        <Select v-model="form.status">
          <SelectTrigger id="status" class="w-full">
            <SelectValue placeholder="Select a status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="form.errors.status" />
      </div>

      <div class="grid gap-2">
        <Label for="connected_at">Connected on (optional)</Label>
        <Input id="connected_at" v-model="form.connected_at" type="date" />
        <InputError :message="form.errors.connected_at" />
      </div>
    </div>

    <div class="flex items-center gap-4">
      <Button type="submit" :disabled="form.processing">
        {{ mode === 'create' ? 'Create water account' : 'Save changes' }}
      </Button>
      <Button variant="secondary" as-child>
        <Link :href="index()">Cancel</Link>
      </Button>
    </div>
  </form>
</template>
