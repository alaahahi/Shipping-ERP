<script setup>
import AppHeaderNav from '@/Components/AppHeaderNav.vue';
import LocaleSync from '@/Components/LocaleSync.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTheme } from '@/composables/useTheme';

const page = usePage();
const { t } = useI18n();
const { theme, toggleTheme } = useTheme();

const user = computed(() => page.props.auth?.user);
const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const currentLocale = computed(() => page.props.appSettings?.locale || 'ar');
const locales = computed(() => page.props.appSettings?.locales ?? []);
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

const setLocale = (locale) => {
    if (!locale || locale === currentLocale.value) {
        return;
    }

    router.post(route('preferences.update'), { locale }, { preserveScroll: true });
};

const localeLabel = (locale) => t(`language.${locale.value}`) || locale.label;
</script>

<template>
    <div class="erp-shell">
        <LocaleSync />

        <header class="erp-header">
            <div class="erp-header-bar">
                <Link :href="route('dashboard')" class="erp-brand">
                    <span class="erp-brand-mark">SE</span>
                    <span class="erp-brand-text">{{ companyName }}</span>
                </Link>

                <AppHeaderNav />

                <h1 class="h6 mb-0 erp-display erp-header-context">
                    <slot name="header">{{ t('nav.dashboard') }}</slot>
                </h1>

                <div class="erp-header-tools" v-if="user">
                    <button
                        type="button"
                        class="btn btn-erp-ghost erp-icon-btn"
                        :aria-label="theme === 'dark' ? t('appearance.toggle_light') : t('appearance.toggle_dark')"
                        :title="theme === 'dark' ? t('appearance.toggle_light') : t('appearance.toggle_dark')"
                        @click="toggleTheme"
                    >
                        <svg v-if="theme === 'dark'" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8" />
                            <path d="M12 3v1.6M12 19.4V21M4.9 4.9l1.1 1.1M18 18l1.1 1.1M3 12h1.6M19.4 12H21M4.9 19.1 6 18M18 6l1.1-1.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M16.5 13.2A6.2 6.2 0 0 1 10.8 7.4 6.3 6.3 0 1 0 16.5 13.2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div class="dropdown">
                        <button
                            class="btn btn-erp-ghost erp-icon-btn"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            :aria-label="t('language.label')"
                            :title="t('language.label')"
                        >
                            <span class="small fw-semibold">{{ currentLocale.toUpperCase() }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                            <li v-for="locale in locales" :key="locale.value">
                                <button
                                    type="button"
                                    class="dropdown-item py-2"
                                    :class="{ active: locale.value === currentLocale }"
                                    @click="setLocale(locale.value)"
                                >
                                    {{ localeLabel(locale) }}
                                </button>
                            </li>
                        </ul>
                    </div>
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
                            <span class="erp-user-name">{{ user.name }}</span>
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
            </div>
        </header>

        <div class="erp-main">
            <div class="erp-page">
                <slot />
            </div>
        </div>
    </div>
</template>
