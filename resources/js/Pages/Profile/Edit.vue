<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const roles = computed(() => user.value.roles ?? []);
const initials = computed(() => {
    const name = String(user.value.name ?? '').trim();
    if (!name) {
        return 'U';
    }

    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
});
</script>

<template>
    <Head :title="t('profile.title')" />

    <AppLayout>
        <template #header>{{ t('profile.title') }}</template>

        <PageHeader :kicker="t('profile.kicker')" :title="t('profile.title')" :subtitle="t('profile.subtitle')" />

        <div class="erp-card p-4 mb-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="erp-profile-avatar" aria-hidden="true">{{ initials }}</div>
                <div class="min-w-0">
                    <h2 class="h5 erp-display mb-1 text-break">{{ user.name }}</h2>
                    <p class="text-secondary small mb-2 text-break">{{ user.email }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <StatusBadge
                            v-for="role in roles"
                            :key="role"
                            tone="info"
                            :label="role"
                            :dot="false"
                        />
                        <StatusBadge v-if="!roles.length" tone="neutral" :label="t('profile.no_role')" :dot="false" />
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>
            <div class="col-lg-6">
                <UpdatePasswordForm />
            </div>
        </div>

        <DeleteUserForm />
    </AppLayout>
</template>
