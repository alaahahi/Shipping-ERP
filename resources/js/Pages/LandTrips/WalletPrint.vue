<script setup>
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { formatMoney } from '@/utils/formatMoney';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    entry: { type: Object, required: true },
    printedAt: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();
const officeName = computed(() => page.props.appSettings?.companyName || t('app.name'));
const isWithdraw = computed(() => props.entry.type === 'withdraw');
const titleAr = computed(() => (isWithdraw.value ? 'وصل صرف' : 'وصل قبض'));
const titleCkb = computed(() => (isWithdraw.value ? 'پسوڵەی خەرج' : 'پسوڵەی وەرگرتن'));
const amountText = computed(() => `${formatMoney(props.entry.amount)} ${props.entry.currency}`);
const voucherDate = computed(() => String(props.entry.created_at || '').slice(0, 10).replace(/-/g, '/'));

const printPage = () => window.print();
</script>

<template>
    <Head :title="`${titleAr} — ${entry.voucher_number}`" />

    <PrintLayout>
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

        <article class="wallet-voucher" dir="rtl">
            <header class="wallet-voucher-head">
                <p class="wallet-voucher-office">{{ officeName }}</p>
                <h1 class="wallet-voucher-title">{{ titleAr }}</h1>
                <p class="wallet-voucher-title-ckb">{{ titleCkb }}</p>
            </header>

            <div class="wallet-voucher-meta">
                <span>الرقم: <b>{{ entry.voucher_number }}</b></span>
                <span>ژمارە: <b>{{ entry.voucher_number }}</b></span>
                <span>التاريخ: <b>{{ voucherDate }}</b></span>
            </div>

            <dl class="wallet-voucher-fields">
                <div class="wallet-voucher-row">
                    <dt>الشركة / کۆمپانیا</dt>
                    <dd>{{ company.name }}</dd>
                </div>
                <div class="wallet-voucher-row">
                    <dt>المبلغ / بڕ</dt>
                    <dd class="wallet-voucher-amount">{{ amountText }}</dd>
                </div>
                <div class="wallet-voucher-row wallet-voucher-row-words">
                    <dt>المبلغ كتابةً</dt>
                    <dd>{{ entry.amount_words_ar }}</dd>
                </div>
                <div class="wallet-voucher-row wallet-voucher-row-words">
                    <dt>بڕەکە بە نووسین</dt>
                    <dd>{{ entry.amount_words_ckb }}</dd>
                </div>
                <div v-if="entry.notes" class="wallet-voucher-row">
                    <dt>البيان / تێبینی</dt>
                    <dd>{{ entry.notes }}</dd>
                </div>
                <div v-if="entry.created_by_name" class="wallet-voucher-row">
                    <dt>الموظف / کارمەند</dt>
                    <dd>{{ entry.created_by_name }}</dd>
                </div>
            </dl>

            <footer class="wallet-voucher-signs">
                <div>
                    <span>المستلم</span>
                    <em></em>
                </div>
                <div>
                    <span>التوقيع</span>
                    <em></em>
                </div>
                <div>
                    <span>الختم</span>
                    <em></em>
                </div>
            </footer>
        </article>
    </PrintLayout>
</template>
