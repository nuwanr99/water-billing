<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/admin/roles';

const props = defineProps<{
    mode: 'create' | 'edit';
    role?: {
        id: number;
        name: string;
    };
}>();

const form = useForm({
    name: props.role?.name ?? '',
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(store().url);

        return;
    }

    form.put(update(props.role!.id).url);
};
</script>

<template>
    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="name">Role name</Label>
            <Input
                id="name"
                v-model="form.name"
                required
                autocomplete="off"
                placeholder="Role name"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="flex items-center gap-4">
            <Button type="submit" :disabled="form.processing">
                {{ mode === 'create' ? 'Create role' : 'Save changes' }}
            </Button>
            <Button variant="secondary" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
        </div>
    </form>
</template>
