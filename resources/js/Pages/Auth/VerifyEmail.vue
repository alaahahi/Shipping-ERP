<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: {
        type: String,
    },
});

const { t } = useI18n();
const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout
        :title="t('auth.verify_email')"
        :subtitle="t('auth.verify_email_text')"
    >
        <Head :title="t('auth.verify_email')" />

        <div v-if="verificationLinkSent" class="alert alert-success py-2 px-3 mb-3" role="status">
            {{ t('auth.verify_link_sent') }}
        </div>

        <form class="d-grid gap-3" @submit.prevent="submit">
            <button
                type="submit"
                class="btn btn-erp w-100"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.sending') : t('auth.resend_verification') }}
            </button>

            <p class="text-center text-secondary mb-0 small">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="fw-semibold text-decoration-none border-0 bg-transparent p-0"
                >
                    {{ t('nav.logout') }}
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
