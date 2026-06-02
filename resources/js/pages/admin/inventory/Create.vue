<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import InventoryItemForm from '@/components/admin/InventoryItemForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index } from '@/routes/admin/inventory';

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inventory', href: index() },
      { title: 'New item', href: create() },
    ],
  },
});
</script>

<template>
  <Head title="New inventory item" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="New inventory item"
      description="Add a stocked material and its opening quantity"
    />

    <InventoryItemForm mode="create" />
  </div>
</template>
