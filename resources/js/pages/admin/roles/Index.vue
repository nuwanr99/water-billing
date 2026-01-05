<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { create, edit, index } from '@/routes/admin/roles';
import type { Paginated } from '@/types';

type RoleRow = {
    id: number;
    name: string;
    permissions_count: number;
    created_at: string | null;
};

defineProps<{
    roles: Paginated<RoleRow>;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Roles',
                href: index(),
            },
        ],
    },
});

const { hasPermission } = usePermission();
</script>

<template>
    <Head title="Roles" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Heading
                variant="small"
                title="Roles"
                description="Manage roles and the permissions they grant"
            />
            <Button v-if="hasPermission('roles.create')" as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    New role
                </Link>
            </Button>
        </div>

        <SearchFilter
            :initial="filters.search"
            :url="index().url"
            placeholder="Search roles..."
        />

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Permissions</TableHead>
                        <TableHead>Created</TableHead>
                        <TableHead class="w-0">
                            <span class="sr-only">Actions</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="role in roles.data" :key="role.id">
                        <TableCell class="font-medium">
                            {{ role.name }}
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary">
                                {{ role.permissions_count }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ role.created_at }}
                        </TableCell>
                        <TableCell>
                            <Button
                                v-if="hasPermission('roles.edit')"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="edit(role.id)">Edit</Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="roles.data.length === 0" :colspan="4">
                        No roles found.
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="roles" />
    </div>
</template>
