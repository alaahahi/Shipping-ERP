<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
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
    <GuestLayout
        :title="t('auth.create_account')"
        :subtitle="t('auth.create_account_subtitle')"
    >
        <Head :title="t('auth.create_account')" />

        <form class="d-grid gap-3" @submit.prevent="submit">
            <div>
                <label for="name" class="form-erp-label">{{ t('auth.full_name') }}</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="form-control form-erp-control"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="form-erp-label">{{ t('auth.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="form-control form-erp-control"
                    required
                    autocomplete="username"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="form-erp-label">{{ t('auth.password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="form-control form-erp-control"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="form-erp-label">
                    {{ t('auth.confirm_password') }}
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="form-control form-erp-control"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <button
                type="submit"
                class="btn btn-erp w-100"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.creating') : t('auth.create_account') }}
            </button>

            <p class="text-center text-secondary mb-0 small">
                {{ t('auth.already_registered') }}
                <Link :href="route('login')" class="fw-semibold text-decoration-none">
                    {{ t('auth.sign_in') }}
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
