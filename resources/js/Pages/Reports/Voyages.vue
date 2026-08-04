<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    rows: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    ships: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const { t } = useI18n();

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    ship_id: props.filters.ship_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const applyFilters = () => {
    filterForm.get(route('reports.voyages'), { preserveState: true, replace: true });
};

const exportUrl = (name) => {
    const params = new URLSearchParams();
    Object.entries(filterForm.data()).forEach(([key, value]) => {
        if (value !== null && value !== undefined && String(value).length > 0) {
            params.set(key, String(value));
        }
    });
    const query = params.toString();
    return query ? `${route(name)}?${query}` : route(name);
};
</script>

<template>
    <Head :title="t('reports.voyages')" />
    <AppLayout>
        <template #header>{{ t('reports.voyages') }}</template>

        <div class="mb-3">
            <Link :href="route('reports.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('reports.back') }}
            </Link>
        </div>

        <PageHeader :title="t('reports.voyages')" :subtitle="t('reports.voyages_help')">
            <template #actions>
                <a :href="exportUrl('reports.voyages.export.excel')" class="btn btn-erp-ghost">
                    {{ t('reports.export_excel') }}
                </a>
                <a :href="exportUrl('reports.voyages.export.pdf')" class="btn btn-erp">
                    {{ t('reports.export_pdf') }}
                </a>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-3">
                    <input v-model="filterForm.search" type="search" class="form-control form-erp-control" :placeholder="t('voyages.search_placeholder')" />
                </div>
                <div class="col-md-2">
                    <select v-model="filterForm.status" class="form-select form-erp-control">
                        <option value="">{{ t('voyages.all_statuses') }}</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select v-model="filterForm.ship_id" class="form-select form-erp-control">
                        <option value="">{{ t('voyages.all_ships') }}</option>
                        <option v-for="ship in ships" :key="ship.id" :value="ship.id">{{ ship.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input v-model="filterForm.date_from" type="date" class="form-control form-erp-control" />
                </div>
                <div class="col-md-2">
                    <input v-model="filterForm.date_to" type="date" class="form-control form-erp-control" />
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-erp-ghost w-100">{{ t('common.filter') }}</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('voyages.number') }}</th>
                            <th>{{ t('ships.name') }}</th>
                            <th>{{ t('voyages.sailing') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end">{{ t('voyage_cars.title') }}</th>
                            <th class="text-end">{{ t('voyage_settlements.revenue_usd') }}</th>
                            <th class="text-end">{{ t('voyage_settlements.expenses_usd') }}</th>
                            <th class="text-end pe-4">{{ t('voyage_settlements.profit_usd') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.data.length === 0">
                            <td colspan="8">
                                <EmptyState icon="R">{{ t('reports.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="row in rows.data" :key="row.id">
                            <td class="ps-4">
                                <Link :href="route('voyages.show', row.id)" class="fw-semibold text-decoration-none">
                                    {{ row.voyage_number }}
                                </Link>
                                <div class="small text-secondary">{{ row.route }}</div>
                            </td>
                            <td>{{ row.ship_name || '—' }}</td>
                            <td>{{ row.sailing_date }}</td>
                            <td><StatusBadge :tone="row.status_tone" :label="row.status_label" /></td>
                            <td class="text-end">{{ row.cars_count }}</td>
                            <td class="text-end font-monospace">{{ row.revenue_usd }}</td>
                            <td class="text-end font-monospace">{{ row.expenses_usd }}</td>
                            <td class="text-end pe-4 font-monospace fw-semibold">{{ row.profit_usd }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="rows.prev_page_url || rows.next_page_url"
                class="d-flex justify-content-between align-items-center p-3 border-top"
            >
                <Link v-if="rows.prev_page_url" :href="rows.prev_page_url" class="btn btn-sm btn-erp-ghost" preserve-scroll>
                    {{ t('common.prev') }}
                </Link>
                <span v-else></span>
                <span class="small text-secondary">{{ rows.from }}–{{ rows.to }} / {{ rows.total }}</span>
                <Link v-if="rows.next_page_url" :href="rows.next_page_url" class="btn btn-sm btn-erp-ghost" preserve-scroll>
                    {{ t('common.next') }}
                </Link>
                <span v-else></span>
            </div>
        </div>
    </AppLayout>
</template>
