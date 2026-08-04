<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    entries: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    currency: props.filters.currency ?? '',
});

const applyFilters = () => {
    filterForm.get(route('journals.index'), { preserveState: true, replace: true });
};

const statusTone = (status) => {
    if (status === 'posted') return 'success';
    if (status === 'void') return 'danger';
    return 'neutral';
};
</script>

<template>
    <Head :title="t('journals.title')" />
    <AppLayout>
        <template #header>{{ t('journals.title') }}</template>

        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.finance')" :title="t('journals.title')" :subtitle="t('journals.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('journals.create')" class="btn btn-erp">{{ t('journals.new') }}</Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-4">
                    <input v-model="filterForm.search" type="search" class="form-control form-erp-control" :placeholder="t('journals.search_placeholder')" />
                </div>
                <div class="col-md-3">
                    <select v-model="filterForm.status" class="form-select form-erp-control">
                        <option value="">{{ t('journals.all_statuses') }}</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select v-model="filterForm.currency" class="form-select form-erp-control">
                        <option value="">{{ t('accounts.all_currencies') }}</option>
                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.value }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-erp-ghost w-100">{{ t('common.filter') }}</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('journals.voucher') }}</th>
                            <th>{{ t('common.date') }}</th>
                            <th>{{ t('common.description') }}</th>
                            <th>{{ t('common.currency') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end">{{ t('common.amount') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="entries.data.length === 0">
                            <td colspan="7">
                                <EmptyState icon="J">{{ t('journals.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="entry in entries.data" :key="entry.id">
                            <td class="ps-4 fw-semibold">{{ entry.voucher_number }}</td>
                            <td>{{ entry.entry_date }}</td>
                            <td>{{ entry.description }}</td>
                            <td>
                                <StatusBadge
                                    :tone="entry.currency === 'AED' ? 'success' : 'neutral'"
                                    :label="entry.currency"
                                    :dot="false"
                                />
                            </td>
                            <td>
                                <StatusBadge :tone="statusTone(entry.status)" :label="entry.status_label" />
                            </td>
                            <td class="text-end font-monospace">{{ entry.amount }}</td>
                            <td class="text-end pe-4">
                                <Link :href="route('journals.show', entry.id)" class="btn btn-sm btn-erp-ghost">{{ t('journals.open') }}</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
