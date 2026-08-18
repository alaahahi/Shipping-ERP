<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    type: { type: String, default: 'receipt' },
    voucherNumber: { type: String, required: true },
    date: { type: String, default: '' },
    partyName: { type: String, default: '—' },
    amountDisplay: { type: String, required: true },
    currencySymbol: { type: String, default: '' },
    amountInWords: { type: String, default: '—' },
    notes: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();

const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const companyPhone = computed(() => page.props.appSettings?.companyPhone || '');
const companyAddress = computed(() => page.props.appSettings?.companyAddress || '');
const companyLogoUrl = computed(() => page.props.appSettings?.companyLogoUrl || null);

const isPayment = computed(() => props.type === 'payment');
const titleAr = computed(() => (isPayment.value ? t('journals.cash_payment_ar') : t('journals.cash_receipt_ar')));
const titleEn = computed(() => (isPayment.value ? t('journals.cash_payment_en') : t('journals.cash_receipt_en')));
const partyVerb = computed(() => (isPayment.value ? t('journals.paid_to') : t('journals.received_from')));
const signatureLabel = computed(() =>
    isPayment.value ? t('journals.payer_signature') : t('journals.receiver_signature')
);

const copies = [1, 2];
const printPageClass = 'cash-voucher-print';

onMounted(() => {
    document.documentElement.classList.add(printPageClass);

    if (!document.querySelector('style[data-cash-voucher-page]')) {
        const style = document.createElement('style');
        style.setAttribute('data-cash-voucher-page', '');
        style.textContent = '@page { size: A4 portrait; margin: 8mm; }';
        document.head.appendChild(style);
    }
});

onBeforeUnmount(() => {
    document.documentElement.classList.remove(printPageClass);
    document.querySelector('style[data-cash-voucher-page]')?.remove();
});
</script>

<template>
    <div class="cash-voucher-sheet" dir="rtl">
        <template v-for="(copy, index) in copies" :key="copy">
            <article class="cash-voucher">
                <header class="cash-voucher-header">
                    <div class="cash-voucher-brand">
                        <div class="cash-voucher-company">{{ companyName }}</div>
                    </div>
                    <div class="cash-voucher-titles">
                        <div class="cash-voucher-title-ar">{{ titleAr }}</div>
                        <div class="cash-voucher-title-en">{{ titleEn }}</div>
                    </div>
                    <div class="cash-voucher-logo">
                        <img
                            v-if="companyLogoUrl"
                            :src="companyLogoUrl"
                            :alt="companyName"
                            class="cash-voucher-logo-img"
                        />
                        <div v-else class="cash-voucher-logo-placeholder">{{ companyName }}</div>
                    </div>
                </header>

                <div class="cash-voucher-meta">
                    <div>{{ t('journals.voucher') }}: {{ voucherNumber }}</div>
                    <div>{{ t('common.date') }}: {{ date }}</div>
                </div>

                <section class="cash-voucher-body">
                    <p>
                        <span class="cash-voucher-label">{{ t('journals.company_party') }}:</span>
                        {{ partyName }}
                    </p>
                    <p>
                        <span class="cash-voucher-label">{{ partyVerb }}:</span>
                        {{ partyName }}
                    </p>
                    <p>
                        <span class="cash-voucher-label">{{ t('journals.amount_in_words') }}:</span>
                        {{ amountInWords }}
                    </p>
                    <p>
                        <span class="cash-voucher-label">{{ t('common.notes') }}:</span>
                        {{ notes || '—' }}
                    </p>

                    <div class="cash-voucher-bottom">
                        <div class="cash-voucher-sign">{{ signatureLabel }}</div>
                        <div class="cash-voucher-amount">
                            <span class="cash-voucher-amount-label">{{ t('common.amount') }}:</span>
                            <span class="cash-voucher-amount-value">
                                {{ amountDisplay }}
                                <span class="cash-voucher-currency">{{ currencySymbol }}</span>
                            </span>
                        </div>
                    </div>
                </section>

                <footer class="cash-voucher-footer">
                    <div v-if="companyAddress">{{ t('settings.address') }}: {{ companyAddress }}</div>
                    <div v-else></div>
                    <div v-if="companyPhone">Mobile: {{ companyPhone }}</div>
                </footer>
            </article>

            <div v-if="index === 0" class="cash-voucher-cut" aria-hidden="true">
                <span class="cash-voucher-cut-label">✂ قص · Cut</span>
            </div>
        </template>
    </div>
</template>
