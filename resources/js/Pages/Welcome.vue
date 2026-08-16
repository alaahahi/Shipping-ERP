<script setup>
import LocaleSync from '@/Components/LocaleSync.vue';
import ThemeSync from '@/Components/ThemeSync.vue';
import { applyDocumentLocale } from '@/i18n';
import { useTheme } from '@/composables/useTheme';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();
const { t, locale } = useI18n();
const { theme, toggleTheme } = useTheme();

const user = computed(() => page.props.auth?.user ?? null);
const locales = computed(() => page.props.appSettings?.locales ?? []);
const currentLocale = computed(() => page.props.appSettings?.locale || locale.value || 'ar');

const setLocale = (value) => {
    if (!value) {
        return;
    }
    locale.value = value;
    applyDocumentLocale(value);
};

const localeLabel = (item) => t(`language.${item.value}`) || item.label;
</script>

<template>
    <Head :title="t('welcome.page_title')" />
    <LocaleSync />
    <ThemeSync />

    <div class="welcome-gate">
        <div class="welcome-gate__atmosphere" aria-hidden="true">
            <div class="welcome-gate__glow welcome-gate__glow--a" />
            <div class="welcome-gate__glow welcome-gate__glow--b" />
            <div class="welcome-gate__wave" />
        </div>

        <header class="welcome-gate__top">
            <div class="welcome-gate__brand">
                <span class="welcome-gate__mark" aria-hidden="true">SE</span>
                <span class="welcome-gate__name">{{ t('app.name') }}</span>
            </div>

            <div class="welcome-gate__tools">
                <div class="relative">
                    <button
                        id="welcomeLocaleButton"
                        type="button"
                        class="welcome-gate__chip"
                        data-dropdown-toggle="welcomeLocaleMenu"
                    >
                        {{ currentLocale.toUpperCase() }}
                        <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
                        </svg>
                    </button>
                    <div
                        id="welcomeLocaleMenu"
                        class="z-30 hidden w-36 divide-y divide-gray-100 rounded-lg bg-white shadow-sm dark:bg-gray-700"
                    >
                        <ul class="py-2 text-sm text-gray-700 dark:text-gray-200" aria-labelledby="welcomeLocaleButton">
                            <li v-for="item in locales" :key="item.value">
                                <button
                                    type="button"
                                    class="block w-full px-4 py-2 text-start hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"
                                    @click="setLocale(item.value)"
                                >
                                    {{ localeLabel(item) }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <button
                    type="button"
                    class="welcome-gate__chip"
                    :aria-label="theme === 'dark' ? t('appearance.toggle_light') : t('appearance.toggle_dark')"
                    :title="theme === 'dark' ? t('appearance.toggle_light') : t('appearance.toggle_dark')"
                    @click="toggleTheme"
                >
                    <svg v-if="theme === 'dark'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8" />
                        <path d="M12 3v1.6M12 19.4V21M4.9 4.9l1.1 1.1M18 18l1.1 1.1M3 12h1.6M19.4 12H21M4.9 19.1 6 18M18 6l1.1-1.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                    <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M16.5 13.2A6.2 6.2 0 0 1 10.8 7.4 6.3 6.3 0 1 0 16.5 13.2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </header>

        <main class="welcome-gate__hero">
            <p class="welcome-gate__kicker">{{ t('welcome.kicker') }}</p>
            <h1 class="welcome-gate__title">{{ t('app.name') }}</h1>
            <p class="welcome-gate__lead">
                {{ user ? t('welcome.logged_in_lead', { name: user.name }) : t('app.tagline') }}
            </p>
            <p class="welcome-gate__copy">
                {{ user ? t('welcome.logged_in_text') : t('welcome.guest_text') }}
            </p>

            <div class="welcome-gate__actions">
                <Link
                    v-if="user"
                    :href="route('dashboard')"
                    class="welcome-gate__cta"
                >
                    {{ t('welcome.open_dashboard') }}
                </Link>
                <Link
                    v-else-if="canLogin"
                    :href="route('login')"
                    class="welcome-gate__cta"
                >
                    {{ t('auth.sign_in') }}
                </Link>
            </div>
        </main>

        <footer class="welcome-gate__foot">
            {{ t('auth.visual_footer') }}
        </footer>
    </div>
</template>

<style scoped>
.welcome-gate {
    --welcome-ink: #ecfeff;
    --welcome-muted: rgba(236, 254, 255, 0.78);
    --welcome-soft: rgba(236, 254, 255, 0.55);
    position: relative;
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    color: var(--welcome-ink);
    background:
        radial-gradient(120% 80% at 12% 8%, rgba(45, 212, 191, 0.22), transparent 52%),
        radial-gradient(90% 70% at 88% 18%, rgba(3, 105, 161, 0.28), transparent 48%),
        linear-gradient(155deg, #020617 0%, #0b1220 42%, #0f766e 100%);
    font-family: var(--erp-font-body, 'Source Sans 3', system-ui, sans-serif);
}

.welcome-gate__atmosphere {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.welcome-gate__glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(48px);
    opacity: 0.55;
    animation: welcome-drift 14s ease-in-out infinite alternate;
}

.welcome-gate__glow--a {
    width: min(42vw, 28rem);
    height: min(42vw, 28rem);
    inset-inline-start: -8%;
    bottom: 8%;
    background: rgba(45, 212, 191, 0.35);
}

.welcome-gate__glow--b {
    width: min(36vw, 22rem);
    height: min(36vw, 22rem);
    inset-inline-end: -6%;
    top: 18%;
    background: rgba(14, 116, 144, 0.4);
    animation-delay: -4s;
}

.welcome-gate__wave {
    position: absolute;
    inset-inline: 0;
    bottom: -12%;
    height: 42%;
    background:
        radial-gradient(140% 100% at 50% 0%, transparent 40%, rgba(2, 6, 23, 0.55) 78%),
        repeating-linear-gradient(
            90deg,
            rgba(255, 255, 255, 0.035) 0 1px,
            transparent 1px 56px
        );
    mask-image: linear-gradient(to top, black 20%, transparent 90%);
    animation: welcome-wave 18s linear infinite;
}

.welcome-gate__top,
.welcome-gate__hero,
.welcome-gate__foot {
    position: relative;
    z-index: 1;
}

.welcome-gate__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.25rem 0;
}

@media (min-width: 768px) {
    .welcome-gate__top {
        padding: 1.75rem 2.5rem 0;
    }
}

.welcome-gate__brand {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
}

.welcome-gate__mark {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.85rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.14);
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.welcome-gate__name {
    font-family: var(--erp-font-display, 'Plus Jakarta Sans', system-ui, sans-serif);
    font-size: 1.15rem;
    font-weight: 650;
    letter-spacing: -0.02em;
}

.welcome-gate__tools {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.welcome-gate__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 2.5rem;
    padding: 0.45rem 0.85rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    background: rgba(255, 255, 255, 0.08);
    color: var(--welcome-ink);
    font-size: 0.8rem;
    font-weight: 600;
    transition: background-color 180ms ease, border-color 180ms ease;
}

.welcome-gate__chip:hover {
    background: rgba(255, 255, 255, 0.14);
    border-color: rgba(255, 255, 255, 0.28);
}

.welcome-gate__hero {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    max-width: 42rem;
    padding: 3.5rem 1.25rem 2.5rem;
    animation: welcome-rise 700ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@media (min-width: 768px) {
    .welcome-gate__hero {
        padding: 4rem 2.5rem 3rem;
    }
}

.welcome-gate__kicker {
    margin: 0 0 0.85rem;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(153, 246, 228, 0.9);
}

.welcome-gate__title {
    margin: 0;
    font-family: var(--erp-font-display, 'Plus Jakarta Sans', system-ui, sans-serif);
    font-size: clamp(2.6rem, 8vw, 4.4rem);
    font-weight: 750;
    line-height: 1.02;
    letter-spacing: -0.045em;
}

.welcome-gate__lead {
    margin: 1rem 0 0;
    max-width: 28rem;
    font-family: var(--erp-font-display, 'Plus Jakarta Sans', system-ui, sans-serif);
    font-size: clamp(1.15rem, 2.6vw, 1.45rem);
    font-weight: 550;
    line-height: 1.35;
    color: var(--welcome-muted);
}

.welcome-gate__copy {
    margin: 0.85rem 0 0;
    max-width: 28rem;
    font-size: 1rem;
    line-height: 1.65;
    color: var(--welcome-soft);
}

.welcome-gate__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 2rem;
}

.welcome-gate__cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 3rem;
    min-width: 11rem;
    padding: 0.75rem 1.4rem;
    border-radius: 0.85rem;
    background: #f0fdfa;
    color: #0f766e;
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    text-decoration: none;
    box-shadow: 0 18px 40px rgba(15, 118, 110, 0.28);
    transition: transform 180ms ease, background-color 180ms ease, box-shadow 180ms ease;
}

.welcome-gate__cta:hover {
    transform: translateY(-2px);
    background: #ffffff;
    box-shadow: 0 22px 48px rgba(15, 118, 110, 0.34);
    color: #0f766e;
}

.welcome-gate__foot {
    padding: 0 1.25rem 1.5rem;
    font-size: 0.8rem;
    color: rgba(236, 254, 255, 0.45);
}

@media (min-width: 768px) {
    .welcome-gate__foot {
        padding: 0 2.5rem 2rem;
    }
}

@keyframes welcome-rise {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes welcome-drift {
    from {
        transform: translate3d(0, 0, 0);
    }
    to {
        transform: translate3d(18px, -24px, 0);
    }
}

@keyframes welcome-wave {
    from {
        background-position: 0 0, 0 0;
    }
    to {
        background-position: 0 0, 56px 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .welcome-gate__glow,
    .welcome-gate__wave,
    .welcome-gate__hero,
    .welcome-gate__cta {
        animation: none !important;
        transition: none !important;
    }
}
</style>
