<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import MultiUserSelect from '@/components/MultiUserSelect.vue';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { users } from '@/routes/admin/settings';
import { edit, update } from '@/routes/admin/settings/notifications';
import type { ComboboxOption } from '@/types';

const props = defineProps<{
  complaintNotifyUsers: { id: number; name: string; email: string }[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Notifications', href: edit() }],
  },
});

const selectedUsers = ref<ComboboxOption[]>(
  props.complaintNotifyUsers.map((user): ComboboxOption => ({
    value: user.id,
    label: user.name,
    description: user.email,
  })),
);

const form = useForm<{ complaint_notify_user_ids: number[] }>({
  complaint_notify_user_ids: [],
});

const submit = (): void => {
  form
    .transform(() => ({
      complaint_notify_user_ids: selectedUsers.value.map((user) => user.value),
    }))
    .put(update().url, { preserveScroll: true });
};
</script>

<template>
  <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
    <Heading
      title="Notification settings"
      description="Control who gets alerted when a member raises a complaint."
    />

    <Card>
      <CardHeader>
        <CardTitle>Complaint notifications</CardTitle>
        <CardDescription>
          These users get a WhatsApp when a member raises a complaint.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <MultiUserSelect
          v-model="selectedUsers"
          :search-url="users().url"
          placeholder="Search users by name or email..."
        />

        <p
          v-if="selectedUsers.length === 0"
          class="text-sm text-amber-600 dark:text-amber-400"
        >
          No one is selected — new complaints won't send a WhatsApp alert.
        </p>

        <Button :disabled="form.processing" @click="submit"> Save </Button>
      </CardContent>
    </Card>
  </div>
</template>
