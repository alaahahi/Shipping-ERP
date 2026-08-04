<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    roles: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
</script>

<template>
    <Head :title="t('roles.title')" />
    <AppLayout>
        <template #header>{{ t('roles.title') }}</template>

        <div v-if="success" class="alert alert-success border-0 shadow-sm mb-3" role="status">{{ success }}</div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 erp-display mb-1">{{ t('roles.system_roles') }}</h2>
                    <p class="text-secondary small mb-0">{{ t('roles.help') }}</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('common.role') }}</th>
                            <th>{{ t('roles.users_count') }}</th>
                            <th>{{ t('roles.permissions_count') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="role in roles" :key="role.id">
                            <td class="ps-4">
                                <span class="fw-semibold text-capitalize">{{ role.name }}</span>
                            </td>
                            <td><span class="badge text-bg-light text-secondary">{{ role.users_count }}</span></td>
                            <td><span class="badge text-bg-light text-secondary">{{ role.permissions_count }}</span></td>
                            <td class="text-end pe-4">
                                <Link :href="route('roles.edit', role.id)" class="btn btn-sm btn-erp-ghost">
                                    {{ t('roles.edit_permissions') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
