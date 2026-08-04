<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    ledger: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    canCollect: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const openBalance = computed(() => Number(props.ledger.open_balance) || 0);

const balanceToneClass = computed(() => {
    if (openBalance.value > 0) return 'is-owing';
    if (openBalance.value < 0) return 'is-owed';
    return 'is-settled';
});

const balanceHint = computed(() => {
    if (openBalance.value > 0) return t('companies.balance_they_owe');
    if (openBalance.value < 0) return t('companies.balance_we_owe');
    return t('companies.balance_settled');
});
</script>

<template>
    <Head :title="company.name" />
    <AppLayout>
        <template #header>{{ company.name }}</template>
        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link :href="route('companies.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('companies.back') }}
            </Link>
        </div>

        <PageHeader :kicker="t('companies.title')" :title="company.name" :subtitle="t('companies.ledger_help')">
            <template #actions>
                <StatusBadge
                    :tone="company.is_active ? 'success' : 'neutral'"
                    :label="company.is_active ? t('common.active') : t('common.inactive')"
                />
                <Link v-if="canManage" :href="route('companies.edit', company.id)" class="btn btn-erp-ghost">
                    {{ t('common.edit') }}
                </Link>
                <Link
                    v-if="canCollect"
                    :href="route('money-vouchers.create', {
                        type: 'receipt',
                        company_id: company.id,
                        currency: 'USD',
                        amount: openBalance > 0 ? ledger.open_balance : undefined,
                    })"
                    class="btn btn-erp"
                >
                    {{ t('companies.collect') }}
                </Link>
            </template>
        </PageHeader>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="erp-stat" :class="balanceToneClass">
                    <div class="erp-stat-label">{{ t('companies.open_balance') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.2rem">
                        <MoneyAmount :value="ledger.open_balance" tone="balance" :currency="ledger.currency" />
                    </p>
                    <div class="erp-balance-hint" :class="balanceToneClass">{{ balanceHint }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('companies.total_charges') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">
                        <MoneyAmount :value="ledger.total_debit" tone="debit" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('companies.total_receipts') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">
                        <MoneyAmount :value="ledger.total_credit" tone="credit" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('companies.contact') }}</div>
                    <p class="erp-stat-value" style="font-size: 1rem">
                        {{ company.contact_name || '—' }}
                        <span v-if="company.contact_phone" class="d-block small text-secondary fw-normal">
                            {{ company.contact_phone }}
                        </span>
                        <span v-if="company.whatsapp_phone" class="d-block small text-success fw-normal">
                            WhatsApp: {{ company.whatsapp_phone }}
                        </span>
                        <span v-else-if="company.contact_phone" class="d-block small text-success fw-normal">
                            WhatsApp: {{ company.contact_phone }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="p-3 border-bottom">
                <h3 class="erp-panel-title mb-0">{{ t('companies.statement') }}</h3>
                <p class="small text-secondary mb-0">{{ t('companies.statement_help') }}</p>
            </div>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('common.date') }}</th>
                            <th>{{ t('journals.voucher') }}</th>
                            <th>{{ t('money_vouchers.voyage') }}</th>
                            <th>{{ t('common.notes') }}</th>
                            <th class="text-end">{{ t('journals.debit') }}</th>
                            <th class="text-end">{{ t('journals.credit') }}</th>
                            <th class="text-end pe-4">{{ t('companies.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="ledger.movements.length === 0">
                            <td colspan="7">
                                <EmptyState icon="$">{{ t('companies.no_movements') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="row in ledger.movements" :key="row.id">
                            <td class="ps-4">{{ row.date }}</td>
                            <td>
                                <Link
                                    v-if="row.journal_entry_id"
                                    :href="route('journals.show', row.journal_entry_id)"
                                    class="fw-semibold text-decoration-none"
                                >
                                    {{ row.voucher }}
                                </Link>
                                <span v-else>{{ row.voucher || '—' }}</span>
                            </td>
                            <td>
                                <Link
                                    v-if="row.voyage_id"
                                    :href="route('voyages.show', { voyage: row.voyage_id, tab: 'settlements' })"
                                    class="text-decoration-none"
                                >
                                    {{ row.voyage_number }}
                                </Link>
                                <span v-else class="text-secondary">{{ t('companies.unallocated') }}</span>
                            </td>
                            <td class="small">{{ row.memo || '—' }}</td>
                            <td class="text-end">
                                <MoneyAmount :value="row.debit" tone="debit" />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="row.credit" tone="credit" />
                            </td>
                            <td class="text-end pe-4">
                                <MoneyAmount :value="row.balance" tone="balance" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
