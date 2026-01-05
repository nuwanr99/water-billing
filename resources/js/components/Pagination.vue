<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginated } from '@/types';

defineProps<{
    paginator: Paginated<unknown>;
}>();

const label = (raw: string) =>
    raw
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace('&hellip;', '…');
</script>

<template>
    <div
        v-if="paginator.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-4"
    >
        <p class="text-sm text-muted-foreground">
            Showing {{ paginator.from ?? 0 }} to {{ paginator.to ?? 0 }} of
            {{ paginator.total }} results
        </p>

        <nav class="flex flex-wrap items-center gap-1">
            <template v-for="(link, index) in paginator.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors"
                    :class="
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input hover:bg-muted'
                    "
                >
                    {{ label(link.label) }}
                </Link>
                <span
                    v-else
                    class="flex h-8 min-w-8 items-center justify-center rounded-md border border-input px-2 text-sm text-muted-foreground opacity-50"
                >
                    {{ label(link.label) }}
                </span>
            </template>
        </nav>
    </div>
</template>
