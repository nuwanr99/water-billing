<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MaintenanceJobForm from '@/components/admin/MaintenanceJobForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index } from '@/routes/admin/maintenance-jobs';

defineProps<{
  complaint: { id: number; complaint_number: string; subject: string } | null;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Maintenance jobs', href: index() },
      { title: 'New job', href: create() },
    ],
  },
});
</script>

<template>
  <Head title="New maintenance job" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="New maintenance job"
      description="Create a work order and assign it to one or more people"
    />

    <MaintenanceJobForm mode="create" :complaint="complaint" />
  </div>
</template>
