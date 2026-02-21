<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { store as storeTransfer } from '@/routes/admin/system-ledger/transfer';

const props = defineProps<{
  accounts: {
    id: number;
    code: string;
    name: string;
  }[];
  preselectedFromId?: number | null;
}>();

const open = ref(false);

const todayIso = new Date().toLocaleDateString('en-CA');

const form = useForm<{
  from_account_id: number | null;
  to_account_id: number | null;
  amount: number | string;
  entry_date: string;
  description: string;
}>({
  from_account_id: props.preselectedFromId ?? null,
  to_account_id: null,
  amount: '',
  entry_date: todayIso,
  description: '',
});

form.transform((data) => ({
  ...data,
  description: data.description === '' ? null : data.description,
}));

const isSameAccount = computed(
  () =>
    form.from_account_id !== null &&
    form.from_account_id === form.to_account_id,
);

const swapDirection = (): void => {
  [form.from_account_id, form.to_account_id] = [
    form.to_account_id,
    form.from_account_id,
  ];
};

const resetForm = (): void => {
  form.clearErrors();
  form.reset();
};

const submit = (): void => {
  form.post(storeTransfer().url, {
    preserveScroll: true,
    onSuccess: () => {
      open.value = false;
      resetForm();
    },
  });
};
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <slot />
    </DialogTrigger>
    <DialogContent>
      <form class="space-y-6" @submit.prevent="submit">
        <DialogHeader class="space-y-3">
          <DialogTitle>Transfer between accounts</DialogTitle>
          <DialogDescription>
            Moves money between the society's cash and bank accounts. Posts a
            balanced journal automatically.
          </DialogDescription>
        </DialogHeader>

        <div class="grid gap-2">
          <Label for="from_account_id">From account</Label>
          <Select v-model="form.from_account_id">
            <SelectTrigger id="from_account_id" class="w-full">
              <SelectValue placeholder="Select the source account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="account in accounts"
                :key="account.id"
                :value="account.id"
              >
                <span class="tabular-nums">{{ account.code }}</span>
                — {{ account.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="form.errors.from_account_id" />
        </div>

        <div class="flex justify-center">
          <Button
            type="button"
            variant="outline"
            size="icon"
            aria-label="Swap direction"
            @click="swapDirection"
          >
            <ArrowUpDown class="size-4" />
          </Button>
        </div>

        <div class="grid gap-2">
          <Label for="to_account_id">To account</Label>
          <Select v-model="form.to_account_id">
            <SelectTrigger id="to_account_id" class="w-full">
              <SelectValue placeholder="Select the destination account" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="account in accounts"
                :key="account.id"
                :value="account.id"
              >
                <span class="tabular-nums">{{ account.code }}</span>
                — {{ account.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <InputError :message="form.errors.to_account_id" />
          <p v-if="isSameAccount" class="text-sm text-amber-600">
            The source and destination accounts must differ.
          </p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
          <div class="grid gap-2">
            <Label for="amount">Amount (Rs)</Label>
            <Input
              id="amount"
              v-model="form.amount"
              type="number"
              min="0.01"
              step="0.01"
              required
              placeholder="0.00"
              class="tabular-nums"
            />
            <InputError :message="form.errors.amount" />
          </div>

          <div class="grid gap-2">
            <Label for="entry_date">Date</Label>
            <Input
              id="entry_date"
              v-model="form.entry_date"
              type="date"
              :max="todayIso"
              required
            />
            <InputError :message="form.errors.entry_date" />
          </div>
        </div>

        <div class="grid gap-2">
          <Label for="description">Note (optional)</Label>
          <Input
            id="description"
            v-model="form.description"
            placeholder="e.g. Weekly cash banking"
          />
          <InputError :message="form.errors.description" />
        </div>

        <DialogFooter class="gap-2">
          <DialogClose as-child>
            <Button type="button" variant="secondary" @click="resetForm">
              Cancel
            </Button>
          </DialogClose>
          <Button type="submit" :disabled="form.processing">
            Post transfer
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
