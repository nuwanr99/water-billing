<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/composables/usePermission';
import { dashboard } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';

type RecentUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    created_at: string | null;
};

defineProps<{
    stats: {
        users: number;
        roles: number;
        permissions: number;
    };
    recentUsers: RecentUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin dashboard',
                href: dashboard(),
            },
        ],
    },
});

const { hasPermission } = usePermission();
</script>

<template>
    <Head title="Admin dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            title="Admin dashboard"
            description="An overview of the application's users, roles, and permissions"
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription>Users</CardDescription>
                    <CardTitle class="text-3xl tabular-nums">
                        {{ stats.users }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Roles</CardDescription>
                    <CardTitle class="text-3xl tabular-nums">
                        {{ stats.roles }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Permissions</CardDescription>
                    <CardTitle class="text-3xl tabular-nums">
                        {{ stats.permissions }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <Heading
                    variant="small"
                    title="Recent users"
                    description="The latest accounts created in the application"
                />
                <Button
                    v-if="hasPermission('users.view')"
                    variant="outline"
                    as-child
                >
                    <Link :href="usersIndex()">View all users</Link>
                </Button>
            </div>

            <div class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Roles</TableHead>
                            <TableHead>Created</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in recentUsers" :key="user.id">
                            <TableCell class="font-medium">
                                {{ user.name }}
                            </TableCell>
                            <TableCell>{{ user.email }}</TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="role in user.roles"
                                        :key="role"
                                        variant="secondary"
                                    >
                                        {{ role }}
                                    </Badge>
                                    <span
                                        v-if="user.roles.length === 0"
                                        class="text-muted-foreground"
                                        >&mdash;</span
                                    >
                                </div>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ user.created_at }}
                            </TableCell>
                        </TableRow>
                        <TableEmpty
                            v-if="recentUsers.length === 0"
                            :colspan="4"
                        >
                            No users yet.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
