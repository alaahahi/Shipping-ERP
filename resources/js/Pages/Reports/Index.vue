<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReportsNav from '@/Components/ReportsNav.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    overview: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const filterForm = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const applyFilters = () => {
    filterForm.get(route('reports.index'), { preserveState: true, replace: true });
};

const voyagesExportUrl = (name) => {
    const params = new URLSearchParams();
    if (filterForm.date_from) params.set('date_from', filterForm.date_from);
    if (filterForm.date_to) params.set('date_to', filterForm.date_to);
    const query = params.toString();
    return query ? `${route(name)}?${query}` : route(name);
};
</script>

<template>
    <Head :title="t('reports.title')" />
    <AppLayout>
        <template #header>{{ t('reports.title') }}</template>

        <ReportsNav current="overview" />

        <PageHeader :kicker="t('nav.finance')" :title="t('reports.title')" :subtitle="t('reports.help')">
            <template #actions>
                <a :href="voyagesExportUrl('reports.voyages.export.excel')" class="btn btn-erp-ghost">
                    {{ t('reports.export_excel') }}
                </a>
                <a :href="voyagesExportUrl('reports.voyages.export.pdf')" class="btn btn-erp-ghost">
                    {{ t('reports.export_pdf') }}
                </a>
                <Link :href="route('reports.voyages')" class="btn btn-erp">{{ t('reports.voyages') }}</Link>
            </template>
        </PageHeader>

        <form class="erp-card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('accounts.date_from') }}</label>
                    <input v-model="filterForm.date_from" type="date" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('accounts.date_to') }}</label>
                    <input v-model="filterForm.date_to" type="date" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-erp-ghost">{{ t('common.filter') }}</button>
                </div>
            </div>
        </form>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('reports.voyages_in_period') }}</div>
                    <p class="erp-stat-value">{{ overview.voyages_count }}</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('reports.cars_in_period') }}</div>
                    <p class="erp-stat-value">{{ overview.cars_count }}</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('voyage_settlements.revenue_usd') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.2rem">{{ overview.revenue_usd }}</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('voyage_settlements.profit_usd') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.2rem">{{ overview.profit_usd }}</p>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="erp-card p-4 h-100">
                    <h3 class="erp-panel-title mb-3">{{ t('reports.status_mix') }}</h3>
                    <div class="d-grid gap-2">
                        <div class="d-flex justify-content-between"><span>{{ t('reports.active') }}</span><strong>{{ overview.voyages_active }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ t('reports.draft') }}</span><strong>{{ overview.voyages_draft }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ t('reports.closed') }}</span><strong>{{ overview.voyages_closed }}</strong></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="erp-card p-4 h-100">
                    <h3 class="erp-panel-title mb-3">{{ t('reports.costs') }}</h3>
                    <div class="d-grid gap-2">
                        <div class="d-flex justify-content-between"><span>{{ t('voyage_settlements.expenses_usd') }}</span><strong class="font-monospace">{{ overview.expenses_usd }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ t('voyage_settlements.expenses_aed') }}</span><strong class="font-monospace">{{ overview.expenses_aed }}</strong></div>
                        <div class="d-flex justify-content-between"><span>{{ t('voyage_settlements.commission_aed') }}</span><strong class="font-monospace">{{ overview.commission_aed }}</strong></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="erp-card p-4 h-100">
                    <h3 class="erp-panel-title mb-3">{{ t('reports.quick') }}</h3>
                    <p class="small text-secondary mb-3">{{ t('reports.note') }}</p>
                    <Link :href="route('reports.voyages')" class="btn btn-erp w-100 mb-2">{{ t('reports.open_voyages') }}</Link>
                    <Link :href="route('reports.land-trips')" class="btn btn-erp-ghost w-100">{{ t('reports.land_trips') }}</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
