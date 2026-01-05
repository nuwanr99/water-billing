<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleForm from '@/components/admin/RoleForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index } from '@/routes/admin/roles';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Roles',
                href: index(),
            },
            {
                title: 'Create',
                href: create(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Create role" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            title="Create role"
            description="Create a role, then assign its permissions"
        />

        <RoleForm mode="create" />
    </div>
</template>
