<script setup lang="ts">
import { Search } from '@lucide/vue';
import { Input } from '@/components/ui/input';

const model = defineModel<string>({ required: true });

defineProps<{
  placeholder?: string;
}>();
</script>

<template>
  <div class="relative w-full max-w-xs">
    <Search
      class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
    />
    <Input
      v-model="model"
      type="search"
      :placeholder="placeholder ?? 'Search...'"
      class="pl-9"
    />
  </div>
</template>
