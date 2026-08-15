<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { fbButton, fbInput, fbLabel } from '@/flowbite';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout :title="t('auth.confirm_password_title')" :subtitle="t('auth.confirm_password_text')">
        <Head :title="t('auth.confirm_password_title')" />

        <form @submit.prevent="submit">
            <div class="mb-5">
                <label for="password" :class="fbLabel">{{ t('auth.password') }}</label>
                <input id="password" v-model="form.password" type="password" :class="fbInput" required autofocus autocomplete="current-password">
                <InputError :message="form.errors.password" />
            </div>
            <button type="submit" :class="fbButton" :disabled="form.processing">
                {{ form.processing ? t('auth.saving') : t('auth.confirm') }}
            </button>
        </form>
    </GuestLayout>
</template>
