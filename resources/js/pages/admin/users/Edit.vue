<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import DeleteConfirmDialog from '@/components/admin/DeleteConfirmDialog.vue';
import UserForm from '@/components/admin/UserForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { usePermission } from '@/composables/usePermission';
import { destroy, index } from '@/routes/admin/users';
import { update as passwordUpdate } from '@/routes/admin/users/password';

type EditableUser = {
    id: number;
    first_name: string;
    last_name: string;
    phone: string;
    address: string;
    wa_number: string | null;
    email: string;
    name: string;
    roles: string[];
};

const props = defineProps<{
    user: EditableUser;
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
                title: 'Edit',
                href: '#',
            },
        ],
    },
});

const { user: authUser, hasPermission } = usePermission();

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const submitPassword = () => {
    passwordForm.put(passwordUpdate(props.user.id).url, {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            :title="`Edit ${user.name}`"
            description="Update the user's details, roles, and password"
        />

        <UserForm mode="edit" :roles="roles" :user="user" />

        <Separator class="max-w-2xl" />

        <form class="max-w-2xl space-y-6" @submit.prevent="submitPassword">
            <Heading
                variant="small"
                title="Update password"
                description="Set a new password for this user"
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="password">New password</Label>
                    <PasswordInput
                        id="password"
                        v-model="passwordForm.password"
                        required
                        autocomplete="new-password"
                        placeholder="New password"
                    />
                    <InputError :message="passwordForm.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        v-model="passwordForm.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm password"
                    />
                    <InputError
                        :message="passwordForm.errors.password_confirmation"
                    />
                </div>
            </div>

            <Button type="submit" :disabled="passwordForm.processing">
                Update password
            </Button>
        </form>

        <template
            v-if="hasPermission('users.delete') && authUser?.id !== user.id"
        >
            <Separator class="max-w-2xl" />

            <div class="max-w-2xl space-y-4">
                <Heading
                    variant="small"
                    title="Delete user"
                    description="Permanently remove this user account"
                />

                <DeleteConfirmDialog
                    :url="destroy(user.id).url"
                    :title="`Delete ${user.name}?`"
                    description="This will permanently delete the user account. This action cannot be undone."
                    trigger-label="Delete user"
                />
            </div>
        </template>
    </div>
</template>
