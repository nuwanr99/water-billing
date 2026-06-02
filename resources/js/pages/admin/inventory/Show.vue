<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { HandCoins, Pencil, TriangleAlert } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { edit, index } from '@/routes/admin/inventory';
import { create as recordMovement } from '@/routes/admin/inventory/movements';
import { show as jobShow } from '@/routes/admin/maintenance-jobs';
import type { Paginated } from '@/types';

type MovementRow = {
  id: number;
  type: 'purchase' | 'usage' | 'adjustment';
  type_label: string;
  quantity: number;
  unit_rate: number | null;
  note: string | null;
  job_id: number | null;
  job_number: string | null;
  moved_by: string | null;
  moved_at: string;
};

defineProps<{
  item: {
    id: number;
    name: string;
    unit: string;
    unit_rate: number;
    quantity_in_stock: number;
    reorder_level: number;
    is_low_stock: boolean;
    is_active: boolean;
  };
  movements: Paginated<MovementRow>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inventory', href: index() },
      { title: '', href: '#' },
    ],
  },
});

const formatRs = (value: number): string =>
  `Rs ${value.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

const signedQuantity = (value: number): string =>
  `${value > 0 ? '+' : ''}${value}`;

const typeVariant = (
  type: MovementRow['type'],
): 'secondary' | 'destructive' | 'outline' =>
  type === 'purchase'
    ? 'secondary'
    : type === 'usage'
      ? 'destructive'
      : 'outline';
</script>

<template>
  <Head :title="item.name" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight">{{ item.name }}</h2>
          <Badge :variant="item.is_active ? 'secondary' : 'outline'">
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </Badge>
        </div>
        <p class="text-sm text-muted-foreground">
          Measured in {{ item.unit }} · reorder at {{ item.reorder_level }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Button size="sm" as-child>
          <Link :href="recordMovement(item.id)">
            <HandCoins class="size-4" />
            Record movement
          </Link>
        </Button>
        <Button variant="outline" size="sm" as-child>
          <Link :href="edit(item.id)">
            <Pencil class="size-4" />
            Edit
          </Link>
        </Button>
      </div>
    </div>

    <div
      v-if="item.is_low_stock"
      class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
    >
      <TriangleAlert class="size-4 shrink-0" />
      Stock is at or below the reorder level — time to restock.
    </div>

    <Card class="max-w-2xl">
      <CardContent>
        <dl class="grid gap-4 sm:grid-cols-3">
          <div>
            <dt class="text-xs text-muted-foreground">On hand</dt>
            <dd class="text-2xl font-semibold tabular-nums">
              {{ item.quantity_in_stock }}
              <span class="text-sm font-normal text-muted-foreground">
                {{ item.unit }}
              </span>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Reorder level</dt>
            <dd class="text-sm font-medium tabular-nums">
              {{ item.reorder_level }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Unit rate</dt>
            <dd class="text-sm font-medium tabular-nums">
              {{ formatRs(item.unit_rate) }}
            </dd>
          </div>
        </dl>
      </CardContent>
    </Card>

    <div class="space-y-3">
      <Heading variant="small" title="Movement history" />

      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Date</TableHead>
              <TableHead>Type</TableHead>
              <TableHead class="text-right">Quantity</TableHead>
              <TableHead>Job</TableHead>
              <TableHead>Note</TableHead>
              <TableHead>By</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="movement in movements.data" :key="movement.id">
              <TableCell class="text-muted-foreground">
                {{ movement.moved_at }}
              </TableCell>
              <TableCell>
                <Badge :variant="typeVariant(movement.type)">
                  {{ movement.type_label }}
                </Badge>
              </TableCell>
              <TableCell
                class="text-right font-medium tabular-nums"
                :class="movement.quantity < 0 ? 'text-destructive' : ''"
              >
                {{ signedQuantity(movement.quantity) }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                <Link
                  v-if="movement.job_id"
                  :href="jobShow(movement.job_id)"
                  class="text-primary underline-offset-4 hover:underline"
                >
                  {{ movement.job_number }}
                </Link>
                <span v-else>—</span>
              </TableCell>
              <TableCell class="max-w-64 truncate text-muted-foreground">
                {{ movement.note ?? '—' }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ movement.moved_by ?? '—' }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="movements.data.length === 0" :colspan="6">
              No stock movements yet.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>

      <Pagination :paginator="movements" />
    </div>
  </div>
</template>
