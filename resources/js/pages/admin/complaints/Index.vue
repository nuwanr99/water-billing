<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import ComplaintStatusBadge from '@/components/ComplaintStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
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
import { complaintCategoryLabels } from '@/lib/complaints';
import type { ComplaintCategory, ComplaintStatus } from '@/lib/complaints';
import { index, show } from '@/routes/admin/complaints';
import type { DatatableFilters, Paginated } from '@/types';

type AdminComplaintRow = {
  id: number;
  complaint_number: string;
  subject: string;
  category: ComplaintCategory;
  status: ComplaintStatus;
  member: string;
  account_number: string | null;
  handlers: string[];
  submitted_at: string;
};

const props = defineProps<{
  complaints: Paginated<AdminComplaintRow>;
  filters: DatatableFilters;
  statusFilter: string | null;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Complaints',
        href: index(),
      },
    ],
  },
});

const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

const statusFilter = ref(props.statusFilter ?? 'all');

const onStatusChange = (value: unknown): void => {
  const status = typeof value === 'string' ? value : 'all';

  router.get(
    index().url,
    {
      search: search.value || undefined,
      status: status === 'all' ? undefined : status,
    },
    { preserveState: true, replace: true },
  );
};
</script>

<template>
  <Head title="Complaints" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Complaints"
      description="Review and respond to member complaints"
    />

    <div class="flex flex-wrap items-center gap-4">
      <SearchFilter
        v-model="search"
        placeholder="Search by number, subject, or member..."
      />

      <Select v-model="statusFilter" @update:model-value="onStatusChange">
        <SelectTrigger class="w-40">
          <SelectValue placeholder="All statuses" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All</SelectItem>
          <SelectItem value="open">Open</SelectItem>
          <SelectItem value="in_progress">In progress</SelectItem>
          <SelectItem value="closed">Closed</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="rounded-xl border">
      <Table>
        <TableHeader>
          <TableRow>
            <SortableHead
              column="complaint_number"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Complaint
            </SortableHead>
            <TableHead>Member</TableHead>
            <TableHead>Subject</TableHead>
            <TableHead>Category</TableHead>
            <TableHead>Handlers</TableHead>
            <SortableHead
              column="status"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Status
            </SortableHead>
            <SortableHead
              column="submitted_at"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Submitted
            </SortableHead>
            <TableHead class="w-0">
              <span class="sr-only">Actions</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="complaint in complaints.data" :key="complaint.id">
            <TableCell class="font-medium">
              {{ complaint.complaint_number }}
            </TableCell>
            <TableCell>{{ complaint.member }}</TableCell>
            <TableCell class="max-w-64 truncate">
              {{ complaint.subject }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ complaintCategoryLabels[complaint.category] }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ complaint.handlers.join(', ') || '—' }}
            </TableCell>
            <TableCell>
              <ComplaintStatusBadge :status="complaint.status" />
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ complaint.submitted_at }}
            </TableCell>
            <TableCell>
              <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="show(complaint.id)">View</Link>
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableEmpty v-if="complaints.data.length === 0" :colspan="8">
            No complaints found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="complaints" />
  </div>
</template>
