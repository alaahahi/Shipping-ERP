<script setup>
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    cars: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    printedAt: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();
const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const title = computed(() => t('land_trips.print_title', { company: props.company.name }));
const backHref = computed(() => {
    const base = route('land-trips.companies.show', props.company.id);
    const params = new URLSearchParams();
    if (props.filters.location_status_id) {
        params.set('location_status_id', String(props.filters.location_status_id));
    }
    if (props.filters.sort && props.filters.sort !== 'newest') {
        params.set('sort', String(props.filters.sort));
    }
    const query = params.toString();

    return query ? `${base}?${query}` : base;
});
const scopeLabel = computed(() => {
    if (props.filters.selected) {
        return t('land_trips.print_selected', { count: props.summary.count || 0 });
    }
    if (props.filters.search) {
        return `${t('common.search')}: ${props.filters.search}`;
    }

    return t('land_trips.all_cars');
});
const printPage = () => window.print();
</script>

<template>
    <Head :title="title" />

    <PrintLayout>
        <template #toolbar-start>
            <a class="btn btn-erp-ghost btn-sm" :href="backHref">
                {{ t('land_trips.back_company_cars') }}
            </a>
        </template>
        <template #toolbar-actions>
            <button type="button" class="btn btn-erp btn-sm" @click="printPage">
                {{ t('common.print') }}
            </button>
        </template>

        <header class="erp-print-header">
            <div class="erp-print-kicker">{{ companyName }}</div>
            <h1 class="erp-print-title">{{ company.name }}</h1>
            <p class="erp-print-meta mb-0">
                {{ t('land_trips.cars') }}
                <span> · {{ scopeLabel }}</span>
                <span> · {{ t('land_trips.printed_at') }}: {{ printedAt }}</span>
            </p>
        </header>

        <section class="erp-print-summary">
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('land_trips.cars_count') }}</div>
                <div class="erp-print-summary-value">{{ summary.count || 0 }}</div>
            </div>
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('land_trips.car_price') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="summary.price" currency="USD" show-zero />
                </div>
            </div>
        </section>

        <table class="erp-print-table">
            <thead>
                <tr>
                    <th class="erp-print-col-serial">{{ t('land_trips.sequence') }}</th>
                    <th>{{ t('land_trips.chassis') }}</th>
                    <th>{{ t('land_trips.model') }}</th>
                    <th>{{ t('land_trips.color') }}</th>
                    <th>{{ t('land_trips.year') }}</th>
                    <th>{{ t('land_trips.cmr_waybill') }}</th>
                    <th class="text-end">{{ t('land_trips.car_price') }}</th>
                    <th>{{ t('land_trips.location_status') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!cars.length">
                    <td colspan="8">{{ t('land_trips.empty_cars') }}</td>
                </tr>
                <tr v-for="(car, index) in cars" :key="car.id">
                    <td class="erp-print-col-serial">{{ index + 1 }}</td>
                    <td class="font-monospace">{{ car.chassis_no || '—' }}</td>
                    <td>{{ car.model || car.description || '—' }}</td>
                    <td>{{ car.color || '—' }}</td>
                    <td>{{ car.year || '—' }}</td>
                    <td>{{ car.cmr_waybill || '—' }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="car.price" currency="USD" show-zero />
                    </td>
                    <td>{{ car.location_status_label || '—' }}</td>
                </tr>
                <tr v-if="cars.length" class="erp-print-total-row">
                    <td colspan="6">{{ t('land_trips.cars_total') }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="summary.price" currency="USD" show-zero />
                    </td>
                    <td>{{ summary.count || 0 }}</td>
                </tr>
            </tbody>
        </table>

        <footer class="erp-print-footer">{{ t('land_trips.print_footer') }}</footer>
    </PrintLayout>
</template>
