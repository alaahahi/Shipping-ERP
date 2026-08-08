<script setup>
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    printedAt: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();
const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const isSold = computed(() => props.filters.sale_state === 'sold');
const title = computed(() => (isSold.value ? t('iran_cars.print_sold') : t('iran_cars.print_unsold')));
const colCount = computed(() => (isSold.value ? 9 : 7));
const printPage = () => window.print();
</script>

<template>
    <Head :title="title" />

    <PrintLayout>
        <template #toolbar-start>
            <a class="btn btn-erp-ghost btn-sm" :href="route('iran-cars.index', filters)">
                {{ t('common.back') }}
            </a>
        </template>
        <template #toolbar-actions>
            <button type="button" class="btn btn-erp btn-sm" @click="printPage">
                {{ t('common.print') }}
            </button>
        </template>

        <header class="erp-print-header">
            <div class="erp-print-kicker">{{ companyName }}</div>
            <h1 class="erp-print-title">{{ title }}</h1>
            <p class="erp-print-meta mb-0">
                {{ t('iran_cars.title') }}
                <span> · {{ t('iran_cars.printed_at') }}: {{ printedAt }}</span>
            </p>
        </header>

        <section class="erp-print-summary">
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('iran_cars.cars_count') }}</div>
                <div class="erp-print-summary-value">{{ summary.count || 0 }}</div>
            </div>
            <div v-if="!isSold" class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('iran_cars.list_price') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="summary.list_amount" currency="USD" show-zero />
                </div>
            </div>
            <template v-else>
                <div class="erp-print-summary-box">
                    <div class="erp-print-summary-label">{{ t('iran_cars.sale_price') }}</div>
                    <div class="erp-print-summary-value">
                        <MoneyAmount :value="summary.sale_amount" currency="USD" show-zero />
                    </div>
                </div>
                <div class="erp-print-summary-box">
                    <div class="erp-print-summary-label">{{ t('iran_cars.paid') }}</div>
                    <div class="erp-print-summary-value">
                        <MoneyAmount :value="summary.paid_amount" currency="USD" show-zero />
                    </div>
                </div>
                <div class="erp-print-summary-box">
                    <div class="erp-print-summary-label">{{ t('iran_cars.remaining') }}</div>
                    <div class="erp-print-summary-value">
                        <MoneyAmount :value="summary.remaining_amount" currency="USD" show-zero />
                    </div>
                </div>
            </template>
        </section>

        <table class="erp-print-table">
            <thead>
                <tr>
                    <th class="erp-print-col-serial">#</th>
                    <th>{{ t('iran_cars.model') }}</th>
                    <th>{{ t('iran_cars.year') }}</th>
                    <th>{{ t('iran_cars.color') }}</th>
                    <th>{{ t('iran_cars.vin') }}</th>
                    <th>{{ t('iran_cars.company') }}</th>
                    <th v-if="!isSold" class="text-end">{{ t('iran_cars.list_price') }}</th>
                    <template v-else>
                        <th class="text-end">{{ t('iran_cars.sale_price') }}</th>
                        <th class="text-end">{{ t('iran_cars.paid') }}</th>
                        <th class="text-end">{{ t('iran_cars.remaining') }}</th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template v-for="group in groups" :key="group.border">
                    <tr class="erp-print-month-row">
                        <td :colspan="colCount">{{ group.label }} · {{ group.count }}</td>
                    </tr>
                    <tr v-for="car in group.cars" :key="car.id">
                        <td class="erp-print-col-serial">{{ car.index }}</td>
                        <td>{{ car.model_name }}</td>
                        <td>{{ car.year || '—' }}</td>
                        <td>{{ car.color || '—' }}</td>
                        <td class="font-monospace">{{ car.vin }}</td>
                        <td>{{ car.company_name }}</td>
                        <td v-if="!isSold" class="text-end">
                            <MoneyAmount :value="car.total_amount" :currency="car.currency" show-zero />
                        </td>
                        <template v-else>
                            <td class="text-end">
                                <MoneyAmount :value="car.sale_price" :currency="car.currency" show-zero />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="car.paid_amount" :currency="car.currency" show-zero />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="car.remaining_amount" :currency="car.currency" show-zero />
                            </td>
                        </template>
                    </tr>
                    <tr class="erp-print-total-row">
                        <td :colspan="isSold ? 6 : 6">{{ group.label }}</td>
                        <td v-if="!isSold" class="text-end">
                            <MoneyAmount :value="group.list_amount" currency="USD" show-zero />
                        </td>
                        <template v-else>
                            <td class="text-end"><MoneyAmount :value="group.sale_amount" currency="USD" show-zero /></td>
                            <td class="text-end"><MoneyAmount :value="group.paid_amount" currency="USD" show-zero /></td>
                            <td class="text-end"><MoneyAmount :value="group.remaining_amount" currency="USD" show-zero /></td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>

        <footer class="erp-print-footer">{{ t('iran_cars.print_footer') }}</footer>
    </PrintLayout>
</template>
