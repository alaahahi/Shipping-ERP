<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
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
    <GuestLayout
        :title="t('auth.choose_new_password')"
        :subtitle="t('auth.choose_password_subtitle')"
    >
        <Head :title="t('auth.reset_password')" />

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
                <label for="password" class="form-erp-label">{{ t('auth.new_password') }}</label>
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
                {{ form.processing ? t('auth.saving') : t('auth.reset_password') }}
            </button>
        </form>
    </GuestLayout>
</template>
