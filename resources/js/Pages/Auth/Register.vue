<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { fbButton, fbInput, fbLabel, fbLink } from '@/flowbite';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout :title="t('auth.create_account')" :subtitle="t('auth.create_account_subtitle')">
        <Head :title="t('auth.create_account')" />

        <form @submit.prevent="submit">
            <div class="mb-5">
                <label for="name" :class="fbLabel">{{ t('auth.full_name') }}</label>
                <input id="name" v-model="form.name" type="text" :class="fbInput" required autofocus autocomplete="name">
                <InputError :message="form.errors.name" />
            </div>
            <div class="mb-5">
                <label for="email" :class="fbLabel">{{ t('auth.email') }}</label>
                <input id="email" v-model="form.email" type="email" :class="fbInput" required autocomplete="username">
                <InputError :message="form.errors.email" />
            </div>
            <div class="mb-5">
                <label for="password" :class="fbLabel">{{ t('auth.password') }}</label>
                <input id="password" v-model="form.password" type="password" :class="fbInput" required autocomplete="new-password">
                <InputError :message="form.errors.password" />
            </div>
            <div class="mb-5">
                <label for="password_confirmation" :class="fbLabel">{{ t('auth.confirm_password') }}</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" :class="fbInput" required autocomplete="new-password">
                <InputError :message="form.errors.password_confirmation" />
            </div>
            <button type="submit" :class="fbButton" :disabled="form.processing">
                {{ form.processing ? t('auth.creating') : t('auth.create_account') }}
            </button>
            <p class="mt-5 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ t('auth.already_registered') }}
                <Link :href="route('login')" :class="fbLink">{{ t('auth.sign_in') }}</Link>
            </p>
        </form>
    </GuestLayout>
</template>
