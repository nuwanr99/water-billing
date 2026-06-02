<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
  ArrowLeftRight,
  Banknote,
  BookOpenText,
  ClipboardList,
  Droplets,
  Gauge,
  HandCoins,
  KeyRound,
  LayoutGrid,
  ListTree,
  MessageSquareWarning,
  Package,
  Receipt,
  ReceiptText,
  Settings,
  ShieldCheck,
  Users,
  Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import AccountSwitcher from '@/components/AccountSwitcher.vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermission } from '@/composables/usePermission';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as billingCategoriesIndex } from '@/routes/admin/billing-categories';
import { index as adminBillsIndex } from '@/routes/admin/bills';
import { index as adminComplaintsIndex } from '@/routes/admin/complaints';
import { index as adminExpensesIndex } from '@/routes/admin/expenses';
import { index as adminInventoryIndex } from '@/routes/admin/inventory';
import { index as ledgerAccountsIndex } from '@/routes/admin/ledger-accounts';
import { index as adminMaintenanceJobsIndex } from '@/routes/admin/maintenance-jobs';
import { index as adminPaymentsIndex } from '@/routes/admin/payments';
import { index as permissionsIndex } from '@/routes/admin/permissions';
import { index as rolesIndex } from '@/routes/admin/roles';
import { edit as notificationSettings } from '@/routes/admin/settings/notifications';
import { index as systemLedgerIndex } from '@/routes/admin/system-ledger';
import { index as usersIndex } from '@/routes/admin/users';
import { index as adminWaterAccountsIndex } from '@/routes/admin/water-accounts';
import { index as meterReadingsIndex } from '@/routes/meter-readings';
import { index as myComplaintsIndex } from '@/routes/my/complaints';
import { index as myJobsIndex } from '@/routes/my-jobs';
import { index as waterAccountsIndex } from '@/routes/water-accounts';
import type { NavItem } from '@/types';

const { hasPermission } = usePermission();

const page = usePage();
const inAdminArea = computed(() => page.url.startsWith('/admin'));

const mainNavItems = computed<NavItem[]>(() => {
  if (inAdminArea.value) {
    return [];
  }

  const items: NavItem[] = [
    {
      title: 'Dashboard',
      href: dashboard(),
      icon: LayoutGrid,
    },
    {
      title: 'My water accounts',
      href: waterAccountsIndex(),
      icon: Droplets,
    },
  ];

  if (hasPermission('readings.view')) {
    items.push({
      title: 'Meter readings',
      href: meterReadingsIndex(),
      icon: Gauge,
    });
  }

  if (hasPermission('complaints.view-own')) {
    items.push({
      title: 'My complaints',
      href: myComplaintsIndex(),
      icon: MessageSquareWarning,
    });
  }

  if (hasPermission('maintenance-jobs.view-assigned')) {
    items.push({
      title: 'My jobs',
      href: myJobsIndex(),
      icon: ClipboardList,
    });
  }

  return items;
});

const adminNavItems = computed<NavItem[]>(() => {
  if (!hasPermission('admin') || !inAdminArea.value) {
    return [];
  }

  const items: NavItem[] = [
    { title: 'Dashboard', href: adminDashboard(), icon: LayoutGrid },
  ];

  if (hasPermission('users.view')) {
    items.push({ title: 'Users', href: usersIndex(), icon: Users });
  }

  if (hasPermission('water-accounts.view')) {
    items.push({
      title: 'Water accounts',
      href: adminWaterAccountsIndex(),
      icon: Droplets,
    });
  }

  if (hasPermission('tariffs.view')) {
    items.push({
      title: 'Billing categories',
      href: billingCategoriesIndex(),
      icon: ReceiptText,
    });
  }

  if (hasPermission('bills.view')) {
    items.push({ title: 'Bills', href: adminBillsIndex(), icon: Receipt });
  }

  if (hasPermission('payments.view-all')) {
    items.push({
      title: 'Payments',
      href: adminPaymentsIndex(),
      icon: Banknote,
    });
  }

  if (hasPermission('complaints.view-all')) {
    items.push({
      title: 'Complaints',
      href: adminComplaintsIndex(),
      icon: MessageSquareWarning,
    });
  }

  if (hasPermission('maintenance-jobs.view-all')) {
    items.push({
      title: 'Maintenance jobs',
      href: adminMaintenanceJobsIndex(),
      icon: Wrench,
    });
  }

  if (hasPermission('expenses.view')) {
    items.push({
      title: 'Expenses',
      href: adminExpensesIndex(),
      icon: HandCoins,
    });
  }

  if (hasPermission('inventory.view')) {
    items.push({
      title: 'Inventory',
      href: adminInventoryIndex(),
      icon: Package,
    });
  }

  if (hasPermission('system-ledger.view')) {
    items.push({
      title: 'System ledger',
      href: systemLedgerIndex(),
      icon: BookOpenText,
    });
    items.push({
      title: 'Ledger accounts',
      href: ledgerAccountsIndex(),
      icon: ListTree,
    });
  }

  if (hasPermission('roles.view')) {
    items.push({ title: 'Roles', href: rolesIndex(), icon: ShieldCheck });
  }

  if (hasPermission('permissions.view')) {
    items.push({
      title: 'Permissions',
      href: permissionsIndex(),
      icon: KeyRound,
    });
  }

  if (hasPermission('settings.manage')) {
    items.push({
      title: 'Settings',
      href: notificationSettings(),
      icon: Settings,
    });
  }

  return items;
});

const footerNavItems = computed<NavItem[]>(() =>
  hasPermission('admin')
    ? [
        {
          title: inAdminArea.value
            ? 'Switch to user view'
            : 'Switch to admin view',
          href: inAdminArea.value ? dashboard() : adminDashboard(),
          icon: ArrowLeftRight,
        },
      ]
    : [],
);
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <Link :href="inAdminArea ? adminDashboard() : dashboard()">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
      <AccountSwitcher v-if="!inAdminArea" />
    </SidebarHeader>

    <SidebarContent>
      <NavMain v-if="mainNavItems.length > 0" :items="mainNavItems" />
      <NavMain
        v-if="adminNavItems.length > 0"
        :items="adminNavItems"
        label="Admin"
      />
    </SidebarContent>

    <SidebarFooter>
      <NavFooter v-if="footerNavItems.length > 0" :items="footerNavItems" />
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
