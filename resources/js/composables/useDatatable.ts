import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import type { DatatableFilters } from '@/types';

export function useDatatable(url: string, filters: DatatableFilters) {
  const search = ref(filters.search);
  const sort = ref(filters.sort);
  const direction = ref(filters.direction);

  const visit = () => {
    const params: Record<string, string> = {};

    if (search.value) {
      params.search = search.value;
    }

    if (sort.value) {
      params.sort = sort.value;
      params.direction = direction.value ?? 'asc';
    }

    router.get(url, params, { preserveState: true, replace: true });
  };

  watchDebounced(search, visit, { debounce: 300 });

  const sortBy = (column: string) => {
    if (sort.value === column) {
      direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
      sort.value = column;
      direction.value = 'asc';
    }

    visit();
  };

  return { search, sort, direction, sortBy };
}
