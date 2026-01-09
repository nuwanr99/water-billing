<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Droplets, List } from '@lucide/vue';
import { computed } from 'vue';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index, switchMethod } from '@/routes/water-accounts';
import type { SharedWaterAccount } from '@/types';

const page = usePage();

const accounts = computed(() => page.props.waterAccounts ?? []);
const currentAccount = computed(
  () =>
    accounts.value.find(
      (account) => account.id === page.props.currentWaterAccountId,
    ) ?? null,
);

const switchAccount = (account: SharedWaterAccount) => {
  if (account.id === currentAccount.value?.id) {
    return;
  }

  router.post(switchMethod(account.id).url, {}, { preserveScroll: true });
};
</script>

<template>
  <SidebarMenu v-if="accounts.length > 0">
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <SidebarMenuButton
            size="lg"
            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
          >
            <div
              class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"
            >
              <Droplets class="size-4" />
            </div>
            <div class="grid flex-1 text-left text-sm leading-tight">
              <span class="truncate font-medium">
                {{ currentAccount?.account_number ?? 'Water account' }}
              </span>
              <span class="truncate text-xs text-muted-foreground">
                Water account
              </span>
            </div>
            <ChevronsUpDown class="ml-auto size-4" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          class="w-(--reka-dropdown-menu-trigger-width) min-w-56"
          align="start"
        >
          <DropdownMenuLabel class="text-xs text-muted-foreground">
            Water accounts
          </DropdownMenuLabel>
          <DropdownMenuItem
            v-for="account in accounts"
            :key="account.id"
            class="gap-2"
            @click="switchAccount(account)"
          >
            <div class="grid flex-1 leading-tight">
              <span class="truncate">
                {{ account.account_number }}
                <span
                  v-if="account.status === 'inactive'"
                  class="text-xs text-muted-foreground"
                >
                  (inactive)
                </span>
              </span>
              <span
                v-if="account.connection_address"
                class="truncate text-xs text-muted-foreground"
              >
                {{ account.connection_address }}
              </span>
            </div>
            <Check
              v-if="account.id === currentAccount?.id"
              class="size-4 shrink-0"
            />
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem as-child>
            <Link :href="index()" class="w-full">
              <List class="size-4" />
              View all accounts
            </Link>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  </SidebarMenu>
</template>
