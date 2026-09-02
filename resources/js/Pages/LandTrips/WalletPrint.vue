<script setup>
import CashVoucherSheet from '@/Components/CashVoucherSheet.vue';
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { formatMoney } from '@/utils/formatMoney';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    entry: { type: Object, required: true },
    printedAt: { type: String, default: '' },
});

const { t } = useI18n();
const isWithdraw = computed(() => props.entry.type === 'withdraw');
const voucherType = computed(() => (isWithdraw.value ? 'payment' : 'receipt'));
const titleAr = computed(() => (isWithdraw.value ? t('journals.cash_payment_ar') : t('journals.cash_receipt_ar')));
const currencySymbols = { USD: '$', AED: 'د.إ', IQD: 'د.ع', EUR: '€' };
const currencySymbol = computed(() => currencySymbols[props.entry.currency] || props.entry.currency);
const amountDisplay = computed(() => formatMoney(props.entry.amount));
const notes = computed(() => {
    const parts = [props.entry.notes, props.entry.created_by_name].filter(Boolean);

    return parts.join(' · ');
});
const voucherDate = computed(() => props.entry.entry_date || props.printedAt || String(props.entry.created_at || ''));

const printPage = () => window.print();
</script>

<template>
    <Head :title="`${titleAr} — ${entry.voucher_number}`" />

    <PrintLayout class="cash-voucher-print">
        <template #toolbar-start>
            <a class="btn btn-erp-ghost btn-sm" :href="route('land-trips.companies.show', company.id)">
                {{ t('common.back') }}
            </a>
        </template>
        <template #toolbar-actions>
            <button type="button" class="btn btn-erp btn-sm" @click="printPage">
                {{ t('common.print') }}
            </button>
        </template>

        <CashVoucherSheet
            :type="voucherType"
            :voucher-number="entry.voucher_number"
            :date="voucherDate"
            :party-name="company.name"
            :amount="entry.amount"
            :amount-display="amountDisplay"
            :currency="entry.currency"
            :currency-symbol="currencySymbol"
            :amount-in-words="entry.amount_words_ar"
            :notes="notes"
            :chassis-nos="entry.chassis || []"
        />
    </PrintLayout>
</template>
