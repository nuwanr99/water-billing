<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { onMounted, ref } from 'vue';
import SearchableCombobox from '@/components/SearchableCombobox.vue';
import type { ComboboxOption } from '@/types';

type UserResult = { id: number; name: string; email: string };

const props = defineProps<{
  searchUrl: string;
  placeholder?: string;
}>();

/**
 * The selected users, shown as removable chips. v-model exposes the raw id
 * list for the form; the chip labels live alongside so the picker can render
 * pre-selected users without another round-trip.
 */
const selectedUsers = defineModel<ComboboxOption[]>({ default: () => [] });

const picked = ref<ComboboxOption | null>(null);
const searchTerm = ref('');
const options = ref<ComboboxOption[]>([]);

const search = useHttp<{ search: string }, UserResult[]>({ search: '' });

const fetchUsers = (): void => {
  search.search = searchTerm.value;

  search.get(props.searchUrl, {
    onSuccess: (response) => {
      options.value = (response ?? []).map((user): ComboboxOption => ({
        value: user.id,
        label: user.name,
        description: user.email,
      }));
    },
  });
};

const addSelection = (option: ComboboxOption | null): void => {
  if (!option) {
    return;
  }

  if (!selectedUsers.value.some((user) => user.value === option.value)) {
    selectedUsers.value = [...selectedUsers.value, option];
  }

  picked.value = null;
  searchTerm.value = '';
};

const removeUser = (value: number): void => {
  selectedUsers.value = selectedUsers.value.filter(
    (user) => user.value !== value,
  );
};

onMounted(fetchUsers);
watchDebounced(searchTerm, fetchUsers, { debounce: 300 });
</script>

<template>
  <div class="flex flex-col gap-2">
    <SearchableCombobox
      v-model="picked"
      v-model:search-term="searchTerm"
      :options="options"
      :loading="search.processing"
      :placeholder="placeholder ?? 'Search users by name or email...'"
      empty-text="No users found."
      @update:model-value="addSelection"
    />

    <div v-if="selectedUsers.length > 0" class="flex flex-wrap gap-2">
      <span
        v-for="user in selectedUsers"
        :key="user.value"
        class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-sm"
      >
        {{ user.label }}
        <button
          type="button"
          class="text-muted-foreground hover:text-foreground"
          :aria-label="`Remove ${user.label}`"
          @click="removeUser(user.value)"
        >
          <X class="size-3.5" />
        </button>
      </span>
    </div>
  </div>
</template>
