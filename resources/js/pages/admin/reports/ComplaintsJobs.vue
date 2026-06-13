<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ReportExportButtons from '@/components/admin/reports/ReportExportButtons.vue';
import ReportPeriodFilter from '@/components/admin/reports/ReportPeriodFilter.vue';
import ReportStatTile from '@/components/admin/reports/ReportStatTile.vue';
import SortableHead from '@/components/admin/SortableHead.vue';
import ComplaintStatusBadge from '@/components/ComplaintStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import JobStatusBadge from '@/components/JobStatusBadge.vue';
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import {
  complaintCategoryLabels,
  complaintStatusLabels,
} from '@/lib/complaints';
import type { ComplaintCategory, ComplaintStatus } from '@/lib/complaints';
import { jobStatusLabels } from '@/lib/jobs';
import type { MaintenanceJobStatus } from '@/lib/jobs';
import type { ReportPeriodFilters } from '@/lib/reports';
import { csv as csvUrl, index, pdf as pdfUrl } from '@/routes/admin/reports/jobs';
import type { DatatableFilters, Paginated } from '@/types';

type JobRow = {
  id: number;
  job_number: string;
  title: string;
  complaint_number: string | null;
  assignees: string;
  scheduled_date: string;
  completed_at: string | null;
  status: MaintenanceJobStatus;
};

type ComplaintRow = {
  id: number;
  complaint_number: string;
  subject: string;
  category: ComplaintCategory;
  member: string;
  submitted_at: string;
  status: ComplaintStatus;
  closed_at: string | null;
};

const props = defineProps<{
  period: ReportPeriodFilters;
  reportFilters: {
    date_basis: 'scheduled' | 'completed';
    job_status: string | null;
    complaint_status: string | null;
    category: string | null;
    assignee: number | null;
  };
  jobStatuses: MaintenanceJobStatus[];
  complaintStatuses: ComplaintStatus[];
  categories: ComplaintCategory[];
  assignees: { id: number; name: string }[];
  summary: {
    complaint_status_counts: Record<string, number>;
    job_status_counts: Record<string, number>;
    avg_resolution_days: number;
  };
  jobs: Paginated<JobRow>;
  complaints: ComplaintRow[];
  filters: DatatableFilters;
  exportParams: Record<string, string | number>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Complaint and Maintenance Job Report', href: index() },
    ],
  },
});

const { search, sort, direction, sortBy } = useDatatable(
  index().url,
  props.filters,
);

const dateBasis = ref(props.reportFilters.date_basis);
const jobStatus = ref(props.reportFilters.job_status ?? 'all');
const complaintStatus = ref(props.reportFilters.complaint_status ?? 'all');
const category = ref(props.reportFilters.category ?? 'all');
const assignee = ref(
  props.reportFilters.assignee === null
    ? 'all'
    : String(props.reportFilters.assignee),
);

const reload = (params: Record<string, string | number | null>) => {
  router.get(
    index().url,
    Object.fromEntries(
      Object.entries({
        period_type: props.period.period_type,
        month: props.period.month,
        quarter: props.period.quarter,
        year: props.period.year,
        from: props.period.from,
        to: props.period.to,
        date_basis: dateBasis.value,
        job_status: jobStatus.value === 'all' ? null : jobStatus.value,
        complaint_status:
          complaintStatus.value === 'all' ? null : complaintStatus.value,
        category: category.value === 'all' ? null : category.value,
        assignee: assignee.value === 'all' ? null : assignee.value,
        ...params,
      }).filter(([, value]) => value !== null && value !== ''),
    ),
    { preserveState: true, preserveScroll: true },
  );
};

watch([dateBasis, jobStatus, complaintStatus, category, assignee], () =>
  reload({}),
);

const periodExtraParams = {
  date_basis: dateBasis.value,
  job_status: jobStatus.value === 'all' ? null : jobStatus.value,
  complaint_status:
    complaintStatus.value === 'all' ? null : complaintStatus.value,
  category: category.value === 'all' ? null : category.value,
  assignee: assignee.value === 'all' ? null : assignee.value,
};

const exportQuery = new URLSearchParams(
  Object.entries(props.exportParams).map(([key, value]) => [
    key,
    String(value),
  ]),
).toString();
</script>

