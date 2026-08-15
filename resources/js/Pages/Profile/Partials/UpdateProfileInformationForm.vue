<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const { t } = useI18n();
const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), { preserveScroll: true });
};
</script>

<template>
    <section class="erp-card p-4 h-100">
        <header class="mb-4">
            <h2 class="h5 erp-display mb-1">{{ t('profile.info_title') }}</h2>
            <p class="text-secondary small mb-0">{{ t('profile.info_help') }}</p>
        </header>

        <form class="d-grid gap-3" @submit.prevent="submit">
            <div>
                <label for="name" class="form-erp-label">{{ t('auth.full_name') }}</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="form-control form-erp-control"
                    required
                    autocomplete="name"
                >
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
                >
                <InputError :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="small">
                <p class="mb-2">
                    {{ t('profile.unverified') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="btn btn-link btn-sm p-0 align-baseline"
                    >
                        {{ t('profile.resend_verification') }}
                    </Link>
                </p>
                <p v-if="status === 'verification-link-sent'" class="text-success mb-0">
                    {{ t('profile.verify_sent') }}
                </p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3 mt-1">
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('common.save') }}
                </button>
                <Transition
                    enter-active-class="transition-opacity"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity"
                    leave-to-class="opacity-0"
                >
                    <span v-if="form.recentlySuccessful" class="small text-success">{{ t('profile.saved') }}</span>
                </Transition>
            </div>
        </form>
    </section>
</template>
