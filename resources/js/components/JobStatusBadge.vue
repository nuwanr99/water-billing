<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { jobStatusClasses, jobStatusLabels } from '@/lib/jobs';
import type { MaintenanceJobStatus } from '@/lib/jobs';

defineProps<{
  status: MaintenanceJobStatus;
}>();
</script>

<template>
  <Badge :class="jobStatusClasses[status]">
    {{ jobStatusLabels[status] }}
  </Badge>
</template>
