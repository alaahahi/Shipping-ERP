<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
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
    <GuestLayout
        :title="t('auth.confirm_password_title')"
        :subtitle="t('auth.confirm_password_text')"
    >
        <Head :title="t('auth.confirm_password_title')" />

        <form class="d-grid gap-3" @submit.prevent="submit">
            <div>
                <label for="password" class="form-erp-label">{{ t('auth.password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="form-control form-erp-control"
                    required
                    autofocus
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <button
                type="submit"
                class="btn btn-erp w-100"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.saving') : t('auth.confirm') }}
            </button>
        </form>
    </GuestLayout>
</template>
