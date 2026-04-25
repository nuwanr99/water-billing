<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
  complaintStatusClasses,
  complaintStatusLabels,
} from '@/lib/complaints';
import type { ComplaintStatus } from '@/lib/complaints';

defineProps<{
  status: ComplaintStatus;
}>();
</script>

<template>
  <Badge :class="complaintStatusClasses[status]">
    {{ complaintStatusLabels[status] }}
  </Badge>
</template>
