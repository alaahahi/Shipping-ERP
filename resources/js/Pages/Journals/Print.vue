<script setup>
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    entry: { type: Object, required: true },
    voucher: { type: Object, required: true },
    printedAt: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();

const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const companyPhone = computed(() => page.props.appSettings?.companyPhone || '');
const companyAddress = computed(() => page.props.appSettings?.companyAddress || '');
const companyLogoUrl = computed(() => page.props.appSettings?.companyLogoUrl || null);

const isPayment = computed(() => props.voucher.type === 'payment');
const titleAr = computed(() => (isPayment.value ? t('journals.cash_payment_ar') : t('journals.cash_receipt_ar')));
const titleEn = computed(() => (isPayment.value ? t('journals.cash_payment_en') : t('journals.cash_receipt_en')));
const partyVerb = computed(() => (isPayment.value ? t('journals.paid_to') : t('journals.received_from')));
const signatureLabel = computed(() =>
    isPayment.value ? t('journals.payer_signature') : t('journals.receiver_signature')
);

const datetimeLabel = computed(() => props.printedAt || props.entry.entry_date || '');
const copies = [1, 2];

const printPage = () => window.print();
</script>

<template>
    <Head :title="`${titleAr} — ${entry.voucher_number}`" />

    <PrintLayout>
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
                        <div>{{ t('journals.voucher') }}: {{ entry.voucher_number }}</div>
                        <div>{{ t('common.date') }}: {{ datetimeLabel }}</div>
                    </div>

                    <section class="cash-voucher-body">
                        <p>
                            <span class="cash-voucher-label">{{ t('journals.company_party') }}:</span>
                            {{ voucher.party_name }}
                        </p>
                        <p>
                            <span class="cash-voucher-label">{{ partyVerb }}:</span>
                            {{ voucher.party_name }}
                        </p>
                        <p>
                            <span class="cash-voucher-label">{{ t('journals.amount_in_words') }}:</span>
                            {{ voucher.amount_in_words }}
                        </p>
                        <p>
                            <span class="cash-voucher-label">{{ t('common.notes') }}:</span>
                            {{ voucher.notes || '—' }}
                        </p>

                        <div class="cash-voucher-bottom">
                            <div class="cash-voucher-sign">{{ signatureLabel }}</div>
                            <div class="cash-voucher-amount">
                                <span class="cash-voucher-amount-label">{{ t('common.amount') }}:</span>
                                <span class="cash-voucher-amount-value">
                                    {{ voucher.amount_display }}
                                    <span class="cash-voucher-currency">{{ voucher.currency_symbol }}</span>
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
    </PrintLayout>
</template>
