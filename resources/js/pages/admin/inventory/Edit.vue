<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DeleteConfirmDialog from '@/components/admin/DeleteConfirmDialog.vue';
import InventoryItemForm from '@/components/admin/InventoryItemForm.vue';
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { destroy, index } from '@/routes/admin/inventory';

defineProps<{
  item: {
    id: number;
    name: string;
    unit: string;
    unit_rate: number;
    quantity_in_stock: number;
    reorder_level: number;
    is_active: boolean;
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inventory', href: index() },
      { title: 'Edit', href: '#' },
    ],
  },
});
</script>

<template>
  <Head :title="`Edit ${item.name}`" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      :title="`Edit ${item.name}`"
      description="Update the item's details. Its on-hand stock only changes through recorded movements."
    />

    <InventoryItemForm mode="edit" :item="item" />

    <Separator class="max-w-2xl" />

    <div class="max-w-2xl space-y-4">
      <Heading
        variant="small"
        title="Delete item"
        description="An item with recorded stock movements cannot be deleted — deactivate it instead."
      />

      <DeleteConfirmDialog
        :url="destroy(item.id).url"
        :title="`Delete ${item.name}?`"
        description="This permanently removes the item. This action cannot be undone."
        :confirm-name="item.name"
        trigger-label="Delete item"
      />
    </div>
  </div>
</template>
