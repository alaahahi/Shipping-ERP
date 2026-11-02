<script setup>
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    entries: { type: Array, default: () => [] },
    printedAt: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();
const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const currency = computed(() => props.summary.currency || 'USD');
const backHref = computed(() => route('land-trips.companies.show', props.company.id));
const printPage = () => window.print();

const typeLabel = (type) => (type === 'withdraw' ? t('land_trips.wallet_withdraw') : t('land_trips.wallet_deposit'));
</script>

<template>
    <Head :title="`${t('land_trips.wallet_print_all')} — ${company.name}`" />

    <PrintLayout>
        <template #toolbar-start>
            <a class="btn btn-erp-ghost btn-sm" :href="backHref">
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
            <h1 class="erp-print-title">{{ t('land_trips.wallet_print_all') }}</h1>
            <p class="erp-print-meta mb-0">
                <strong>{{ company.name }}</strong>
                <span> · {{ t('land_trips.printed_at') }}: {{ printedAt }}</span>
            </p>
        </header>

        <section class="erp-print-summary">
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('land_trips.cars_total') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="summary.cars_total" :currency="currency" show-zero />
                </div>
                <div class="erp-print-summary-hint">{{ summary.cars_count || 0 }} {{ t('land_trips.cars') }}</div>
            </div>
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('land_trips.wallet_payments') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="summary.paid" :currency="currency" show-zero />
                </div>
            </div>
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('land_trips.wallet_remaining') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="summary.remaining" :currency="currency" show-zero />
                </div>
            </div>
        </section>

        <h2 class="erp-print-section-title">{{ t('land_trips.wallet_payments') }}</h2>
        <table class="erp-print-table">
            <thead>
                <tr>
                    <th class="erp-print-col-serial">#</th>
                    <th>{{ t('land_trips.wallet_voucher') }}</th>
                    <th>{{ t('common.date') }}</th>
                    <th>{{ t('common.type') }}</th>
                    <th>{{ t('common.notes') }}</th>
                    <th class="text-end">{{ t('common.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!entries.length">
                    <td colspan="6">{{ t('land_trips.wallet_empty') }}</td>
                </tr>
                <tr v-for="(entry, index) in entries" :key="entry.id">
                    <td class="erp-print-col-serial">{{ index + 1 }}</td>
                    <td>{{ entry.voucher_number }}</td>
                    <td>{{ entry.entry_date || entry.created_at }}</td>
                    <td>{{ typeLabel(entry.type) }}</td>
                    <td>{{ entry.notes || '—' }}</td>
                    <td class="text-end">
                        <MoneyAmount
                            :value="entry.amount"
                            :currency="entry.currency"
                            :tone="entry.type === 'withdraw' ? 'debit' : 'credit'"
                            show-zero
                        />
                    </td>
                </tr>
                <tr class="erp-print-total-row">
                    <td colspan="5">{{ t('land_trips.wallet_payments') }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="summary.paid" :currency="currency" show-zero />
                    </td>
                </tr>
                <tr class="erp-print-total-row">
                    <td colspan="5">{{ t('land_trips.wallet_remaining') }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="summary.remaining" :currency="currency" show-zero />
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="erp-print-footer mb-0">{{ t('land_trips.wallet_print_footer') }}</p>
    </PrintLayout>
</template>
