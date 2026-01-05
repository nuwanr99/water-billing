<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    initial: string;
    url: string;
    placeholder?: string;
}>();

const search = ref(props.initial);

watchDebounced(
    search,
    (value) => {
        router.get(props.url, value ? { search: value } : {}, {
            preserveState: true,
            replace: true,
        });
    },
    { debounce: 300 },
);
</script>

<template>
    <div class="relative w-full max-w-xs">
        <Search
            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="search"
            type="search"
            :placeholder="placeholder ?? 'Search...'"
            class="pl-9"
        />
    </div>
</template>
