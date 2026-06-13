<script setup lang="ts">
import {
  Card,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

defineProps<{
  label: string;
  value: string | number;
  hint?: string;
}>();
</script>

<template>
  <Card class="gap-1 py-4">
    <CardHeader class="px-4">
      <CardDescription class="text-xs uppercase tracking-wide">
        {{ label }}
      </CardDescription>
      <CardTitle class="text-2xl tabular-nums">{{ value }}</CardTitle>
      <CardDescription v-if="hint" class="text-xs">
        {{ hint }}
      </CardDescription>
    </CardHeader>
  </Card>
</template>