<template>
  <Head title="Complaint and Maintenance Job Report" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="Complaint and Maintenance Job"
        :description="`Service record for ${period.label}`"
      />
      <ReportExportButtons
        :pdf-url="`${pdfUrl().url}?${exportQuery}`"
        :csv-url="`${csvUrl().url}?${exportQuery}`"
      />
    </div>

    <div class="flex flex-wrap items-end gap-3">
      <ReportPeriodFilter
        :action="index().url"
        :filters="period"
        :extra-params="periodExtraParams"
      />

      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">Job date basis</Label>
        <Select v-model="dateBasis">
          <SelectTrigger class="w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="scheduled">Scheduled date</SelectItem>
            <SelectItem value="completed">Completed date</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">Job status</Label>
        <Select v-model="jobStatus">
          <SelectTrigger class="w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All job statuses</SelectItem>
            <SelectItem
              v-for="option in jobStatuses"
              :key="option"
              :value="option"
            >
              {{ jobStatusLabels[option] }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">Complaint status</Label>
        <Select v-model="complaintStatus">
          <SelectTrigger class="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All complaint statuses</SelectItem>
            <SelectItem
              v-for="option in complaintStatuses"
              :key="option"
              :value="option"
            >
              {{ complaintStatusLabels[option] }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">Category</Label>
        <Select v-model="category">
          <SelectTrigger class="w-36">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All categories</SelectItem>
            <SelectItem
              v-for="option in categories"
              :key="option"
              :value="option"
            >
              {{ complaintCategoryLabels[option] }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-1.5">
        <Label class="text-xs text-muted-foreground">Assignee</Label>
        <Select v-model="assignee">
          <SelectTrigger class="w-44">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All assignees</SelectItem>
            <SelectItem
              v-for="option in assignees"
              :key="option.id"
              :value="String(option.id)"
            >
              {{ option.name }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <ReportStatTile
        v-for="(count, name) in summary.complaint_status_counts"
        :key="`complaint-${name}`"
        :label="`Complaints ${String(name).replace('_', ' ')}`"
        :value="count"
      />
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <ReportStatTile
        v-for="(count, name) in summary.job_status_counts"
        :key="`job-${name}`"
        :label="`Jobs ${String(name).replace('_', ' ')}`"
        :value="count"
      />
      <ReportStatTile
        label="Avg. resolution time"
        :value="`${summary.avg_resolution_days} days`"
      />
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-medium">Job completion log</h3>
        <Input
          v-model="search"
          type="search"
          placeholder="Search job number or title..."
          class="w-64"
        />
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
              <TableHead>Completed</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="job in jobs.data" :key="job.id">
              <TableCell class="font-medium">{{ job.job_number }}</TableCell>
              <TableCell>{{ job.title }}</TableCell>
              <TableCell class="text-muted-foreground">
                {{ job.complaint_number ?? '—' }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ job.assignees || '—' }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ job.scheduled_date }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ job.completed_at ?? '—' }}
              </TableCell>
              <TableCell>
                <JobStatusBadge :status="job.status" />
              </TableCell>
            </TableRow>
            <TableEmpty v-if="jobs.data.length === 0" :colspan="7">
              No jobs in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>

    <Pagination :paginator="jobs" />

    <div class="flex flex-col gap-2">
      <h3 class="text-sm font-medium">Complaints in period</h3>
      <div class="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Complaint</TableHead>
              <TableHead>Subject</TableHead>
              <TableHead>Category</TableHead>
              <TableHead>Member</TableHead>
              <TableHead>Submitted</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Closed</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="complaint in complaints" :key="complaint.id">
              <TableCell class="font-medium">
                {{ complaint.complaint_number }}
              </TableCell>
              <TableCell>{{ complaint.subject }}</TableCell>
              <TableCell class="text-muted-foreground">
                {{ complaintCategoryLabels[complaint.category] }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ complaint.member }}
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ complaint.submitted_at }}
              </TableCell>
              <TableCell>
                <ComplaintStatusBadge :status="complaint.status" />
              </TableCell>
              <TableCell class="text-muted-foreground">
                {{ complaint.closed_at ?? '—' }}
              </TableCell>
            </TableRow>
            <TableEmpty v-if="complaints.length === 0" :colspan="7">
              No complaints in this period.
            </TableEmpty>
          </TableBody>
        </Table>
      </div>
    </div>
  </div>
</template>
