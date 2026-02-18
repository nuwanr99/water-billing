<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/water-accounts';
import { store } from '@/routes/admin/water-accounts/charges';

const props = defineProps<{
  account: {
    id: number;
    owner_name: string;
    account_number: string;
    balance: number;
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Water accounts',
        href: index(),
      },
      {
        title: 'Add charge',
        href: '#',
      },
    ],
  },
});

const chargeTypes = [
  { value: 'charge', label: 'Charge (one-off / repair)' },
  { value: 'penalty', label: 'Penalty' },
  { value: 'adjustment', label: 'Adjustment (± / credit)' },
];

/**
 * Ledger balances are member-owes when positive; negative balances are
 * credit and display as "CR" amounts.
 */
const formatBalance = (value: number): string => {
  const formatted = Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return value < 0 ? `Rs ${formatted} CR` : `Rs ${formatted}`;
};

const form = useForm<{
  type: string;
  amount: number | string;
  description: string;
}>({
  type: 'charge',
  amount: '',
  description: '',
});

const submit = () => {
  form.post(store(props.account.id).url);
};
</script>

<template>
  <Head :title="`Add charge — ${account.account_number}`" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Add charge"
      description="Record a one-off charge, penalty, or adjustment on the account ledger"
    />

    <Card class="max-w-2xl">
      <CardContent>
        <dl class="grid gap-4 sm:grid-cols-3">
          <div>
            <dt class="text-xs text-muted-foreground">Member</dt>
            <dd class="text-sm font-medium">{{ account.owner_name }}</dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Account number</dt>
            <dd class="text-sm font-medium">{{ account.account_number }}</dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Current balance</dt>
            <dd class="text-sm font-medium tabular-nums">
              {{ formatBalance(account.balance) }}
            </dd>
          </div>
        </dl>
      </CardContent>
    </Card>

    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
      <div class="grid gap-2">
        <Label for="type">Type</Label>
        <Select v-model="form.type">
          <SelectTrigger id="type" class="w-full">
            <SelectValue placeholder="Select a charge type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="chargeType in chargeTypes"
              :key="chargeType.value"
              :value="chargeType.value"
            >
              {{ chargeType.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p class="text-xs text-muted-foreground">
          Adjustments may be negative — a negative amount is a credit that
          reduces what the member owes.
        </p>
        <InputError :message="form.errors.type" />
      </div>

      <div class="grid gap-2">
        <Label for="amount">Amount (Rs)</Label>
        <Input
          id="amount"
          v-model="form.amount"
          type="number"
          step="0.01"
          required
          class="max-w-48"
          placeholder="0.00"
        />
        <InputError :message="form.errors.amount" />
      </div>

      <div class="grid gap-2">
        <Label for="description">Description</Label>
        <Input
          id="description"
          v-model="form.description"
          required
          placeholder="e.g. Meter box repair"
        />
        <InputError :message="form.errors.description" />
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing">
          Record charge
        </Button>
        <Button variant="secondary" as-child>
          <Link :href="index()">Cancel</Link>
        </Button>
      </div>
    </form>
  </div>
</template>
