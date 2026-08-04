<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
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
    <GuestLayout
        :title="t('auth.reset_password')"
        :subtitle="t('auth.reset_subtitle')"
    >
        <Head :title="t('auth.forgot_password')" />

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

            <button
                type="submit"
                class="btn btn-erp w-100"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.sending') : t('auth.email_reset_link') }}
            </button>

            <p class="text-center text-secondary mb-0 small">
                <Link :href="route('login')" class="fw-semibold text-decoration-none">
                    {{ t('auth.back_to_sign_in') }}
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
