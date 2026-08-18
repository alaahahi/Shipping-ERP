<script setup>
import LocaleSync from '@/Components/LocaleSync.vue';
import ThemeSync from '@/Components/ThemeSync.vue';
import IntelliJCredit from '@/Components/IntelliJCredit.vue';
import { fbGhostButton } from '@/flowbite';
import { applyDocumentLocale } from '@/i18n';
import { useTheme } from '@/composables/useTheme';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
});

const page = usePage();
const { t, locale } = useI18n();
const { theme, toggleTheme } = useTheme();

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
    <div class="min-h-screen grid lg:grid-cols-2 bg-slate-100 dark:bg-slate-950">
        <LocaleSync />
        <ThemeSync />

        <section class="relative overflow-hidden text-white px-8 py-10 lg:px-12 lg:py-16 flex flex-col justify-between min-h-[220px] bg-gradient-to-br from-slate-950 via-slate-900 to-teal-800">
            <div class="pointer-events-none absolute -end-16 -bottom-24 h-72 w-72 rounded-full bg-teal-400/20 blur-3xl" aria-hidden="true" />
            <div class="relative z-10">
                <div class="inline-flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/10 text-sm font-bold tracking-wide">SE</span>
                    <span class="text-xl font-semibold tracking-tight">{{ t('app.name') }}</span>
                </div>
            </div>

            <div class="relative z-10 max-w-md mt-10 lg:mt-0">
                <h1 class="text-3xl lg:text-4xl font-bold leading-tight mb-4">{{ t('auth.visual_title') }}</h1>
                <p class="text-slate-200/85 text-base leading-relaxed">{{ t('auth.visual_text') }}</p>
            </div>

            <IntelliJCredit tone="light" />
        </section>

        <section class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="flex justify-end gap-2 mb-4">
                    <button
                        id="authLocaleButton"
                        :class="fbGhostButton"
                        class="!w-auto"
                        type="button"
                        data-dropdown-toggle="authLocaleMenu"
                    >
                        {{ currentLocale.toUpperCase() }}
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
                        </svg>
                    </button>
                    <div id="authLocaleMenu" class="z-20 hidden bg-white divide-y divide-gray-100 rounded-lg shadow-sm w-36 dark:bg-gray-700">
                        <ul class="py-2 text-sm text-gray-700 dark:text-gray-200" aria-labelledby="authLocaleButton">
                            <li v-for="item in locales" :key="item.value">
                                <button
                                    type="button"
                                    class="w-full text-start px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white"
                                    :class="{ 'font-semibold text-teal-700 dark:text-teal-300': item.value === currentLocale }"
                                    @click="setLocale(item.value)"
                                >
                                    {{ localeLabel(item) }}
                                </button>
                            </li>
                        </ul>
                    </div>
                    <button
                        type="button"
                        :class="fbGhostButton"
                        class="!w-auto"
                        :aria-label="theme === 'dark' ? t('appearance.toggle_light') : t('appearance.toggle_dark')"
                        :title="theme === 'dark' ? t('appearance.toggle_light') : t('appearance.toggle_dark')"
                        @click="toggleTheme"
                    >
                        <svg v-if="theme === 'dark'" class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8" />
                            <path d="M12 3v1.6M12 19.4V21M4.9 4.9l1.1 1.1M18 18l1.1 1.1M3 12h1.6M19.4 12H21M4.9 19.1 6 18M18 6l1.1-1.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M16.5 13.2A6.2 6.2 0 0 1 10.8 7.4 6.3 6.3 0 1 0 16.5 13.2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8 dark:bg-gray-800 dark:border-gray-700">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">{{ title || t('auth.sign_in') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ subtitle || t('auth.sign_in_subtitle') }}</p>
                    <slot />
                </div>
                <div class="mt-6">
                    <IntelliJCredit />
                </div>
            </div>
        </section>
    </div>
</template>
