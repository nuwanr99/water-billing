<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { Toaster } from '@/components/ui/sonner';
import { dashboard } from '@/routes';

const { title = 'Meter readings', backHref = null } = defineProps<{
  title?: string;
  backHref?: string | null;
}>();
</script>

<template>
  <div
    class="flex min-h-dvh flex-col bg-background pb-[env(safe-area-inset-bottom)] text-foreground"
  >
    <header
      class="sticky top-0 z-20 border-b bg-background/90 pt-[env(safe-area-inset-top)] backdrop-blur"
    >
      <div class="mx-auto flex h-14 w-full max-w-md items-center gap-1 px-2">
        <Link
          :href="backHref ?? dashboard().url"
          class="flex size-10 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground active:bg-accent"
          :aria-label="backHref ? 'Back' : 'Exit to dashboard'"
        >
          <ChevronLeft class="size-6" />
        </Link>
        <h1 class="min-w-0 flex-1 truncate text-base font-semibold">
          {{ title }}
        </h1>
        <slot name="header-trailing" />
      </div>
    </header>

    <main class="mx-auto flex w-full max-w-md flex-1 flex-col">
      <slot />
    </main>

    <Toaster position="top-center" />
  </div>
</template>
