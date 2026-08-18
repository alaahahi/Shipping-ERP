<script setup>
import CashVoucherSheet from '@/Components/CashVoucherSheet.vue';
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    ship: { type: Object, required: true },
    expense: { type: Object, required: true },
    printedAt: { type: String, default: '' },
});

const { t } = useI18n();
const titleAr = computed(() => t('journals.cash_payment_ar'));
const voucherDate = computed(() => props.printedAt || props.expense.expense_date || '');

const printPage = () => window.print();
</script>

<template>
    <Head :title="`${titleAr} — ${expense.voucher_number}`" />

    <PrintLayout class="cash-voucher-print">
        <template #toolbar-start>
            <a class="btn btn-erp-ghost btn-sm" :href="route('ships.show', { ship: ship.id, tab: 'expenses' })">
                {{ t('common.back') }}
            </a>
        </template>
        <template #toolbar-actions>
            <button type="button" class="btn btn-erp btn-sm" @click="printPage">
                {{ t('common.print') }}
            </button>
        </template>

        <CashVoucherSheet
            type="payment"
            :voucher-number="expense.voucher_number"
            :date="voucherDate"
            :party-name="expense.party_name"
            :amount="expense.amount"
            :amount-display="expense.amount_display"
            :currency="expense.currency"
            :currency-symbol="expense.currency_symbol"
            :amount-in-words="expense.amount_in_words"
            :notes="expense.notes"
        />
    </PrintLayout>
</template>
