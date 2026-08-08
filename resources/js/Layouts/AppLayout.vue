<script setup>
import LocaleSync from '@/Components/LocaleSync.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppStore } from '@/stores/app';
import { usePermissions } from '@/composables/usePermissions';

const page = usePage();
const appStore = useAppStore();
const { can } = usePermissions();
const { t } = useI18n();

const user = computed(() => page.props.auth?.user);
const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const notifications = computed(() => page.props.notifications ?? { unread_count: 0, recent: [] });
const initials = computed(() => {
    if (!user.value?.name) {
        return 'U';
    }

    return user.value.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
});

const openNotification = (item) => {
    router.post(route('notifications.read', item.id), {}, { preserveScroll: true });
};

const markAllRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};
</script>

<template>
    <div class="erp-shell">
        <LocaleSync />

        <div
            v-if="!appStore.sidebarCollapsed"
            class="erp-sidebar-backdrop d-lg-none"
            @click="appStore.toggleSidebar"
        />

        <aside
            class="erp-sidebar"
            :class="{ 'is-open': !appStore.sidebarCollapsed }"
        >
            <Link :href="route('dashboard')" class="erp-brand">
                <span class="erp-brand-mark">SE</span>
                <span class="erp-brand-text">{{ companyName }}</span>
            </Link>

            <nav class="erp-nav">
                <Link
                    :href="route('dashboard')"
                    class="erp-nav-link"
                    :class="{ active: route().current('dashboard') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">◈</span>
                    {{ t('nav.dashboard') }}
                </Link>

                <div class="erp-nav-section">{{ t('nav.operations') }}</div>

                <Link
                    v-if="can('ships.view')"
                    :href="route('ships.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('ships.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">⚒</span>
                    {{ t('nav.ships') }}
                </Link>

                <Link
                    v-if="can('voyages.view')"
                    :href="route('companies.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('companies.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">◇</span>
                    {{ t('nav.companies') }}
                </Link>

                <Link
                    v-if="can('dubai_accounts.view')"
                    :href="route('dubai-accounts.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('dubai-accounts.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">▣</span>
                    {{ t('nav.dubai_accounts') }}
                </Link>

                <Link
                    v-if="can('voyages.view')"
                    :href="route('voyages.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('voyages.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">➤</span>
                    {{ t('nav.voyages') }}
                </Link>

                <Link
                    v-if="can('land_trips.view')"
                    :href="route('land-trips.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('land-trips.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">▤</span>
                    {{ t('nav.land_trips') }}
                </Link>

                <div class="erp-nav-section">{{ t('nav.finance') }}</div>

                <Link
                    v-if="can('accounting.view')"
                    :href="route('accounts.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('accounts.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">☰</span>
                    {{ t('nav.accounts') }}
                </Link>

                <Link
                    v-if="can('accounting.view')"
                    :href="route('journals.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('journals.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">≡</span>
                    {{ t('nav.journals') }}
                </Link>

                <Link
                    v-if="can('accounting.view')"
                    :href="route('money-vouchers.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('money-vouchers.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">$</span>
                    {{ t('nav.receipts') }}
                </Link>

                <Link
                    v-if="can('iran_cars.view')"
                    :href="route('iran-cars.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('iran-cars.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">▣</span>
                    {{ t('nav.iran_cars') }}
                </Link>

                <Link
                    v-if="can('reports.view')"
                    :href="route('reports.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('reports.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">▦</span>
                    {{ t('nav.reports') }}
                </Link>

                <div class="erp-nav-section">{{ t('nav.admin') }}</div>

                <Link
                    v-if="can('users.view')"
                    :href="route('users.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('users.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">◉</span>
                    {{ t('nav.users') }}
                </Link>

                <Link
                    v-if="can('roles.view')"
                    :href="route('roles.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('roles.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">▣</span>
                    {{ t('nav.roles') }}
                </Link>

                <Link
                    v-if="can('settings.view')"
                    :href="route('settings.edit')"
                    class="erp-nav-link"
                    :class="{ active: route().current('settings.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">◍</span>
                    {{ t('nav.settings') }}
                </Link>

                <Link
                    v-if="can('settings.view')"
                    :href="route('whatsapp-notifications.index')"
                    class="erp-nav-link"
                    :class="{ active: route().current('whatsapp-notifications.*') }"
                    @click="appStore.closeSidebar"
                >
                    <span aria-hidden="true">💬</span>
                    {{ t('nav.whatsapp') }}
                </Link>
            </nav>
        </aside>

        <div class="erp-main">
            <header class="erp-topbar">
                <div class="d-flex align-items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-erp-ghost d-lg-none"
                        :aria-label="t('nav.menu')"
                        @click="appStore.toggleSidebar"
                    >
                        {{ t('nav.menu') }}
                    </button>
                    <h1 class="h5 mb-0 erp-display">
                        <slot name="header">{{ t('nav.dashboard') }}</slot>
                    </h1>
                </div>

                <div class="d-flex align-items-center gap-2" v-if="user">
                    <div class="dropdown">
                        <button
                            class="btn btn-erp-ghost position-relative erp-notify-btn"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            :aria-label="t('notifications.title')"
                        >
                            <span aria-hidden="true">◉</span>
                            <span
                                v-if="notifications.unread_count > 0"
                                class="erp-notify-badge"
                            >
                                {{ notifications.unread_count > 9 ? '9+' : notifications.unread_count }}
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 erp-notify-menu p-0">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <strong class="small">{{ t('notifications.title') }}</strong>
                                <button
                                    v-if="notifications.unread_count > 0"
                                    type="button"
                                    class="btn btn-link btn-sm text-decoration-none p-0"
                                    @click.prevent="markAllRead"
                                >
                                    {{ t('notifications.mark_all') }}
                                </button>
                            </div>
                            <div v-if="!notifications.recent?.length" class="px-3 py-4 text-secondary small text-center">
                                {{ t('notifications.empty') }}
                            </div>
                            <button
                                v-for="item in notifications.recent"
                                :key="item.id"
                                type="button"
                                class="erp-notify-item w-100 text-start border-0"
                                :class="{ 'is-unread': item.is_unread }"
                                @click="openNotification(item)"
                            >
                                <div class="fw-semibold small">{{ item.title }}</div>
                                <div class="small text-secondary">{{ item.body }}</div>
                                <div class="tiny text-secondary mt-1">{{ item.created_at }}</div>
                            </button>
                        </div>
                    </div>

                    <div class="dropdown">
                        <button
                            class="user-chip dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <span class="user-avatar">{{ initials }}</span>
                            <span>{{ user.name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                            <li>
                                <Link class="dropdown-item py-2" :href="route('profile.edit')">
                                    {{ t('nav.profile') }}
                                </Link>
                            </li>
                            <li><hr class="dropdown-divider" /></li>
                            <li>
                                <Link
                                    class="dropdown-item py-2 text-danger"
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    {{ t('nav.logout') }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="erp-page">
                <slot />
            </div>
        </div>
    </div>
</template>
