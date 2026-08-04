<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    <GuestLayout
        :title="t('auth.sign_in')"
        :subtitle="t('auth.sign_in_subtitle')"
    >
        <Head :title="t('auth.sign_in')" />

        <div v-if="status" class="alert alert-success py-2 px-3 mb-3" role="status">
            {{ status }}
        </div>

        <form class="d-grid gap-3" @submit.prevent="submit">
            <div>
                <label for="email" class="form-erp-label">{{ t('auth.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="form-control form-erp-control"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-erp-label mb-0">{{ t('auth.password') }}</label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="small text-decoration-none"
                    >
                        {{ t('auth.forgot_password') }}
                    </Link>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="form-control form-erp-control mt-2"
                    required
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="form-check form-check-erp">
                <input
                    id="remember"
                    v-model="form.remember"
                    class="form-check-input"
                    type="checkbox"
                />
                <label class="form-check-label text-secondary" for="remember">
                    {{ t('auth.remember_me') }}
                </label>
            </div>

            <button
                type="submit"
                class="btn btn-erp w-100"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.signing_in') : t('auth.sign_in') }}
            </button>

            <p class="text-center text-secondary mb-0 small">
                {{ t('auth.new_here') }}
                <Link :href="route('register')" class="fw-semibold text-decoration-none">
                    {{ t('auth.create_account') }}
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
