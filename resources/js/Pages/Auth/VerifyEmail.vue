<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { fbAlertSuccess, fbButton, fbLink } from '@/flowbite';
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
    <GuestLayout :title="t('auth.verify_email')" :subtitle="t('auth.verify_email_text')">
        <Head :title="t('auth.verify_email')" />

        <div v-if="verificationLinkSent" :class="fbAlertSuccess" role="status">
            {{ t('auth.verify_link_sent') }}
        </div>

        <form @submit.prevent="submit">
            <button type="submit" :class="fbButton" :disabled="form.processing">
                {{ form.processing ? t('auth.sending') : t('auth.resend_verification') }}
            </button>
            <p class="mt-5 text-center text-sm text-gray-500 dark:text-gray-400">
                <Link :href="route('logout')" method="post" as="button" :class="fbLink" class="border-0 bg-transparent p-0">
                    {{ t('nav.logout') }}
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
