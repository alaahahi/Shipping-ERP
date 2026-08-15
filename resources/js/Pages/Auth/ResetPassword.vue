<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { fbButton, fbInput, fbLabel } from '@/flowbite';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const { t } = useI18n();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout :title="t('auth.choose_new_password')" :subtitle="t('auth.choose_password_subtitle')">
        <Head :title="t('auth.reset_password')" />

        <form @submit.prevent="submit">
            <div class="mb-5">
                <label for="email" :class="fbLabel">{{ t('auth.email') }}</label>
                <input id="email" v-model="form.email" type="email" :class="fbInput" required autofocus autocomplete="username">
                <InputError :message="form.errors.email" />
            </div>
            <div class="mb-5">
                <label for="password" :class="fbLabel">{{ t('auth.new_password') }}</label>
                <input id="password" v-model="form.password" type="password" :class="fbInput" required autocomplete="new-password">
                <InputError :message="form.errors.password" />
            </div>
            <div class="mb-5">
                <label for="password_confirmation" :class="fbLabel">{{ t('auth.confirm_password') }}</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" :class="fbInput" required autocomplete="new-password">
                <InputError :message="form.errors.password_confirmation" />
            </div>
            <button type="submit" :class="fbButton" :disabled="form.processing">
                {{ form.processing ? t('auth.saving') : t('auth.reset_password') }}
            </button>
        </form>
    </GuestLayout>
</template>
