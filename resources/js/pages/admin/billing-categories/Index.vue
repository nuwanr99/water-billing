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
import { create, edit, index } from '@/routes/admin/billing-categories';
import type { DatatableFilters, Paginated } from '@/types';

type BillingCategoryRow = {
  id: number;
  name: string;
  description: string | null;
  late_fee_percent: number;
  is_active: boolean;
  tiers_count: number;
  water_accounts_count: number;
};

const props = defineProps<{
  billingCategories: Paginated<BillingCategoryRow>;
  filters: DatatableFilters;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Billing categories',
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
  <Head title="Billing categories" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Billing categories"
        description="Tariff categories and their consumption slabs"
      />
      <Button v-if="hasPermission('tariffs.manage')" as-child>
        <Link :href="create()">
          <Plus class="size-4" />
          New billing category
        </Link>
      </Button>
    </div>

    <SearchFilter v-model="search" placeholder="Search by name..." />

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
            <TableHead>Description</TableHead>
            <SortableHead
              column="late_fee_percent"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Late fee
            </SortableHead>
            <TableHead>Slabs</TableHead>
            <TableHead>Accounts</TableHead>
            <SortableHead
              column="is_active"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Status
            </SortableHead>
            <TableHead class="w-0">
              <span class="sr-only">Actions</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="billingCategory in billingCategories.data"
            :key="billingCategory.id"
          >
            <TableCell class="font-medium">
              {{ billingCategory.name }}
            </TableCell>
            <TableCell class="max-w-64 truncate text-muted-foreground">
              {{ billingCategory.description ?? '—' }}
            </TableCell>
            <TableCell class="tabular-nums">
              {{ billingCategory.late_fee_percent.toFixed(2) }}%
            </TableCell>
            <TableCell class="tabular-nums">
              {{ billingCategory.tiers_count }}
            </TableCell>
            <TableCell class="tabular-nums">
              {{ billingCategory.water_accounts_count }}
            </TableCell>
            <TableCell>
              <Badge :variant="billingCategory.is_active ? 'default' : 'secondary'">
                {{ billingCategory.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </TableCell>
            <TableCell>
              <Button
                v-if="hasPermission('tariffs.manage')"
                variant="outline"
                size="sm"
                as-child
              >
                <Link :href="edit(billingCategory.id)"> Edit </Link>
              </Button>
            </TableCell>
          </TableRow>
          <TableEmpty v-if="billingCategories.data.length === 0" :colspan="7">
            No billing categories found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="billingCategories" />
  </div>
</template>
