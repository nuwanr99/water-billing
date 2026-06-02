<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, TriangleAlert } from '@lucide/vue';
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
import { create, index, show } from '@/routes/admin/inventory';
import type { DatatableFilters, Paginated } from '@/types';

type InventoryRow = {
  id: number;
  name: string;
  unit: string;
  unit_rate: number;
  quantity_in_stock: number;
  reorder_level: number;
  is_low_stock: boolean;
  is_active: boolean;
};

const props = defineProps<{
  items: Paginated<InventoryRow>;
  filters: DatatableFilters;
  lowStockOnly: boolean;
  lowStockCount: number;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Inventory', href: index() }],
  },
});

const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

const toggleLowStock = (): void => {
  router.get(
    index().url,
    {
      search: search.value || undefined,
      low_stock: props.lowStockOnly ? undefined : 1,
    },
    { preserveState: true, replace: true },
  );
};

const formatRs = (value: number): string =>
  `Rs ${value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
</script>

<template>
  <Head title="Inventory" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Inventory"
        description="Track stocked materials and their on-hand quantities"
      />
      <Button as-child>
        <Link :href="create()">
          <Plus class="size-4" />
          New item
        </Link>
      </Button>
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <SearchFilter v-model="search" placeholder="Search by name or unit..." />

      <Button
        :variant="lowStockOnly ? 'default' : 'outline'"
        size="sm"
        @click="toggleLowStock"
      >
        <TriangleAlert class="size-4" />
        Low stock
        <Badge v-if="lowStockCount > 0" variant="secondary">
          {{ lowStockCount }}
        </Badge>
      </Button>
    </div>

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
              Item
            </SortableHead>
            <TableHead>Unit</TableHead>
            <SortableHead
              column="quantity_in_stock"
              :sort="sort"
              :direction="direction"
              class="text-right"
              @sort="sortBy"
            >
              In stock
            </SortableHead>
            <SortableHead
              column="reorder_level"
              :sort="sort"
              :direction="direction"
              class="text-right"
              @sort="sortBy"
            >
              Reorder at
            </SortableHead>
            <SortableHead
              column="unit_rate"
              :sort="sort"
              :direction="direction"
              class="text-right"
              @sort="sortBy"
            >
              Unit rate
            </SortableHead>
            <TableHead>Status</TableHead>
            <TableHead class="w-0">
              <span class="sr-only">Actions</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="item in items.data" :key="item.id">
            <TableCell class="font-medium">{{ item.name }}</TableCell>
            <TableCell class="text-muted-foreground">{{ item.unit }}</TableCell>
            <TableCell class="text-right tabular-nums">
              <span class="inline-flex items-center gap-1.5">
                {{ item.quantity_in_stock }}
                <Badge v-if="item.is_low_stock" variant="destructive"
                  >Low</Badge
                >
              </span>
            </TableCell>
            <TableCell class="text-right text-muted-foreground tabular-nums">
              {{ item.reorder_level }}
            </TableCell>
            <TableCell class="text-right text-muted-foreground tabular-nums">
              {{ formatRs(item.unit_rate) }}
            </TableCell>
            <TableCell>
              <Badge :variant="item.is_active ? 'secondary' : 'outline'">
                {{ item.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </TableCell>
            <TableCell>
              <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="show(item.id)">View</Link>
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableEmpty v-if="items.data.length === 0" :colspan="7">
            No inventory items found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="items" />
  </div>
</template>
