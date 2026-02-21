<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { create, index, store } from '@/routes/admin/system-ledger';

type AccountType = 'asset' | 'liability' | 'equity' | 'income' | 'expense';

type JournalLine = {
  system_ledger_account_id: number | null;
  debit: number | string;
  credit: number | string;
  description: string;
};

defineProps<{
  accounts: {
    id: number;
    code: string;
    name: string;
    type: AccountType;
  }[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'System ledger',
        href: index(),
      },
      {
        title: 'New entry',
        href: create(),
      },
    ],
  },
});

const typeClasses: Record<AccountType, string> = {
  asset:
    'border-transparent bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
  liability:
    'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
  equity:
    'border-transparent bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300',
  income:
    'border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
  expense:
    'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

const todayIso = new Date().toLocaleDateString('en-CA');

const emptyLine = (): JournalLine => ({
  system_ledger_account_id: null,
  debit: '',
  credit: '',
  description: '',
});

const form = useForm<{
  description: string;
  entry_date: string;
  lines: JournalLine[];
}>({
  description: '',
  entry_date: todayIso,
  lines: [emptyLine(), emptyLine()],
});

form.transform((data) => ({
  ...data,
  lines: data.lines.map((line) => ({
    system_ledger_account_id: line.system_ledger_account_id,
    debit: line.debit === '' ? null : line.debit,
    credit: line.credit === '' ? null : line.credit,
    description: line.description === '' ? null : line.description,
  })),
}));

/**
 * A line is either a debit or a credit — typing into one side clears the
 * other so a line can never carry both amounts.
 */
const onDebitInput = (line: JournalLine): void => {
  if (line.debit !== '' && line.debit !== null) {
    line.credit = '';
  }
};

const onCreditInput = (line: JournalLine): void => {
  if (line.credit !== '' && line.credit !== null) {
    line.debit = '';
  }
};

const addLine = (): void => {
  form.lines.push(emptyLine());
};

const removeLine = (lineIndex: number): void => {
  if (form.lines.length > 2) {
    form.lines.splice(lineIndex, 1);
  }
};

/**
 * Totals are summed in cents to avoid floating-point drift when deciding
 * whether the journal balances.
 */
const toCents = (value: number | string): number => {
  const amount = Number(value);

  return Number.isFinite(amount) && amount > 0 ? Math.round(amount * 100) : 0;
};

const totalDebitCents = computed(() =>
  form.lines.reduce((sum, line) => sum + toCents(line.debit), 0),
);

const totalCreditCents = computed(() =>
  form.lines.reduce((sum, line) => sum + toCents(line.credit), 0),
);

const isBalanced = computed(
  () =>
    totalDebitCents.value > 0 &&
    totalDebitCents.value === totalCreditCents.value,
);

const differenceCents = computed(() =>
  Math.abs(totalDebitCents.value - totalCreditCents.value),
);

const formatCents = (cents: number): string =>
  `Rs ${(cents / 100).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

const lineError = (lineIndex: number, field: string): string | undefined =>
  (form.errors as Record<string, string>)[`lines.${lineIndex}.${field}`];

const submit = (): void => {
  form.post(store().url);
};
</script>

<template>
  <Head title="New journal entry" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="New journal entry"
      description="Record a manual journal — opening balances, corrections, donations"
    />

    <form class="max-w-5xl space-y-6" @submit.prevent="submit">
      <p class="max-w-3xl text-xs text-muted-foreground">
        Debits must equal credits. Money in: debit the cash/bank account, credit
        an income account. Money out: debit an expense account, credit
        cash/bank.
      </p>

      <div class="grid max-w-3xl gap-6 sm:grid-cols-2">
        <div class="grid gap-2">
          <Label for="description">Description</Label>
          <Input
            id="description"
            v-model="form.description"
            required
            placeholder="e.g. Opening balances as at 1 Jan"
          />
          <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-2">
          <Label for="entry_date">Entry date</Label>
          <Input
            id="entry_date"
            v-model="form.entry_date"
            type="date"
            :max="todayIso"
            required
            class="max-w-48"
          />
          <InputError :message="form.errors.entry_date" />
        </div>
      </div>

      <div class="space-y-3">
        <Label>Lines</Label>

        <div class="overflow-x-auto rounded-xl border">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b bg-muted/50 text-left">
                <th class="min-w-64 p-3 font-medium">Account</th>
                <th class="p-3 font-medium">Debit (Rs)</th>
                <th class="p-3 font-medium">Credit (Rs)</th>
                <th class="p-3 font-medium">Note (optional)</th>
                <th class="w-0 p-3">
                  <span class="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(line, lineIndex) in form.lines"
                :key="lineIndex"
                class="border-b last:border-0"
              >
                <td class="p-3 align-top">
                  <Select v-model="line.system_ledger_account_id">
                    <SelectTrigger
                      class="w-full"
                      :aria-label="`Line ${lineIndex + 1} account`"
                    >
                      <SelectValue placeholder="Select an account" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="account in accounts"
                        :key="account.id"
                        :value="account.id"
                      >
                        <span class="tabular-nums">{{ account.code }}</span>
                        — {{ account.name }}
                        <Badge :class="typeClasses[account.type]">
                          {{ account.type }}
                        </Badge>
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <InputError
                    class="mt-1"
                    :message="lineError(lineIndex, 'system_ledger_account_id')"
                  />
                </td>
                <td class="p-3 align-top">
                  <Input
                    v-model="line.debit"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    class="max-w-32 tabular-nums"
                    :aria-label="`Line ${lineIndex + 1} debit`"
                    @update:model-value="onDebitInput(line)"
                  />
                  <InputError
                    class="mt-1"
                    :message="lineError(lineIndex, 'debit')"
                  />
                </td>
                <td class="p-3 align-top">
                  <Input
                    v-model="line.credit"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    class="max-w-32 tabular-nums"
                    :aria-label="`Line ${lineIndex + 1} credit`"
                    @update:model-value="onCreditInput(line)"
                  />
                  <InputError
                    class="mt-1"
                    :message="lineError(lineIndex, 'credit')"
                  />
                </td>
                <td class="p-3 align-top">
                  <Input
                    v-model="line.description"
                    class="min-w-40"
                    placeholder="Line note"
                    :aria-label="`Line ${lineIndex + 1} note`"
                  />
                  <InputError
                    class="mt-1"
                    :message="lineError(lineIndex, 'description')"
                  />
                </td>
                <td class="p-3 align-top">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="form.lines.length <= 2"
                    :aria-label="`Remove line ${lineIndex + 1}`"
                    @click="removeLine(lineIndex)"
                  >
                    <Trash2 class="size-4" />
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <InputError :message="form.errors.lines" />

        <div class="flex flex-wrap items-center justify-between gap-3">
          <Button type="button" variant="outline" size="sm" @click="addLine">
            <Plus class="size-4" />
            Add line
          </Button>

          <div class="flex flex-wrap items-center gap-4 text-sm">
            <span>
              Debits:
              <span class="font-medium tabular-nums">
                {{ formatCents(totalDebitCents) }}
              </span>
            </span>
            <span>
              Credits:
              <span class="font-medium tabular-nums">
                {{ formatCents(totalCreditCents) }}
              </span>
            </span>
            <Badge
              v-if="isBalanced"
              class="border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300"
            >
              Balanced
            </Badge>
            <Badge
              v-else
              class="border-transparent bg-amber-100 text-amber-800 tabular-nums dark:bg-amber-950 dark:text-amber-300"
            >
              Out of balance: {{ formatCents(differenceCents) }}
            </Badge>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing || !isBalanced">
          Post journal entry
        </Button>
        <Button variant="secondary" as-child>
          <Link :href="index()">Cancel</Link>
        </Button>
      </div>
    </form>
  </div>
</template>
