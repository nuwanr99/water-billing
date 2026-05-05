<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MaintenanceJobForm from '@/components/admin/MaintenanceJobForm.vue';
import Heading from '@/components/Heading.vue';
import { index } from '@/routes/admin/maintenance-jobs';

defineProps<{
  job: {
    id: number;
    title: string;
    description: string;
    scheduled_date: string;
    complaint_number: string | null;
    assignees: { id: number; name: string }[];
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Maintenance jobs', href: index() }],
  },
});
</script>

<template>
  <Head title="Edit maintenance job" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Edit maintenance job"
      :description="`Reschedule, reassign, or revise the work order`"
    />

    <MaintenanceJobForm mode="edit" :job="job" />
  </div>
</template>
