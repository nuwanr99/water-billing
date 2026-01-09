<script setup lang="ts">
import { Check, ChevronsUpDown, LoaderCircle } from '@lucide/vue';
import {
  ComboboxAnchor,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxPortal,
  ComboboxRoot,
  ComboboxTrigger,
  ComboboxViewport,
} from 'reka-ui';
import type { ComboboxOption } from '@/types';

withDefaults(
  defineProps<{
    options: ComboboxOption[];
    placeholder?: string;
    emptyText?: string;
    loading?: boolean;
  }>(),
  {
    placeholder: 'Search...',
    emptyText: 'No results found.',
    loading: false,
  },
);

const selected = defineModel<ComboboxOption | null>({ required: true });
const searchTerm = defineModel<string>('searchTerm', { default: '' });
</script>

<template>
  <ComboboxRoot
    v-model="selected"
    by="value"
    ignore-filter
    open-on-click
    open-on-focus
  >
    <ComboboxAnchor
      class="flex h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 dark:bg-input/30"
    >
      <ComboboxInput
        v-model="searchTerm"
        class="h-full w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
        :display-value="(option?: ComboboxOption | null) => option?.label ?? ''"
        :placeholder="placeholder"
        autocomplete="off"
      />
      <LoaderCircle
        v-if="loading"
        class="size-4 shrink-0 animate-spin text-muted-foreground"
      />
      <ComboboxTrigger class="shrink-0">
        <ChevronsUpDown class="size-4 opacity-50" />
      </ComboboxTrigger>
    </ComboboxAnchor>

    <ComboboxPortal>
      <ComboboxContent
        position="popper"
        class="z-50 max-h-72 w-(--reka-popper-anchor-width) translate-y-1 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
      >
        <ComboboxViewport class="p-1">
          <ComboboxEmpty class="py-6 text-center text-sm text-muted-foreground">
            {{ loading ? 'Searching...' : emptyText }}
          </ComboboxEmpty>

          <ComboboxItem
            v-for="option in options"
            :key="option.value"
            :value="option"
            :text-value="option.label"
            class="relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
          >
            <div class="flex min-w-0 flex-col">
              <span class="truncate">{{ option.label }}</span>
              <span
                v-if="option.description"
                class="truncate text-xs text-muted-foreground"
              >
                {{ option.description }}
              </span>
            </div>
            <span
              class="absolute right-2 flex size-3.5 items-center justify-center"
            >
              <ComboboxItemIndicator>
                <Check class="size-4" />
              </ComboboxItemIndicator>
            </span>
          </ComboboxItem>
        </ComboboxViewport>
      </ComboboxContent>
    </ComboboxPortal>
  </ComboboxRoot>
</template>
