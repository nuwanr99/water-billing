<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
  ArrowLeftRight,
  BookOpen,
  Droplets,
  FolderGit2,
  KeyRound,
  LayoutGrid,
  ShieldCheck,
  Users,
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
import { index as permissionsIndex } from '@/routes/admin/permissions';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import { index as adminWaterAccountsIndex } from '@/routes/admin/water-accounts';
import { index as waterAccountsIndex } from '@/routes/water-accounts';
import type { NavItem } from '@/types';

const { hasPermission } = usePermission();

const page = usePage();
const inAdminArea = computed(() => page.url.startsWith('/admin'));

const mainNavItems = computed<NavItem[]>(() =>
  inAdminArea.value
    ? []
    : [
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
      ],
);

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

  return items;
});

const footerNavItems: NavItem[] = [
  {
    title: 'Repository',
    href: 'https://github.com/laravel/vue-starter-kit',
    icon: FolderGit2,
  },
  {
    title: 'Documentation',
    href: 'https://laravel.com/docs/starter-kits#vue',
    icon: BookOpen,
  },
];
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
      <SidebarMenu v-if="hasPermission('admin')">
        <SidebarMenuItem>
          <SidebarMenuButton
            as-child
            :tooltip="
              inAdminArea ? 'Switch to user view' : 'Switch to admin view'
            "
          >
            <Link :href="inAdminArea ? dashboard() : adminDashboard()">
              <ArrowLeftRight />
              <span>
                {{
                  inAdminArea ? 'Switch to user view' : 'Switch to admin view'
                }}
              </span>
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
      <NavFooter :items="footerNavItems" />
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
