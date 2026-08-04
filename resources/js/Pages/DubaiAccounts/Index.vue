<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    partners: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const filterForm = useForm({
    search: props.filters.search ?? '',
    active: props.filters.active ?? '',
});

const applyFilters = () => {
    filterForm.get(route('dubai-accounts.index'), { preserveState: true, replace: true });
};

const destroy = (partner) => {
    if (!window.confirm(t('dubai_accounts.delete_confirm', { name: partner.name }))) return;
    router.delete(route('dubai-accounts.destroy', partner.id));
};
</script>

<template>
    <Head :title="t('dubai_accounts.title')" />
    <AppLayout>
        <template #header>{{ t('dubai_accounts.title') }}</template>
        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.operations')" :title="t('dubai_accounts.title')" :subtitle="t('dubai_accounts.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('dubai-accounts.create')" class="btn btn-erp">
                    {{ t('dubai_accounts.add') }}
                </Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-5">
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="form-control form-erp-control"
                        :placeholder="t('dubai_accounts.search')"
                    />
                </div>
                <div class="col-md-3">
                    <select v-model="filterForm.active" class="form-select form-erp-control">
                        <option value="">{{ t('common.all') }}</option>
                        <option value="1">{{ t('common.active') }}</option>
                        <option value="0">{{ t('common.inactive') }}</option>
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
                            <th class="ps-4">{{ t('dubai_accounts.partner') }}</th>
                            <th>{{ t('dubai_accounts.contact') }}</th>
                            <th class="text-end">{{ t('dubai_accounts.open_balance') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="partners.data.length === 0">
                            <td colspan="5">
                                <EmptyState icon="D">{{ t('dubai_accounts.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="partner in partners.data" :key="partner.id">
                            <td class="ps-4">
                                <Link :href="route('dubai-accounts.show', partner.id)" class="fw-semibold text-decoration-none">
                                    {{ partner.name }}
                                </Link>
                            </td>
                            <td>
                                <div>{{ partner.contact_name || '—' }}</div>
                                <div v-if="partner.contact_phone" class="small text-secondary">{{ partner.contact_phone }}</div>
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="partner.open_balance" tone="balance" :currency="partner.currency" />
                            </td>
                            <td>
                                <StatusBadge
                                    :tone="partner.is_active ? 'success' : 'neutral'"
                                    :label="partner.is_active ? t('common.active') : t('common.inactive')"
                                />
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <Link :href="route('dubai-accounts.show', partner.id)" class="btn btn-sm btn-erp-ghost">
                                        {{ t('dubai_accounts.ledger') }}
                                    </Link>
                                    <Link
                                        v-if="canManage"
                                        :href="route('dubai-accounts.edit', partner.id)"
                                        class="btn btn-sm btn-erp-ghost"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroy(partner)"
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
