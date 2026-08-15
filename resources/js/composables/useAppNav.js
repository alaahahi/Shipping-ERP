import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';

export function useAppNav() {
    const page = usePage();
    const { can } = usePermissions();
    const { t } = useI18n();

    const catalog = computed(() => {
        void page.url;

        const items = [
            {
                key: 'dashboard',
                href: () => route('dashboard'),
                match: 'dashboard',
                labelKey: 'nav.dashboard',
                group: null,
                permission: null,
                priority: 0,
            },
            {
                key: 'voyages',
                href: () => route('voyages.index'),
                match: 'voyages.*',
                labelKey: 'nav.voyages',
                group: 'operations',
                permission: 'voyages.view',
                priority: 1,
            },
            {
                key: 'land_trips',
                href: () => route('land-trips.index'),
                match: 'land-trips.*',
                labelKey: 'nav.land_trips',
                group: 'operations',
                permission: 'land_trips.view',
                priority: 2,
            },
            {
                key: 'ships',
                href: () => route('ships.index'),
                match: 'ships.*',
                labelKey: 'nav.ships',
                group: 'operations',
                permission: 'ships.view',
                priority: 3,
            },
            {
                key: 'companies',
                href: () => route('companies.index'),
                match: 'companies.*',
                labelKey: 'nav.companies',
                group: 'operations',
                permission: 'voyages.view',
                priority: 4,
            },
            {
                key: 'dubai_accounts',
                href: () => route('dubai-accounts.index'),
                match: 'dubai-accounts.*',
                labelKey: 'nav.dubai_accounts',
                group: 'operations',
                permission: 'dubai_accounts.view',
                priority: 8,
            },
            {
                key: 'accounts',
                href: () => route('accounts.index'),
                match: 'accounts.*',
                labelKey: 'nav.accounts',
                group: 'finance',
                permission: 'accounting.view',
                priority: 9,
            },
            {
                key: 'journals',
                href: () => route('journals.index'),
                match: 'journals.*',
                labelKey: 'nav.journals',
                group: 'finance',
                permission: 'accounting.view',
                priority: 10,
            },
            {
                key: 'receipts',
                href: () => route('money-vouchers.index'),
                match: 'money-vouchers.*',
                labelKey: 'nav.receipts',
                group: 'finance',
                permission: 'accounting.view',
                priority: 11,
            },
            {
                key: 'iran_cars',
                href: () => route('iran-cars.index'),
                match: 'iran-cars.*',
                labelKey: 'nav.iran_cars',
                group: 'finance',
                permission: 'iran_cars.view',
                priority: 12,
            },
            {
                key: 'reports',
                href: () => route('reports.index'),
                match: 'reports.*',
                labelKey: 'nav.reports',
                group: 'finance',
                permission: 'reports.view',
                priority: 7,
            },
            {
                key: 'users',
                href: () => route('users.index'),
                match: 'users.*',
                labelKey: 'nav.users',
                group: 'admin',
                permission: 'users.view',
                priority: 20,
            },
            {
                key: 'roles',
                href: () => route('roles.index'),
                match: 'roles.*',
                labelKey: 'nav.roles',
                group: 'admin',
                permission: 'roles.view',
                priority: 21,
            },
            {
                key: 'settings',
                href: () => route('settings.edit'),
                match: 'settings.*',
                labelKey: 'nav.settings',
                group: 'admin',
                permission: 'settings.view',
                priority: 22,
            },
            {
                key: 'whatsapp',
                href: () => route('whatsapp-notifications.index'),
                match: 'whatsapp-notifications.*',
                labelKey: 'nav.whatsapp',
                group: 'admin',
                permission: 'settings.view',
                priority: 23,
            },
        ];

        return items
            .filter((item) => !item.permission || can(item.permission))
            .map((item) => ({
                ...item,
                href: item.href(),
                label: t(item.labelKey),
                groupLabel: item.group ? t(`nav.${item.group}`) : null,
                active: item.match === 'dashboard'
                    ? route().current('dashboard')
                    : route().current(item.match),
            }))
            .sort((a, b) => a.priority - b.priority);
    });

    const groupedOverflow = (items) => {
        const groups = [];
        const indexByKey = new Map();

        items.forEach((item) => {
            const key = item.group || 'other';
            if (!indexByKey.has(key)) {
                indexByKey.set(key, groups.length);
                groups.push({
                    key,
                    label: item.groupLabel,
                    items: [],
                });
            }
            groups[indexByKey.get(key)].items.push(item);
        });

        return groups;
    };

    return {
        catalog,
        groupedOverflow,
    };
}
