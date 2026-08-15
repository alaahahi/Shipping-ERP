<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { fbAlertSuccess, fbButton, fbInput, fbLabel, fbLink } from '@/flowbite';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    status: {
        type: String,
    },
});

const { t } = useI18n();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout :title="t('auth.reset_password')" :subtitle="t('auth.reset_subtitle')">
        <Head :title="t('auth.forgot_password')" />

        <div v-if="status" :class="fbAlertSuccess" role="status">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div class="mb-5">
                <label for="email" :class="fbLabel">{{ t('auth.email') }}</label>
                <input id="email" v-model="form.email" type="email" :class="fbInput" required autofocus autocomplete="username">
                <InputError :message="form.errors.email" />
            </div>
            <button type="submit" :class="fbButton" :disabled="form.processing">
                {{ form.processing ? t('auth.sending') : t('auth.email_reset_link') }}
            </button>
            <p class="mt-5 text-center text-sm text-gray-500 dark:text-gray-400">
                <Link :href="route('login')" :class="fbLink">{{ t('auth.back_to_sign_in') }}</Link>
            </p>
        </form>
    </GuestLayout>
</template>
