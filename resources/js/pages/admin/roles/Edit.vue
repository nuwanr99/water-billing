<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import DeleteConfirmDialog from '@/components/admin/DeleteConfirmDialog.vue';
import RoleForm from '@/components/admin/RoleForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { usePermission } from '@/composables/usePermission';
import { destroy, index } from '@/routes/admin/roles';
import { update as permissionsUpdate } from '@/routes/admin/roles/permissions';

const props = defineProps<{
    role: {
        id: number;
        name: string;
        permissions: string[];
    };
    categories: Record<string, string[]>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Roles',
                href: index(),
            },
            {
                title: 'Edit',
                href: '#',
            },
        ],
    },
});

const { hasPermission } = usePermission();

const permissionsForm = useForm({
    permissions: [...props.role.permissions],
});

const isGranted = (permission: string) =>
    permissionsForm.permissions.includes(permission);

const togglePermission = (
    permission: string,
    checked: boolean | 'indeterminate',
) => {
    permissionsForm.permissions =
        checked === true
            ? [...permissionsForm.permissions, permission]
            : permissionsForm.permissions.filter((name) => name !== permission);
};

const grantedCount = (permissions: string[]) =>
    permissions.filter(isGranted).length;

const categoryState = (permissions: string[]): boolean | 'indeterminate' => {
    const granted = grantedCount(permissions);

    if (granted === 0) {
        return false;
    }

    return granted === permissions.length ? true : 'indeterminate';
};

const toggleCategory = (
    permissions: string[],
    checked: boolean | 'indeterminate',
) => {
    const withoutCategory = permissionsForm.permissions.filter(
        (name) => !permissions.includes(name),
    );

    permissionsForm.permissions =
        checked === true
            ? [...withoutCategory, ...permissions]
            : withoutCategory;
};

const submitPermissions = () => {
    permissionsForm.put(permissionsUpdate(props.role.id).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit ${role.name}`" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            :title="`Edit ${role.name}`"
            description="Update the role's name and the permissions it grants"
        />

        <RoleForm mode="edit" :role="role" />

        <Separator class="max-w-2xl" />

        <form class="max-w-2xl space-y-6" @submit.prevent="submitPermissions">
            <Heading
                variant="small"
                title="Permissions"
                description="Grant permissions to this role, grouped by category"
            />

            <div class="space-y-3">
                <Collapsible
                    v-for="(permissions, category) in categories"
                    :key="category"
                    :default-open="true"
                    class="rounded-lg border"
                >
                    <div class="flex items-center gap-3 p-3">
                        <Checkbox
                            :model-value="categoryState(permissions)"
                            :aria-label="`Toggle all ${category} permissions`"
                            @update:model-value="
                                toggleCategory(permissions, $event)
                            "
                        />
                        <CollapsibleTrigger
                            class="flex flex-1 items-center justify-between gap-2 text-left"
                        >
                            <span class="font-medium capitalize">
                                {{ category }}
                            </span>
                            <span
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                {{ grantedCount(permissions) }} of
                                {{ permissions.length }}
                                <ChevronDown class="size-4" />
                            </span>
                        </CollapsibleTrigger>
                    </div>
                    <CollapsibleContent>
                        <div
                            class="grid gap-3 border-t p-3 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <label
                                v-for="permission in permissions"
                                :key="permission"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :model-value="isGranted(permission)"
                                    @update:model-value="
                                        togglePermission(permission, $event)
                                    "
                                />
                                <span>{{ permission }}</span>
                            </label>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </div>

            <InputError :message="permissionsForm.errors.permissions" />

            <Button type="submit" :disabled="permissionsForm.processing">
                Save permissions
            </Button>
        </form>

        <template v-if="hasPermission('roles.delete')">
            <Separator class="max-w-2xl" />

            <div class="max-w-2xl space-y-4">
                <Heading
                    variant="small"
                    title="Delete role"
                    description="Users assigned to this role will lose the permissions it grants"
                />

                <DeleteConfirmDialog
                    :url="destroy(role.id).url"
                    :title="`Delete ${role.name}?`"
                    description="This will permanently delete the role and revoke it from all users. This action cannot be undone."
                    :confirm-name="role.name"
                    trigger-label="Delete role"
                />
            </div>
        </template>
    </div>
</template>
