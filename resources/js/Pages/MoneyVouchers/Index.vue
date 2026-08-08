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
    vouchers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const filterForm = useForm({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    currency: props.filters.currency ?? '',
});

const applyFilters = () => {
    filterForm.get(route('money-vouchers.index'), { preserveState: true, replace: true });
};

const destroy = (voucher) => {
    if (!window.confirm(t('money_vouchers.delete_confirm', { number: voucher.voucher_number }))) return;
    router.delete(route('money-vouchers.destroy', voucher.id));
};
</script>

<template>
    <Head :title="t('money_vouchers.title')" />
    <AppLayout>
        <template #header>{{ t('money_vouchers.title') }}</template>
        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.finance')" :title="t('money_vouchers.title')" :subtitle="t('money_vouchers.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('money-vouchers.create')" class="btn btn-erp">
                    {{ t('money_vouchers.add') }}
                </Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-3">
                    <input v-model="filterForm.search" type="search" class="form-control form-erp-control" :placeholder="t('money_vouchers.search')" />
                </div>
                <div class="col-md-2">
                    <select v-model="filterForm.type" class="form-select form-erp-control">
                        <option value="">{{ t('money_vouchers.all_types') }}</option>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select v-model="filterForm.status" class="form-select form-erp-control">
                        <option value="">{{ t('common.all') }}</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select v-model="filterForm.currency" class="form-select form-erp-control">
                        <option value="">{{ t('common.currency') }}</option>
                        <option value="USD">USD</option>
                        <option value="AED">AED</option>
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
                            <th class="ps-4">{{ t('money_vouchers.number') }}</th>
                            <th>{{ t('money_vouchers.type') }}</th>
                            <th>{{ t('common.date') }}</th>
                            <th>{{ t('money_vouchers.counterparty') }}</th>
                            <th class="text-end">{{ t('common.amount') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vouchers.data.length === 0">
                            <td colspan="7"><EmptyState icon="$">{{ t('money_vouchers.none') }}</EmptyState></td>
                        </tr>
                        <tr
                            v-for="voucher in vouchers.data"
                            :key="voucher.id"
                            :class="voucher.type === 'receipt' ? 'is-receipt' : 'is-payment'"
                        >
                            <td class="ps-4">
                                <Link :href="route('money-vouchers.show', voucher.id)" class="fw-semibold text-decoration-none">
                                    {{ voucher.voucher_number }}
                                </Link>
                                <div class="small text-secondary">{{ voucher.voyage_number || voucher.payment_account }}</div>
                            </td>
                            <td><StatusBadge :tone="voucher.type_tone" :label="voucher.type_label" :dot="false" /></td>
                            <td>{{ voucher.voucher_date }}</td>
                            <td>{{ voucher.company_name || voucher.counterparty || '—' }}</td>
                            <td class="text-end font-monospace">{{ voucher.amount }} {{ voucher.currency }}</td>
                            <td><StatusBadge :tone="voucher.status_tone" :label="voucher.status_label" /></td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <Link :href="route('money-vouchers.show', voucher.id)" class="btn btn-sm btn-erp-ghost">{{ t('common.open') }}</Link>
                                    <Link
                                        v-if="canManage && voucher.is_draft"
                                        :href="route('money-vouchers.edit', voucher.id)"
                                        class="btn btn-sm btn-erp-ghost"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        v-if="canManage && voucher.is_draft"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroy(voucher)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="vouchers.prev_page_url || vouchers.next_page_url"
                class="d-flex justify-content-between align-items-center p-3 border-top"
            >
                <Link v-if="vouchers.prev_page_url" :href="vouchers.prev_page_url" class="btn btn-sm btn-erp-ghost">{{ t('common.prev') }}</Link>
                <span v-else></span>
                <span class="small text-secondary">{{ vouchers.from }}–{{ vouchers.to }} / {{ vouchers.total }}</span>
                <Link v-if="vouchers.next_page_url" :href="vouchers.next_page_url" class="btn btn-sm btn-erp-ghost">{{ t('common.next') }}</Link>
                <span v-else></span>
            </div>
        </div>
    </AppLayout>
</template>
