<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    account: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    opening_balance: { type: String, required: true },
    closing_balance: { type: String, required: true },
    period_debit: { type: String, required: true },
    period_credit: { type: String, required: true },
    lines: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();

const filterForm = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const applyFilters = () => {
    filterForm.get(route('accounts.show', props.account.id), {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head :title="`${t('accounts.ledger')} · ${account.code}`" />
    <AppLayout>
        <template #header>{{ t('accounts.ledger') }} · {{ account.code }}</template>

        <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <Link :href="route('accounts.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('accounts.back') }}
            </Link>
            <Link
                v-if="canManage"
                :href="route('accounts.edit', account.id)"
                class="btn btn-sm btn-erp-ghost"
            >
                {{ t('common.edit') }}
            </Link>
        </div>

        <div class="erp-hero mb-3">
            <div class="erp-hero-kicker">{{ t('accounts.ledger') }}</div>
            <h2 class="erp-hero-title">{{ account.code }} — {{ account.name }}</h2>
            <p class="erp-hero-subtitle">
                {{ account.type_label }} · {{ account.currency }} · {{ t('accounts.balance') }}:
                <MoneyAmount :value="account.balance" tone="balance" />
            </p>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('accounts.opening') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.2rem">
                        <MoneyAmount :value="opening_balance" tone="balance" />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('accounts.closing') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.2rem">
                        <MoneyAmount :value="closing_balance" tone="balance" />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('journals.debit') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.2rem">
                        <MoneyAmount :value="period_debit" tone="debit" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('journals.credit') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.2rem">
                        <MoneyAmount :value="period_credit" tone="credit" show-zero />
                    </p>
                </div>
            </div>
        </div>

        <div class="erp-card p-0 overflow-hidden mb-3">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('accounts.date_from') }}</label>
                    <input v-model="filterForm.date_from" type="date" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('accounts.date_to') }}</label>
                    <input v-model="filterForm.date_to" type="date" class="form-control form-erp-control" />
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-erp-ghost">{{ t('common.filter') }}</button>
                    <Link :href="route('accounts.show', account.id)" class="btn btn-sm btn-erp-ghost">
                        {{ t('common.reset') }}
                    </Link>
                </div>
            </form>
            <div class="px-4 pb-3">
                <p class="small text-secondary mb-0">{{ t('accounts.ledger_help') }}</p>
            </div>
        </div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('common.date') }}</th>
                            <th>{{ t('journals.voucher') }}</th>
                            <th>{{ t('common.description') }}</th>
                            <th class="text-end">{{ t('journals.debit') }}</th>
                            <th class="text-end">{{ t('journals.credit') }}</th>
                            <th class="text-end pe-4">{{ t('accounts.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-light">
                            <td class="ps-4" colspan="5">{{ t('accounts.opening') }}</td>
                            <td class="text-end pe-4">
                                <MoneyAmount :value="opening_balance" tone="balance" />
                            </td>
                        </tr>
                        <tr v-if="lines.data.length === 0">
                            <td colspan="6" class="text-center text-secondary py-4">{{ t('accounts.ledger_none') }}</td>
                        </tr>
                        <tr v-for="line in lines.data" :key="line.id">
                            <td class="ps-4">{{ line.entry_date }}</td>
                            <td>
                                <Link
                                    :href="route('journals.show', line.journal_entry_id)"
                                    class="fw-semibold text-decoration-none"
                                >
                                    {{ line.voucher_number }}
                                </Link>
                            </td>
                            <td>
                                <div>{{ line.description }}</div>
                                <div v-if="line.memo" class="small text-secondary">{{ line.memo }}</div>
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="line.debit" tone="debit" />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="line.credit" tone="credit" />
                            </td>
                            <td class="text-end pe-4">
                                <MoneyAmount :value="line.balance" tone="balance" />
                            </td>
                        </tr>
                        <tr class="table-light fw-semibold">
                            <td class="ps-4" colspan="5">{{ t('accounts.closing') }}</td>
                            <td class="text-end pe-4">
                                <MoneyAmount :value="closing_balance" tone="balance" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="lines.prev_page_url || lines.next_page_url"
                class="d-flex justify-content-between align-items-center p-3 border-top"
            >
                <Link
                    v-if="lines.prev_page_url"
                    :href="lines.prev_page_url"
                    class="btn btn-sm btn-erp-ghost"
                    preserve-scroll
                >
                    {{ t('common.prev') }}
                </Link>
                <span v-else></span>
                <span class="small text-secondary">
                    {{ lines.from }}–{{ lines.to }} / {{ lines.total }}
                </span>
                <Link
                    v-if="lines.next_page_url"
                    :href="lines.next_page_url"
                    class="btn btn-sm btn-erp-ghost"
                    preserve-scroll
                >
                    {{ t('common.next') }}
                </Link>
                <span v-else></span>
            </div>
        </div>
    </AppLayout>
</template>
