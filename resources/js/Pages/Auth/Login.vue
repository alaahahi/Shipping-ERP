<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { fbAlertSuccess, fbButton, fbCheckbox, fbInput, fbLabel, fbLink } from '@/flowbite';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const { t } = useI18n();
const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout :title="t('auth.sign_in')" :subtitle="t('auth.sign_in_subtitle')">
        <Head :title="t('auth.sign_in')" />

        <div v-if="status" :class="fbAlertSuccess" role="status">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div class="mb-5">
                <label for="email" :class="fbLabel">{{ t('auth.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    :class="fbInput"
                    required
                    autofocus
                    autocomplete="username"
                >
                <InputError :message="form.errors.email" />
            </div>

            <div class="mb-5">
                <div class="flex items-center justify-between mb-2">
                    <label for="password" class="text-sm font-medium text-gray-900 dark:text-white">{{ t('auth.password') }}</label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        :class="fbLink"
                    >
                        {{ t('auth.forgot_password') }}
                    </Link>
                </div>
                <div class="relative">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        :class="fbInput"
                        class="pe-11"
                        required
                        autocomplete="current-password"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 end-0 flex items-center pe-3 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"
                        :aria-label="showPassword ? t('auth.password') : t('auth.password')"
                        @click="showPassword = !showPassword"
                    >
                        <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke="currentColor" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" />
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" />
                        </svg>
                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M4 4l16 16M9.9 9.9A3 3 0 0 0 12 15a3 3 0 0 0 2.1-.9M6.1 6.5C4.2 8 2.8 10.1 2.5 12c0 0 3.5 7 9.5 7 1.7 0 3.2-.4 4.5-1M17.4 15.6c1.4-1.1 2.5-2.5 3.1-3.6 0 0-3.5-7-9.5-7-.7 0-1.4.1-2 .2" />
                        </svg>
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-start mb-5">
                <div class="flex items-center h-5">
                    <input id="remember" v-model="form.remember" type="checkbox" :class="fbCheckbox">
                </div>
                <label for="remember" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">
                    {{ t('auth.remember_me') }}
                </label>
            </div>

            <button type="submit" :class="fbButton" :disabled="form.processing">
                <svg v-if="form.processing" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                {{ form.processing ? t('auth.signing_in') : t('auth.sign_in') }}
            </button>

            <p class="mt-5 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ t('auth.new_here') }}
                <Link :href="route('register')" :class="fbLink">
                    {{ t('auth.create_account') }}
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
