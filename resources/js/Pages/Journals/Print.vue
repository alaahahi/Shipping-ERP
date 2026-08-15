<script setup>
import PrintLayout from '@/Layouts/PrintLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    entry: { type: Object, required: true },
    printedAt: { type: String, default: '' },
});

const { t } = useI18n();
const printPage = () => window.print();
</script>

<template>
    <Head :title="`${t('journals.print_voucher')} — ${entry.voucher_number}`" />

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

        <header class="erp-print-header">
            <div class="erp-print-kicker">{{ t('journals.print_voucher') }}</div>
            <h1 class="erp-print-title">{{ entry.voucher_number }}</h1>
            <p class="erp-print-meta mb-0">
                {{ t('common.date') }}: {{ entry.entry_date }}
                <span v-if="printedAt"> · {{ t('journals.printed_at') }}: {{ printedAt }}</span>
            </p>
        </header>

        <section class="erp-print-summary">
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('common.description') }}</div>
                <div class="erp-print-summary-value">{{ entry.description || '—' }}</div>
            </div>
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('common.currency') }}</div>
                <div class="erp-print-summary-value">{{ entry.currency }}</div>
            </div>
            <div v-if="entry.reference" class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('common.reference') }}</div>
                <div class="erp-print-summary-value">{{ entry.reference }}</div>
            </div>
        </section>

        <table class="erp-print-table">
            <thead>
                <tr>
                    <th>{{ t('journals.account') }}</th>
                    <th>{{ t('journals.memo') }}</th>
                    <th class="text-end">{{ t('journals.debit') }}</th>
                    <th class="text-end">{{ t('journals.credit') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in entry.lines" :key="line.id">
                    <td>{{ line.account ? `${line.account.code} — ${line.account.name}` : '—' }}</td>
                    <td>{{ line.memo || '—' }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="line.debit" tone="plain" />
                    </td>
                    <td class="text-end">
                        <MoneyAmount :value="line.credit" tone="plain" />
                    </td>
                </tr>
                <tr class="erp-print-total-row">
                    <td colspan="2">{{ t('journals.totals') }}</td>
                    <td class="text-end">
                        <MoneyAmount :value="entry.total_debit" tone="plain" />
                    </td>
                    <td class="text-end">
                        <MoneyAmount :value="entry.total_credit" tone="plain" />
                    </td>
                </tr>
            </tbody>
        </table>

        <img
            v-if="entry.attachment_url"
            :src="entry.attachment_url"
            :alt="t('accounts.view_image')"
            class="mt-4 mx-auto max-h-80 w-auto max-w-full"
        />
    </PrintLayout>
</template>
