<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    type: { type: String, default: 'receipt' },
    voucherNumber: { type: String, required: true },
    date: { type: String, default: '' },
    partyName: { type: String, default: '—' },
    amount: { type: [Number, String], default: null },
    amountDisplay: { type: String, required: true },
    currency: { type: String, default: '' },
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
const titleAr = computed(() => (isPayment.value ? 'سند صرف' : 'سند قبض'));
const titleEn = computed(() => (isPayment.value ? 'PAYMENT VOUCHER' : 'RECEIPT VOUCHER'));
const partyEn = computed(() => (isPayment.value ? 'Paid To Mrs :' : 'Received From Mrs :'));
const partyAr = computed(() =>
    isPayment.value ? 'صرفنا إلى السيد / السادة' : 'استلمنا من السيد / السادة'
);

const currencyCode = computed(() =>
    String(props.currency || '')
        .trim()
        .toUpperCase()
        .replace(/[^A-Z]/g, '')
        .slice(0, 3)
);

const currencyMeta = computed(() => {
    const map = {
        USD: { en: 'USD', ar: 'دولار' },
        AED: { en: 'AED', ar: 'درهم' },
        IQD: { en: 'IQD', ar: 'دينار' },
        EUR: { en: 'EUR', ar: 'يورو' },
    };

    if (map[currencyCode.value]) {
        return map[currencyCode.value];
    }

    const code = currencyCode.value;

    return {
        en: /^[A-Z]{3}$/.test(code) ? code : '',
        ar: '',
    };
});

const numericAmount = computed(() => {
    const raw = String(props.amount ?? props.amountDisplay ?? '')
        .replace(/[^\d.,\-]/g, '')
        .replace(/,/g, '');
    const parsed = Number(raw);

    return Number.isFinite(parsed) ? parsed : 0;
});

const formattedAmount = computed(() => {
    const whole = Math.trunc(Math.abs(numericAmount.value));

    return whole.toLocaleString('en-US', {
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
        useGrouping: true,
    });
});

const parsedDate = computed(() => {
    const value = String(props.date || '').trim();
    if (!value) {
        return null;
    }

    const timestamp = Date.parse(value);
    if (!Number.isNaN(timestamp)) {
        return new Date(timestamp);
    }

    const match = value.match(/(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})/);
    if (!match) {
        return null;
    }

    return new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
});

const pad = (value) => String(value ?? '').padStart(2, '0');

const gregorianParts = computed(() => {
    const date = parsedDate.value;
    if (!date) {
        return { day: '——', month: '——', year: '————' };
    }

    return {
        day: pad(date.getDate()),
        month: pad(date.getMonth() + 1),
        year: String(date.getFullYear()),
    };
});

