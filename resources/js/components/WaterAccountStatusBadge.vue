<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { WaterAccountStatus } from '@/types';

defineProps<{
  status: WaterAccountStatus;
}>();
</script>

<template>
  <Badge variant="outline" class="gap-1.5">
    <span
      class="size-1.5 rounded-full"
      :class="status === 'active' ? 'bg-emerald-500' : 'bg-muted-foreground'"
    />
    {{ status === 'active' ? 'Active' : 'Inactive' }}
  </Badge>
</template>
