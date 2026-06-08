<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { billStatusDotClasses, billStatusLabels } from '@/lib/bills';
import type { BillStatus } from '@/lib/bills';

defineProps<{
  status: BillStatus;
}>();
</script>

<template>
  <Badge variant="outline" class="gap-1.5">
    <span class="size-1.5 rounded-full" :class="billStatusDotClasses[status]" />
    {{ billStatusLabels[status] }}
  </Badge>
</template>
