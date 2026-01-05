import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermission() {
    const page = usePage();

    const user = computed(() => page.props.auth.user);
    const roles = computed<string[]>(() => user.value?.roles ?? []);
    const permissions = computed<string[]>(() => user.value?.permissions ?? []);

    const hasRole = (role: string | string[]): boolean => {
        const wanted = Array.isArray(role) ? role : [role];

        return wanted.some((name) => roles.value.includes(name));
    };

    const hasPermission = (permission: string | string[]): boolean => {
        if (roles.value.includes('Super Admin')) {
            return true;
        }

        const wanted = Array.isArray(permission) ? permission : [permission];

        return wanted.some((name) => permissions.value.includes(name));
    };

    return { user, roles, permissions, hasRole, hasPermission };
}
