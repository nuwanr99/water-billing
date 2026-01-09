<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserForm from '@/components/admin/UserForm.vue';
import Heading from '@/components/Heading.vue';
import { create, index } from '@/routes/admin/users';

defineProps<{
  roles: string[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Users',
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
  <Head title="Create user" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="Create user"
      description="Add a new user account and assign its roles"
    />

    <UserForm mode="create" :roles="roles" />
  </div>
</template>