const hijriParts = computed(() => {
    const date = parsedDate.value;
    if (!date) {
        return { day: '——', month: '——', year: '————' };
    }

    try {
        const parts = new Intl.DateTimeFormat('en-u-ca-islamic', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).formatToParts(date);
        const pick = (type) => parts.find((part) => part.type === type)?.value || '——';

        return {
            day: pad(pick('day')),
            month: pad(pick('month')),
            year: String(pick('year')).replace(/\D/g, '') || '————',
        };
    } catch {
        return { day: '——', month: '——', year: '————' };
    }
});

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
    <div class="cash-voucher-sheet">
        <template v-for="(copy, index) in copies" :key="copy">
            <article
                class="cash-voucher"
                :class="isPayment ? 'is-payment' : 'is-receipt'"
                dir="ltr"
            >
                <div class="cv-accent-top" aria-hidden="true">
                    <span />
                    <span />
                </div>

                <header class="cv-header">
                    <div class="cv-brand">
                        <div class="cv-logo-box">
                            <img
                                v-if="companyLogoUrl"
                                :src="companyLogoUrl"
                                :alt="companyName"
                                class="cv-logo"
                            />
                            <div v-else class="cv-logo-fallback">{{ companyName }}</div>
                        </div>
                        <div class="cv-company">{{ companyName }}</div>
                        <div v-if="companyAddress" class="cv-company-meta">{{ companyAddress }}</div>
                        <div v-if="companyPhone" class="cv-company-meta">Mobile: {{ companyPhone }}</div>
                    </div>

                    <div class="cv-titles">
                        <div class="cv-title-ar">{{ titleAr }}</div>
                        <div class="cv-title-en">{{ titleEn }}</div>
                        <div class="cv-serial">{{ voucherNumber }}</div>
                    </div>

                    <div class="cv-amount-box" dir="ltr">
                        <div class="cv-amount-cell cv-amount-major">
                            <span class="cv-amount-num">{{ formattedAmount }}</span>
                        </div>
                        <div
                            v-if="currencyMeta.en || currencyMeta.ar"
                            class="cv-amount-cell cv-amount-currency"
                        >
                            <span v-if="currencyMeta.en" class="cv-amount-cur-en">{{ currencyMeta.en }}</span>
                            <span v-if="currencyMeta.ar" class="cv-amount-cur-ar">{{ currencyMeta.ar }}</span>
                        </div>
                    </div>
                </header>

                <div class="cv-dates" dir="ltr">
                    <div class="cv-date cv-date-en">
                        الموافق
                        <span>{{ gregorianParts.day }}</span>
                        /
                        <span>{{ gregorianParts.month }}</span>
                        /
                        <span>{{ gregorianParts.year }}</span>
                        م
                    </div>
                    <div class="cv-date cv-date-ar">
                        التاريخ
                        <span>{{ hijriParts.day }}</span>
                        /
                        <span>{{ hijriParts.month }}</span>
                        /
                        <span>{{ hijriParts.year }}</span>
                        هـ
                    </div>
                </div>

                <section class="cv-body">
                    <div class="cv-line" dir="ltr">
                        <span class="cv-en">{{ partyEn }}</span>
                        <span class="cv-value">{{ partyName }}</span>
                        <span class="cv-ar">: <bdi>{{ partyAr }}</bdi></span>
                    </div>
                    <div class="cv-line" dir="ltr">
                        <span class="cv-en">Amount :</span>
                        <span class="cv-value">{{ amountInWords }}</span>
                        <span class="cv-ar">: <bdi>مبلغ وقدره</bdi></span>
                    </div>
                    <div class="cv-line cv-line-method" dir="ltr">
                        <div class="cv-method cv-method-en">
                            <span>Cash / Cheque No.</span>
                            <strong>Cash</strong>
                            <span>Bank</span>
                            <span class="cv-dots"></span>
                        </div>
                        <div class="cv-method cv-method-ar" dir="rtl">
                            <span>نقداً / شيك رقم</span>
                            <strong>نقداً</strong>
                            <span>على بنك</span>
                            <span class="cv-dots"></span>
                        </div>
                    </div>
                    <div class="cv-line" dir="ltr">
                        <span class="cv-en">Being :</span>
                        <span class="cv-value">{{ notes || '' }}</span>
                        <span class="cv-ar">: <bdi>وذلك مقابل</bdi></span>
                    </div>
                </section>

                <footer class="cv-signs" dir="ltr">
                    <div class="cv-sign">
                        <div class="cv-sign-line"></div>
                        <div class="cv-sign-labels" dir="ltr">
                            <span>Receiver</span>
                            <span>المستلم</span>
                        </div>
                    </div>
                    <div class="cv-sign">
                        <div class="cv-sign-line"></div>
                        <div class="cv-sign-labels" dir="ltr">
                            <span>Accountant</span>
                            <span>المحاسب</span>
                        </div>
                    </div>
                </footer>

                <div class="cv-accent-bottom" aria-hidden="true"></div>
            </article>

            <div v-if="index === 0" class="cash-voucher-cut" aria-hidden="true">
                <span class="cash-voucher-cut-label">✂ قص · Cut</span>
            </div>
        </template>
    </div>
</template>
