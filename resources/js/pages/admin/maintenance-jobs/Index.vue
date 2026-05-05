<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import SearchFilter from '@/components/admin/SearchFilter.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import Heading from '@/components/Heading.vue';
import JobStatusBadge from '@/components/JobStatusBadge.vue';
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
import type { MaintenanceJobStatus } from '@/lib/jobs';
import { create, index, show } from '@/routes/admin/maintenance-jobs';
import type { DatatableFilters, Paginated } from '@/types';

type MaintenanceJobRow = {
  id: number;
  job_number: string;
  title: string;
  status: MaintenanceJobStatus;
  complaint_number: string | null;
  assignees: string[];
  scheduled_date: string;
};

const props = defineProps<{
  jobs: Paginated<MaintenanceJobRow>;
  filters: DatatableFilters;
  statusFilter: string | null;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Maintenance jobs',
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
  <Head title="Maintenance jobs" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Maintenance jobs"
        description="Track and manage maintenance work orders"
      />
      <Button as-child>
        <Link :href="create()">
          <Plus class="size-4" />
          New job
        </Link>
      </Button>
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <SearchFilter
        v-model="search"
        placeholder="Search by number or title..."
      />

      <Select v-model="statusFilter" @update:model-value="onStatusChange">
        <SelectTrigger class="w-40">
          <SelectValue placeholder="All statuses" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All</SelectItem>
          <SelectItem value="assigned">Assigned</SelectItem>
          <SelectItem value="in_progress">In progress</SelectItem>
          <SelectItem value="completed">Completed</SelectItem>
          <SelectItem value="cancelled">Cancelled</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="rounded-xl border">
      <Table>
        <TableHeader>
          <TableRow>
            <SortableHead
              column="job_number"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Job
            </SortableHead>
            <TableHead>Title</TableHead>
            <TableHead>Complaint</TableHead>
            <TableHead>Assignees</TableHead>
            <SortableHead
              column="scheduled_date"
              :sort="sort"
              :direction="direction"
              @sort="sortBy"
            >
              Scheduled
            </SortableHead>
            <SortableHead
              column="status"
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
          <TableRow v-for="job in jobs.data" :key="job.id">
            <TableCell class="font-medium">
              {{ job.job_number }}
            </TableCell>
            <TableCell class="max-w-64 truncate">
              {{ job.title }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ job.complaint_number ?? '—' }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ job.assignees.join(', ') || '—' }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ job.scheduled_date }}
            </TableCell>
            <TableCell>
              <JobStatusBadge :status="job.status" />
            </TableCell>
            <TableCell>
              <div class="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" as-child>
                  <Link :href="show(job.id)">View</Link>
                </Button>
              </div>
            </TableCell>
          </TableRow>
          <TableEmpty v-if="jobs.data.length === 0" :colspan="7">
            No maintenance jobs found.
          </TableEmpty>
        </TableBody>
      </Table>
    </div>

    <Pagination :paginator="jobs" />
  </div>
</template>
