<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import JobStatusBadge from '@/components/JobStatusBadge.vue';
import { Card, CardContent } from '@/components/ui/card';
import type { MaintenanceJobStatus } from '@/lib/jobs';
import { index, show } from '@/routes/my-jobs';

type MyJobRow = {
  id: number;
  job_number: string;
  title: string;
  status: MaintenanceJobStatus;
  complaint_number: string | null;
  scheduled_date: string;
};

defineProps<{
  jobs: MyJobRow[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'My jobs',
        href: index(),
      },
    ],
  },
});
</script>

<template>
  <Head title="My jobs" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="My jobs"
      description="Work assigned to you"
    />

    <div
      v-if="jobs.length === 0"
      class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-16 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-muted"
      >
        <ClipboardList class="size-6 text-muted-foreground" />
      </div>
      <div class="space-y-1">
        <p class="font-medium">No jobs assigned</p>
        <p class="max-w-sm text-sm text-muted-foreground">
          When the office assigns you a maintenance job, it will show up here.
        </p>
      </div>
    </div>

    <div v-else class="flex flex-col gap-3">
      <Link v-for="job in jobs" :key="job.id" :href="show(job.id)">
        <Card class="transition-colors hover:bg-muted/50">
          <CardContent class="flex flex-col gap-1.5 py-4">
            <div class="flex items-center justify-between gap-2">
              <p class="font-medium">{{ job.title }}</p>
              <JobStatusBadge :status="job.status" />
            </div>
            <p class="text-sm text-muted-foreground">
              {{ job.job_number }} · scheduled {{ job.scheduled_date }}
              <template v-if="job.complaint_number">
                · {{ job.complaint_number }}
              </template>
            </p>
          </CardContent>
        </Card>
      </Link>
    </div>
  </div>
</template>
