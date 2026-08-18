<script setup>
import CashVoucherSheet from '@/Components/CashVoucherSheet.vue';
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    entry: { type: Object, required: true },
    voucher: { type: Object, required: true },
    printedAt: { type: String, default: '' },
});

const { t } = useI18n();
const isPayment = computed(() => props.voucher.type === 'payment');
const titleAr = computed(() => (isPayment.value ? t('journals.cash_payment_ar') : t('journals.cash_receipt_ar')));
const datetimeLabel = computed(() => props.printedAt || props.entry.entry_date || '');

const printPage = () => window.print();
</script>

<template>
    <Head :title="`${titleAr} — ${entry.voucher_number}`" />

    <PrintLayout class="cash-voucher-print">
        <template #toolbar-start>
            <Link :href="route('journals.show', entry.id)" class="btn btn-erp-ghost btn-sm">
                {{ t('journals.back_voucher') }}
            </Link>
        </template>
        <template #toolbar-actions>
            <button type="button" class="btn btn-erp btn-sm" @click="printPage">
                {{ t('common.print') }}
            </button>
        </template>

        <CashVoucherSheet
            :type="voucher.type"
            :voucher-number="entry.voucher_number"
            :date="datetimeLabel"
            :party-name="voucher.party_name"
            :amount-display="voucher.amount_display"
            :currency-symbol="voucher.currency_symbol"
            :amount-in-words="voucher.amount_in_words"
            :notes="voucher.notes"
        />
    </PrintLayout>
</template>
