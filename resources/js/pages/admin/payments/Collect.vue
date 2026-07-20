<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, FileText, Gauge, SearchX } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import QrScanButton from '@/components/admin/QrScanButton.vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import Heading from '@/components/Heading.vue';
import { collect, index } from '@/routes/admin/payments';
import { show } from '@/routes/admin/payments/collect';

type AccountCard = {
  id: number;
  owner_name: string;
  account_number: string;
  meter_number: string;
  balance: number;
};

const props = defineProps<{
  accounts: AccountCard[];
  filters: { search: string | null };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Payments',
        href: index(),
      },
      {
        title: 'Collect',
        href: '#',
      },
    ],
  },
});

const search = ref(props.filters.search ?? '');

watchDebounced(
  search,
  () => {
    router.get(collect().url, search.value ? { search: search.value } : {}, {
      preserveState: true,
      replace: true,
    });
  },
  { debounce: 300 },
);


/**
 * Amounts render as "Rs 1,234.56" with two decimals; the sign is conveyed
 * by the surrounding Due / CR (credit) styling rather than a minus sign.
 */
const formatAmount = (value: number): string =>
  Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
</script>

<template>
  <Head title="Collect payments" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Collect payments"
      description="Find an account to review its balance and record a payment"
    />

    <div class="flex items-center gap-2">
      <SearchFilter
        v-model="search"
        placeholder="Name, account, or meter number..."
      />
      <QrScanButton context="payment" :search="search" />
    </div>

    <div
      v-if="accounts.length === 0"
      class="flex flex-col items-center gap-3 rounded-2xl border border-dashed py-14 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-muted"
      >
        <SearchX class="size-6 text-muted-foreground" />
      </div>
      <div class="space-y-1 px-6">
        <p class="font-medium">No accounts found</p>
        <p class="text-sm text-muted-foreground">
          Try a different name, account number, or meter number.
        </p>
      </div>
    </div>

    <ul v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
      <li v-for="account in accounts" :key="account.id">
        <Link
          :href="show(account.id).url"
          class="flex h-full items-center gap-3 rounded-2xl border bg-card p-4 transition-[background-color,transform] select-none hover:bg-accent/50 active:scale-[0.99] active:bg-accent/50"
        >
          <div class="flex min-w-0 flex-1 flex-col gap-1.5">
            <p class="truncate font-semibold">{{ account.owner_name }}</p>
            <div
              class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
            >
              <span class="flex items-center gap-1.5">
                <FileText class="size-3.5 shrink-0" />
                {{ account.account_number }}
              </span>
              <span class="flex items-center gap-1.5">
                <Gauge class="size-3.5 shrink-0" />
                {{ account.meter_number }}
              </span>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <div class="text-right">
              <p
                v-if="account.balance > 0"
                class="text-sm font-semibold text-red-600 tabular-nums dark:text-red-400"
              >
                Due Rs {{ formatAmount(account.balance) }}
              </p>
              <template v-else-if="account.balance < 0">
                <p
                  class="text-sm font-semibold text-emerald-600 tabular-nums dark:text-emerald-400"
                >
                  CR Rs {{ formatAmount(account.balance) }}
                </p>
                <p
                  class="mt-0.5 text-xs text-emerald-600/80 dark:text-emerald-400/80"
                >
                  (credit)
                </p>
              </template>
              <p v-else class="text-sm font-medium text-muted-foreground">
                Settled
              </p>
            </div>
            <ChevronRight class="size-4 text-muted-foreground" />
          </div>
        </Link>
      </li>
    </ul>
  </div>
</template>
