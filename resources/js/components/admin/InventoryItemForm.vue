<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/admin/inventory';

type InventoryItemFormData = {
  id: number;
  name: string;
  unit: string;
  unit_rate: number;
  quantity_in_stock: number;
  reorder_level: number;
  is_active: boolean;
};

const props = defineProps<{
  mode: 'create' | 'edit';
  item?: InventoryItemFormData;
}>();

const form = useForm<{
  name: string;
  unit: string;
  unit_rate: number | string;
  quantity_in_stock: number | string;
  reorder_level: number | string;
  is_active: boolean;
}>({
  name: props.item?.name ?? '',
  unit: props.item?.unit ?? '',
  unit_rate: props.item?.unit_rate ?? '',
  quantity_in_stock: props.item?.quantity_in_stock ?? 0,
  reorder_level: props.item?.reorder_level ?? 0,
  is_active: props.item?.is_active ?? true,
});

const activeState = computed<string>({
  get: () => (form.is_active ? 'active' : 'inactive'),
  set: (value: string) => {
    form.is_active = value === 'active';
  },
});

const submit = (): void => {
  if (props.mode === 'create') {
    form.post(store().url);

    return;
  }

  form.put(update(props.item!.id).url);
};
</script>

<template>
  <form class="max-w-2xl space-y-6" @submit.prevent="submit">
    <div class="grid gap-2">
      <Label for="name">Item name</Label>
      <Input
        id="name"
        v-model="form.name"
        required
        maxlength="255"
        placeholder="e.g. 20mm PVC pipe"
      />
      <InputError :message="form.errors.name" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div class="grid gap-2">
        <Label for="unit">Unit</Label>
        <Input
          id="unit"
          v-model="form.unit"
          required
          maxlength="50"
          placeholder="pcs, m, kg…"
        />
        <InputError :message="form.errors.unit" />
      </div>

      <div class="grid gap-2">
        <Label for="unit_rate">Unit rate (Rs)</Label>
        <Input
          id="unit_rate"
          v-model="form.unit_rate"
          type="number"
          step="0.01"
          min="0"
          required
          placeholder="0.00"
        />
        <InputError :message="form.errors.unit_rate" />
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div v-if="mode === 'create'" class="grid gap-2">
        <Label for="quantity_in_stock">Opening stock</Label>
        <Input
          id="quantity_in_stock"
          v-model="form.quantity_in_stock"
          type="number"
          step="1"
          min="0"
          required
        />
        <p class="text-xs text-muted-foreground">
          The quantity on hand today. Afterwards, stock only changes through
          recorded movements.
        </p>
        <InputError :message="form.errors.quantity_in_stock" />
      </div>

      <div class="grid gap-2">
        <Label for="reorder_level">Reorder level</Label>
        <Input
          id="reorder_level"
          v-model="form.reorder_level"
          type="number"
          step="1"
          min="0"
          required
        />
        <p class="text-xs text-muted-foreground">
          Flag the item as low stock at or below this quantity.
        </p>
        <InputError :message="form.errors.reorder_level" />
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="status">Status</Label>
      <Select v-model="activeState">
        <SelectTrigger id="status" class="w-full sm:w-64">
          <SelectValue placeholder="Select a status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="active">Active</SelectItem>
          <SelectItem value="inactive">Inactive</SelectItem>
        </SelectContent>
      </Select>
      <InputError :message="form.errors.is_active" />
    </div>

    <div class="flex items-center gap-4">
      <Button type="submit" :disabled="form.processing">
        {{ mode === 'create' ? 'Add item' : 'Save changes' }}
      </Button>
      <Button variant="secondary" as-child>
        <Link :href="index()">Cancel</Link>
      </Button>
    </div>
  </form>
</template>
