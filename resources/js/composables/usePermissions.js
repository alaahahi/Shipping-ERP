import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.auth?.user?.permissions ?? []);
    const roles = computed(() => page.props.auth?.user?.roles ?? []);

    const can = (permission) => permissions.value.includes(permission);
    const hasRole = (role) => roles.value.includes(role);

    return {
        permissions,
        roles,
        can,
        hasRole,
    };
}
