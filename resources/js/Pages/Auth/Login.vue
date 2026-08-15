<script setup>
import AnimatedCharacters from '@/Components/Auth/AnimatedCharacters.vue';
import InteractiveHoverButton from '@/Components/Auth/InteractiveHoverButton.vue';
import InputError from '@/Components/InputError.vue';
import LocaleSync from '@/Components/LocaleSync.vue';
import ThemeSync from '@/Components/ThemeSync.vue';
import { applyDocumentLocale } from '@/i18n';
import { useTheme } from '@/composables/useTheme';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const { t, locale } = useI18n();
const { theme, toggleTheme } = useTheme();
const showPassword = ref(false);
const isTyping = ref(false);

const locales = computed(() => page.props.appSettings?.locales ?? []);
const currentLocale = computed(() => page.props.appSettings?.locale || locale.value || 'ar');

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const setLocale = (value) => {
    if (!value) {
        return;
    }

    locale.value = value;
    applyDocumentLocale(value);
};

const localeLabel = (item) => t(`language.${item.value}`) || item.label;

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="t('auth.sign_in')" />
    <LocaleSync />
    <ThemeSync />

    <div class="login-shell min-h-screen overflow-hidden grid lg:grid-cols-2">
        <div class="relative hidden lg:flex flex-col justify-between p-12 text-slate-900">
            <div class="relative z-20">
                <div class="flex items-center gap-2 text-lg font-semibold tracking-wide">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-white text-xs font-bold">SE</span>
                    <span>{{ t('app.name') }}</span>
                </div>
            </div>

            <div class="relative z-20 flex items-end justify-center h-[500px]">
                <div class="origin-bottom scale-90 xl:scale-100">
                    <AnimatedCharacters
                        :is-typing="isTyping"
                        :show-password="showPassword"
                        :password-length="form.password.length"
                    />
                </div>
            </div>

            <p class="relative z-20 text-sm text-slate-500">{{ t('auth.visual_footer') }}</p>

            <div class="absolute inset-0 bg-[linear-gradient(to_right,rgb(15_23_42/0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgb(15_23_42/0.05)_1px,transparent_1px)] bg-[size:20px_20px]" />
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(108,63,245,0.18),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(255,255,255,0.5),transparent_30%)]" />
            <div class="absolute top-1/4 end-1/4 size-64 bg-indigo-300/30 rounded-full blur-3xl animate-[float_12s_ease-in-out_infinite]" />
            <div class="absolute bottom-1/4 start-1/4 size-96 bg-slate-300/30 rounded-full blur-3xl animate-[float_16s_ease-in-out_infinite_reverse]" />
        </div>

        <div class="relative flex items-center justify-center p-6 sm:p-8">
            <div class="absolute top-4 end-4 z-30 flex gap-2">
                <div class="relative">
                    <button
                        id="authLocaleButton"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm dark:border-white/10 dark:bg-zinc-900 dark:text-slate-200"
                        data-dropdown-toggle="authLocaleMenu"
                    >
                        {{ currentLocale.toUpperCase() }}
                    </button>
                    <div id="authLocaleMenu" class="z-20 hidden w-36 divide-y divide-gray-100 rounded-lg bg-white shadow-sm dark:bg-gray-700">
                        <ul class="py-2 text-sm text-gray-700 dark:text-gray-200">
                            <li v-for="item in locales" :key="item.value">
                                <button
                                    type="button"
                                    class="w-full px-4 py-2 text-start hover:bg-gray-100 dark:hover:bg-gray-600"
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
                    class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm dark:border-white/10 dark:bg-zinc-900 dark:text-slate-200"
                    :aria-label="theme === 'dark' ? t('appearance.toggle_light') : t('appearance.toggle_dark')"
                    @click="toggleTheme"
                >
                    {{ theme === 'dark' ? t('appearance.light') : t('appearance.dark') }}
                </button>
            </div>

            <div class="login-card w-full max-w-[420px]">
                <div class="lg:hidden flex items-center justify-center gap-2 text-lg font-semibold mb-10">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-indigo-600 text-white text-xs font-bold">SE</span>
                    <span>{{ t('app.name') }}</span>
                </div>

                <div class="text-center mb-10">
                    <p class="text-xs uppercase tracking-[0.32em] text-indigo-600/80 mb-3">{{ t('auth.sign_in_kicker') }}</p>
                    <h1 class="text-3xl font-bold tracking-tight mb-2 text-slate-900 dark:text-white">{{ t('auth.welcome_back') }}</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm">{{ t('auth.sign_in_subtitle') }}</p>
                </div>

                <div v-if="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-300">
                    {{ status }}
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-800 dark:text-slate-200" for="email">{{ t('auth.email') }}</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            autofocus
                            class="login-input h-12 w-full rounded-full px-5 text-sm"
                            @focus="isTyping = true"
                            @blur="isTyping = false"
                        >
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-800 dark:text-slate-200" for="password">{{ t('auth.password') }}</label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                class="login-input h-12 w-full rounded-full px-5 pe-12 text-sm"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 end-0 flex items-center pe-4 text-slate-400 hover:text-slate-700"
                                :aria-label="t('auth.password')"
                                @click="showPassword = !showPassword"
                            >
                                <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-.722-3.25"/><path d="M2 8a10.645 10.645 0 0 0 20 0"/><path d="m20 15-1.726-2.05"/><path d="m4 15 1.726-2.05"/><path d="m9 18 .722-3.25"/></svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                            <input id="remember" v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                            {{ t('auth.remember_me') }}
                        </label>
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm font-medium text-indigo-600 hover:underline"
                        >
                            {{ t('auth.forgot_password') }}
                        </Link>
                    </div>

                    <InteractiveHoverButton
                        type="submit"
                        :text="form.processing ? t('auth.signing_in') : t('auth.sign_in')"
                        :disabled="form.processing"
                    />
                </form>
            </div>
        </div>
    </div>
</template>
