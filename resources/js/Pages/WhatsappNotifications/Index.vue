<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    notifications: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    settings: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const flash = computed(() => page.props.flash ?? {});

const statusBadge = (status) => {
    switch (status) {
        case 'sent': return 'success';
        case 'queued': return 'info';
        case 'failed': return 'danger';
        default: return 'neutral';
    }
};

const statusLabel = (status) => t(`whatsapp.status_${status}`) ?? status;

const filterForm = useForm({
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    company_id: props.filters.company_id ?? '',
});

const applyFilters = () => {
    filterForm.get(route('whatsapp-notifications.index'), { preserveState: true });
};

const resetFilters = () => {
    filterForm.status = '';
    filterForm.type = '';
    filterForm.company_id = '';
    applyFilters();
};

const retry = (id) => {
    router.post(route('whatsapp-notifications.retry', id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('whatsapp.title')" />
    <AppLayout>
        <template #header>{{ t('whatsapp.title') }}</template>
        <FlashMessage :message="flash.success" tone="success" />
        <FlashMessage :message="flash.error" tone="danger" />

        <PageHeader :title="t('whatsapp.title')" :subtitle="t('whatsapp.subtitle')">
            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge" :class="settings.enabled ? 'bg-success' : 'bg-secondary'">
                        {{ settings.enabled ? t('whatsapp.enabled') : t('whatsapp.disabled') }}
                    </span>
                    <span class="small text-secondary">Tenant: {{ settings.tenant_id }}</span>
                </div>
            </template>
        </PageHeader>

        <div class="erp-card p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.status') }}</label>
                    <select v-model="filterForm.status" class="form-select form-erp-control" @change="applyFilters">
                        <option value="">{{ t('common.all') }}</option>
                        <option value="pending">{{ t('whatsapp.status_pending') }}</option>
                        <option value="queued">{{ t('whatsapp.status_queued') }}</option>
                        <option value="sent">{{ t('whatsapp.status_sent') }}</option>
                        <option value="failed">{{ t('whatsapp.status_failed') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.type') }}</label>
                    <select v-model="filterForm.type" class="form-select form-erp-control" @change="applyFilters">
                        <option value="">{{ t('common.all') }}</option>
                        <option value="voyage_loaded">{{ t('whatsapp.type_voyage_loaded') }}</option>
                        <option value="voyage_revenue_posted">{{ t('whatsapp.type_voyage_revenue_posted') }}</option>
                        <option value="payment_received">{{ t('whatsapp.type_payment_received') }}</option>
                        <option value="voyage_departed">{{ t('whatsapp.type_voyage_departed') }}</option>
                        <option value="voyage_arrived">{{ t('whatsapp.type_voyage_arrived') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-erp-ghost" @click="resetFilters">{{ t('common.reset') }}</button>
                </div>
            </div>
        </div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('common.date') }}</th>
                            <th>{{ t('companies.title') }}</th>
                            <th>{{ t('common.phone') }}</th>
                            <th>{{ t('common.type') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="notifications.data.length === 0">
                            <td colspan="6">
                                <EmptyState icon="💬">{{ t('whatsapp.empty') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="row in notifications.data" :key="row.id">
                            <td class="ps-4 small text-secondary">{{ row.created_at }}</td>
                            <td class="fw-semibold">{{ row.company?.name ?? '—' }}</td>
                            <td class="font-monospace small">{{ row.phone }}</td>
                            <td class="small">{{ statusLabel(row.type) }}</td>
                            <td><StatusBadge :tone="statusBadge(row.status)" :label="statusLabel(row.status)" /></td>
                            <td class="pe-4">
                                <button
                                    v-if="canManage && ['pending', 'failed'].includes(row.status)"
                                    class="btn btn-sm btn-erp"
                                    @click="retry(row.id)"
                                >
                                    {{ t('common.retry') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            <pagination v-if="notifications.links" :links="notifications.links" />
        </div>
    </AppLayout>
</template>
