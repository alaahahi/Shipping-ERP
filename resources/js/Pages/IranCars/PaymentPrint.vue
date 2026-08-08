<script setup>
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    car: { type: Object, required: true },
    printedAt: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();
const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const printPage = () => window.print();
</script>

<template>
    <Head :title="`${t('iran_cars.print_payments')} — ${car.vin}`" />

    <PrintLayout>
        <template #toolbar-start>
            <a class="btn btn-erp-ghost btn-sm" :href="route('iran-cars.show', car.id)">
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
            <h1 class="erp-print-title">{{ t('iran_cars.print_payments') }}</h1>
            <p class="erp-print-meta mb-0">
                <strong>{{ car.model_name }}</strong>
                <span> · {{ car.vin }}</span>
                <span> · {{ car.company_name }}</span>
                <span> · {{ car.border_label }}</span>
                <span> · {{ t('iran_cars.printed_at') }}: {{ printedAt }}</span>
            </p>
        </header>

        <section class="erp-print-summary">
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('iran_cars.sale_price') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="car.sale_price || car.total_amount" :currency="car.currency" show-zero />
                </div>
            </div>
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('iran_cars.paid') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="car.paid_amount" :currency="car.currency" show-zero />
                </div>
            </div>
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('iran_cars.remaining') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="car.remaining_amount" :currency="car.currency" show-zero />
                </div>
            </div>
        </section>

        <h2 class="erp-print-section-title">{{ t('iran_cars.payments') }}</h2>
        <table class="erp-print-table">
            <thead>
                <tr>
                    <th class="erp-print-col-serial">#</th>
                    <th>{{ t('iran_cars.voucher') }}</th>
                    <th>{{ t('common.date') }}</th>
                    <th>{{ t('iran_cars.cash_bank') }}</th>
                    <th>{{ t('common.reference') }}</th>
                    <th class="text-end">{{ t('common.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!car.payments?.length">
                    <td colspan="6">{{ t('iran_cars.no_payments') }}</td>
                </tr>
                <tr v-for="(payment, index) in car.payments" :key="payment.id">
                    <td class="erp-print-col-serial">{{ index + 1 }}</td>
                    <td>{{ payment.voucher_number }}</td>
                    <td>{{ payment.payment_date }}</td>
                    <td>{{ payment.debit_account || '—' }}</td>
                    <td>{{ payment.reference || '—' }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="payment.amount" :currency="payment.currency" show-zero />
                    </td>
                </tr>
                <tr class="erp-print-total-row">
                    <td colspan="5">{{ t('iran_cars.paid') }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="car.paid_amount" :currency="car.currency" show-zero />
                    </td>
                </tr>
                <tr class="erp-print-total-row">
                    <td colspan="5">{{ t('iran_cars.remaining') }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="car.remaining_amount" :currency="car.currency" show-zero />
                    </td>
                </tr>
            </tbody>
        </table>

        <footer class="erp-print-footer">{{ t('iran_cars.print_footer') }}</footer>
    </PrintLayout>
</template>
