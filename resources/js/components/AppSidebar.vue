<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    FolderGit2,
    KeyRound,
    LayoutGrid,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
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
import type { NavItem } from '@/types';

const { hasPermission } = usePermission();

const mainNavItems = computed<NavItem[]>(() =>
    hasPermission('admin')
        ? []
        : [
              {
                  title: 'Dashboard',
                  href: dashboard(),
                  icon: LayoutGrid,
              },
          ],
);

const adminNavItems = computed<NavItem[]>(() => {
    if (!hasPermission('admin')) {
        return [];
    }

    const items: NavItem[] = [
        { title: 'Dashboard', href: adminDashboard(), icon: LayoutGrid },
    ];

    if (hasPermission('users.view')) {
        items.push({ title: 'Users', href: usersIndex(), icon: Users });
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
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
