<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';
import { TableHead } from '@/components/ui/table';

const props = defineProps<{
  column: string;
  sort: string | null;
  direction: 'asc' | 'desc' | null;
}>();

const emit = defineEmits<{
  sort: [column: string];
}>();

const isActive = () => props.sort === props.column;
</script>

<template>
  <TableHead>
    <button
      type="button"
      class="flex items-center gap-1.5 font-medium hover:text-foreground"
      :class="isActive() ? 'text-foreground' : ''"
      @click="emit('sort', column)"
    >
      <slot />
      <ArrowUp v-if="isActive() && direction === 'asc'" class="size-3.5" />
      <ArrowDown
        v-else-if="isActive() && direction === 'desc'"
        class="size-3.5"
      />
      <ArrowUpDown v-else class="size-3.5 opacity-50" />
    </button>
  </TableHead>
</template>
