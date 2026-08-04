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
    accounts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const filterForm = useForm({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    currency: props.filters.currency ?? '',
});

const applyFilters = () => {
    filterForm.get(route('accounts.index'), { preserveState: true, replace: true });
};

const destroy = (account) => {
    if (!window.confirm(t('accounts.delete_confirm', { code: account.code }))) return;
    router.delete(route('accounts.destroy', account.id));
};
</script>

<template>
    <Head :title="t('accounts.title')" />
    <AppLayout>
        <template #header>{{ t('accounts.title') }}</template>

        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.finance')" :title="t('accounts.title')" :subtitle="t('accounts.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('accounts.create')" class="btn btn-erp">{{ t('accounts.add') }}</Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-4">
                    <input v-model="filterForm.search" type="search" class="form-control form-erp-control" :placeholder="t('accounts.search_placeholder')" />
                </div>
                <div class="col-md-3">
                    <select v-model="filterForm.type" class="form-select form-erp-control">
                        <option value="">{{ t('accounts.all_types') }}</option>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
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
                            <th class="ps-4">{{ t('accounts.code') }}</th>
                            <th>{{ t('common.name') }}</th>
                            <th>{{ t('accounts.type') }}</th>
                            <th>{{ t('common.currency') }}</th>
                            <th class="text-end">{{ t('accounts.balance') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="accounts.data.length === 0">
                            <td colspan="6">
                                <EmptyState icon="A">{{ t('accounts.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="account in accounts.data" :key="account.id">
                            <td class="ps-4 fw-semibold">
                                <Link :href="route('accounts.show', account.id)" class="text-decoration-none">
                                    {{ account.code }}
                                </Link>
                            </td>
                            <td>
                                <Link :href="route('accounts.show', account.id)" class="text-decoration-none text-body">
                                    {{ account.name }}
                                </Link>
                                <StatusBadge
                                    v-if="account.is_system"
                                    class="ms-1"
                                    tone="neutral"
                                    :label="t('accounts.system')"
                                    :dot="false"
                                />
                            </td>
                            <td>{{ account.type_label }}</td>
                            <td>
                                <StatusBadge
                                    :tone="account.currency === 'AED' ? 'success' : 'neutral'"
                                    :label="account.currency"
                                    :dot="false"
                                />
                            </td>
                            <td class="text-end font-monospace">
                                <Link :href="route('accounts.show', account.id)" class="text-decoration-none text-body">
                                    {{ account.balance }}
                                </Link>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-2">
                                    <Link :href="route('accounts.show', account.id)" class="btn btn-sm btn-erp-ghost">{{ t('accounts.ledger') }}</Link>
                                    <Link v-if="canManage" :href="route('accounts.edit', account.id)" class="btn btn-sm btn-erp-ghost">{{ t('common.edit') }}</Link>
                                    <button
                                        v-if="canManage && !account.is_system"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroy(account)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
