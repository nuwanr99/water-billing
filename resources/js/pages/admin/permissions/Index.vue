<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useDatatable } from '@/composables/useDatatable';
import { index } from '@/routes/admin/permissions';
import type { DatatableFilters, Paginated } from '@/types';

type PermissionRow = {
    id: number;
    name: string;
    category: string;
    created_at: string | null;
};

const props = defineProps<{
    permissions: Paginated<PermissionRow>;
    filters: DatatableFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Permissions',
                href: index(),
            },
        ],
    },
});

const { search, sort, direction, sortBy } = useDatatable(
    index().url,
    props.filters,
);
</script>

<template>
    <Head title="Permissions" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            title="Permissions"
            description="Permissions are defined in code and grouped by category — assign them to roles from the Roles page"
        />

        <SearchFilter v-model="search" placeholder="Search permissions..." />

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <SortableHead
                            column="name"
                            :sort="sort"
                            :direction="direction"
                            @sort="sortBy"
                        >
                            Name
                        </SortableHead>
                        <SortableHead
                            column="category"
                            :sort="sort"
                            :direction="direction"
                            @sort="sortBy"
                        >
                            Category
                        </SortableHead>
                        <SortableHead
                            column="created_at"
                            :sort="sort"
                            :direction="direction"
                            @sort="sortBy"
                        >
                            Created
                        </SortableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="permission in permissions.data"
                        :key="permission.id"
                    >
                        <TableCell class="font-medium">
                            {{ permission.name }}
                        </TableCell>
                        <TableCell>
                            <Badge variant="secondary" class="capitalize">
                                {{ permission.category }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ permission.created_at }}
                        </TableCell>
                    </TableRow>
                    <TableEmpty
                        v-if="permissions.data.length === 0"
                        :colspan="3"
                    >
                        No permissions found.
                    </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="permissions" />
    </div>
</template>
