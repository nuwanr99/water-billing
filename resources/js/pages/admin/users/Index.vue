<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
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
import { useDatatable } from '@/composables/useDatatable';
import { usePermission } from '@/composables/usePermission';
import { create, edit, index } from '@/routes/admin/users';
import type { DatatableFilters, Paginated } from '@/types';

type UserRow = {
  id: number;
  name: string;
  email: string;
  roles: string[];
  created_at: string | null;
};

const props = defineProps<{
  users: Paginated<UserRow>;
  filters: DatatableFilters;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Users',
        href: index(),
      },
    ],
  },
});

const { hasPermission } = usePermission();
const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);
</script>

<template>
  <Head title="Users" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Users"
        description="Manage user accounts and their roles"
      />
      <Button v-if="hasPermission('users.create')" as-child>
        <Link :href="create()">
          <Plus class="size-4" />
          New user
        </Link>
      </Button>
    </div>

    <SearchFilter v-model="search" placeholder="Search users..." />

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
              column="email"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Email
            </SortableHead>
            <TableHead>Roles</TableHead>
            <SortableHead
              column="created_at"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Created
            </SortableHead>
            <TableHead class="w-0">
              <span class="sr-only">Actions</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="user in users.data" :key="user.id">
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
            <TableCell>
              <Button
                v-if="hasPermission('users.edit')"
                variant="outline"
                size="sm"
                as-child
              >
                <Link :href="edit(user.id)">Edit</Link>
              </Button>
            </TableCell>
          </TableRow>
          <TableEmpty v-if="users.data.length === 0" :colspan="5">
            No users found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="users" />
  </div>
</template>
