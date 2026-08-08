<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CompanyDirectChargeModal from '@/Components/CompanyDirectChargeModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    ledger: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
    cars: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({ tab: 'statement', car_search: '' }) },
    creditAccounts: { type: Array, default: () => [] },
    defaultCreditAccountId: { type: Number, default: null },
    canManage: { type: Boolean, default: false },
    canCollect: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const showDirectCharge = ref(false);
const activeTab = ref(['statement', 'payments', 'cars'].includes(props.filters.tab) ? props.filters.tab : 'statement');

const carSearchForm = useForm({
    tab: 'cars',
    car_search: props.filters.car_search || '',
});

const setTab = (tab) => {
    activeTab.value = tab;
    router.get(route('companies.show', props.company.id), {
        tab,
        car_search: tab === 'cars' ? carSearchForm.car_search : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const searchCars = () => {
    activeTab.value = 'cars';
    carSearchForm.get(route('companies.show', props.company.id), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

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
                <button
                    v-if="canCollect"
                    type="button"
                    class="btn btn-erp-ghost"
                    @click="showDirectCharge = true"
                >
                    {{ t('companies.direct_charge') }}
                </button>
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

        <CompanyDirectChargeModal
            :show="showDirectCharge"
            :company-id="company.id"
            :currency="ledger.currency"
            :credit-accounts="creditAccounts"
            :default-credit-account-id="defaultCreditAccountId"
            @close="showDirectCharge = false"
        />

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

        <div class="erp-card overflow-hidden">
            <div class="p-3 border-bottom d-flex flex-wrap gap-2">
                <button type="button" class="btn" :class="activeTab === 'statement' ? 'btn-erp' : 'btn-erp-ghost'" @click="setTab('statement')">
                    {{ t('companies.statement') }}
                </button>
                <button type="button" class="btn" :class="activeTab === 'payments' ? 'btn-erp' : 'btn-erp-ghost'" @click="setTab('payments')">
                    {{ t('companies.payments_tab') }} ({{ payments.length }})
                </button>
                <button type="button" class="btn" :class="activeTab === 'cars' ? 'btn-erp' : 'btn-erp-ghost'" @click="setTab('cars')">
                    {{ t('companies.cars_tab') }} ({{ cars.total || cars.data?.length || 0 }})
                </button>
                <Link
                    v-if="company.ar_account"
                    :href="route('accounts.show', company.ar_account.id)"
                    class="small text-decoration-none fw-semibold ms-auto align-self-center"
                >
                    {{ t('companies.ar_account') }}: {{ company.ar_account.code }}
                </Link>
            </div>

            <div v-if="activeTab === 'statement'">
                <div class="px-3 pt-3">
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

            <div v-else-if="activeTab === 'payments'">
                <div class="px-3 pt-3">
                    <p class="small text-secondary mb-0">{{ t('companies.payments_help') }}</p>
                </div>
                <div class="table-responsive">
                    <table class="table erp-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">{{ t('money_vouchers.number') }}</th>
                                <th>{{ t('common.date') }}</th>
                                <th class="text-end">{{ t('common.amount') }}</th>
                                <th>{{ t('money_vouchers.payment_account') }}</th>
                                <th>{{ t('money_vouchers.voyage') }}</th>
                                <th>{{ t('common.status') }}</th>
                                <th class="pe-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="payments.length === 0">
                                <td colspan="7">
                                    <EmptyState icon="$">{{ t('companies.no_payments') }}</EmptyState>
                                </td>
                            </tr>
                            <tr
                                v-for="voucher in payments"
                                :key="voucher.id"
                                :class="voucher.type === 'receipt' ? 'is-receipt' : 'is-payment'"
                            >
                                <td class="ps-4">
                                    <Link :href="route('money-vouchers.show', voucher.id)" class="fw-semibold text-decoration-none">
                                        {{ voucher.voucher_number }}
                                    </Link>
                                    <div v-if="voucher.reference" class="small text-secondary">{{ voucher.reference }}</div>
                                </td>
                                <td>{{ voucher.voucher_date }}</td>
                                <td class="text-end">
                                    <MoneyAmount :value="voucher.amount" :currency="voucher.currency" />
                                </td>
                                <td class="small">{{ voucher.payment_account || '—' }}</td>
                                <td>
                                    <template v-if="voucher.allocations?.length">
                                        <div v-for="row in voucher.allocations" :key="row.voyage_id" class="small">
                                            <Link :href="route('voyages.show', row.voyage_id)" class="text-decoration-none">
                                                {{ row.voyage_number }}
                                            </Link>
                                            · <MoneyAmount :value="row.amount" />
                                        </div>
                                    </template>
                                    <span v-else class="text-secondary">{{ t('companies.unallocated') }}</span>
                                </td>
                                <td>
                                    <StatusBadge :tone="voucher.status_tone" :label="voucher.status_label" />
                                </td>
                                <td class="pe-4">
                                    <Link :href="route('money-vouchers.show', voucher.id)" class="btn btn-sm btn-erp-ghost">
                                        {{ t('common.open') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else>
                <form class="px-3 pt-3 pb-2" @submit.prevent="searchCars">
                    <p class="small text-secondary mb-2">{{ t('companies.cars_help') }}</p>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <input
                                v-model="carSearchForm.car_search"
                                type="search"
                                class="form-control form-erp-control"
                                :placeholder="t('companies.cars_search')"
                            />
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-erp" :disabled="carSearchForm.processing">
                                {{ t('common.search') }}
                            </button>
                            <button
                                v-if="filters.car_search"
                                type="button"
                                class="btn btn-erp-ghost"
                                @click="carSearchForm.car_search = ''; searchCars()"
                            >
                                {{ t('common.reset') }}
                            </button>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table erp-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">{{ t('voyage_cars.chassis') }}</th>
                                <th>{{ t('companies.car_name') }}</th>
                                <th>{{ t('voyage_cars.consignee') }}</th>
                                <th>{{ t('voyages.number') }}</th>
                                <th class="pe-4">{{ t('voyages.sailing') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!cars.data?.length">
                                <td colspan="5">
                                    <EmptyState icon="C">{{ t('companies.no_cars') }}</EmptyState>
                                </td>
                            </tr>
                            <tr v-for="car in cars.data" :key="car.id">
                                <td class="ps-4 font-monospace fw-semibold">{{ car.chassis_no || '—' }}</td>
                                <td>{{ car.name || '—' }}</td>
                                <td>{{ car.consignee_name || '—' }}</td>
                                <td>
                                    <Link
                                        v-if="car.voyage_id"
                                        :href="route('voyages.show', { voyage: car.voyage_id, tab: 'cars' })"
                                        class="text-decoration-none fw-semibold"
                                    >
                                        {{ car.voyage_number }}
                                    </Link>
                                    <span v-else>—</span>
                                </td>
                                <td class="pe-4">{{ car.sailing_date || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="cars.prev_page_url || cars.next_page_url"
                    class="d-flex justify-content-between align-items-center p-3 border-top"
                >
                    <Link
                        v-if="cars.prev_page_url"
                        :href="cars.prev_page_url"
                        class="btn btn-sm btn-erp-ghost"
                        preserve-scroll
                    >{{ t('common.prev') }}</Link>
                    <span v-else></span>
                    <span class="small text-secondary">{{ cars.from }}–{{ cars.to }} / {{ cars.total }}</span>
                    <Link
                        v-if="cars.next_page_url"
                        :href="cars.next_page_url"
                        class="btn btn-sm btn-erp-ghost"
                        preserve-scroll
                    >{{ t('common.next') }}</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
