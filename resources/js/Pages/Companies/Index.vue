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
    companies: { type: Object, required: true },
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
    filterForm.get(route('companies.index'), { preserveState: true, replace: true });
};

const destroy = (company) => {
    if (!window.confirm(t('companies.delete_confirm', { name: company.name }))) return;
    router.delete(route('companies.destroy', company.id));
};
</script>

<template>
    <Head :title="t('companies.title')" />
    <AppLayout>
        <template #header>{{ t('companies.title') }}</template>
        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.operations')" :title="t('companies.title')" :subtitle="t('companies.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('companies.create')" class="btn btn-erp">
                    {{ t('companies.add') }}
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
                        :placeholder="t('companies.search')"
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
                            <th class="ps-4">{{ t('companies.name') }}</th>
                            <th>{{ t('companies.contact') }}</th>
                            <th>{{ t('common.email') }}</th>
                            <th class="text-end">{{ t('companies.voyages') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="companies.data.length === 0">
                            <td colspan="6"><EmptyState icon="S">{{ t('companies.none') }}</EmptyState></td>
                        </tr>
                        <tr v-for="company in companies.data" :key="company.id">
                            <td class="ps-4">
                                <Link :href="route('companies.show', company.id)" class="fw-semibold text-decoration-none">
                                    {{ company.name }}
                                </Link>
                                <div class="small text-secondary">{{ company.address || '—' }}</div>
                            </td>
                            <td>
                                <div>{{ company.contact_name || '—' }}</div>
                                <div class="small text-secondary">{{ company.contact_phone || '' }}</div>
                            </td>
                            <td>{{ company.email || '—' }}</td>
                            <td class="text-end font-monospace">{{ company.voyages_count }}</td>
                            <td>
                                <StatusBadge
                                    :tone="company.is_active ? 'success' : 'neutral'"
                                    :label="company.is_active ? t('common.active') : t('common.inactive')"
                                />
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <Link :href="route('companies.show', company.id)" class="btn btn-sm btn-erp-ghost">
                                        {{ t('common.open') }}
                                    </Link>
                                    <Link
                                        v-if="canManage"
                                        :href="route('companies.edit', company.id)"
                                        class="btn btn-sm btn-erp-ghost"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        v-if="canManage && company.voyages_count === 0"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroy(company)"
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
                v-if="companies.prev_page_url || companies.next_page_url"
                class="d-flex justify-content-between align-items-center p-3 border-top"
            >
                <Link v-if="companies.prev_page_url" :href="companies.prev_page_url" class="btn btn-sm btn-erp-ghost">
                    {{ t('common.prev') }}
                </Link>
                <span v-else />
                <span class="small text-secondary">{{ companies.from }}–{{ companies.to }} / {{ companies.total }}</span>
                <Link v-if="companies.next_page_url" :href="companies.next_page_url" class="btn btn-sm btn-erp-ghost">
                    {{ t('common.next') }}
                </Link>
                <span v-else />
            </div>
        </div>
    </AppLayout>
</template>
