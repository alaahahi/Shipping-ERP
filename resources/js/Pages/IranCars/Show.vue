<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    car: { type: Object, required: true },
    cashAccounts: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const remaining = computed(() => Number(props.car.remaining_amount) || 0);
const canPay = computed(() => props.canManage && props.car.status !== 'cancelled' && remaining.value > 0.009);

const paymentForm = useForm({
    payment_date: new Date().toISOString().slice(0, 10),
    amount: remaining.value > 0 ? remaining.value : '',
    debit_account_id: props.cashAccounts[0]?.id ?? '',
    reference: '',
    notes: '',
});

const submitPayment = () => {
    paymentForm.post(route('iran-cars.payments.store', props.car.id), { preserveScroll: true });
};

const destroyPayment = (payment) => {
    if (!window.confirm(t('iran_cars.reverse_payment_confirm', { voucher: payment.voucher_number }))) return;
    router.delete(route('iran-cars.payments.destroy', {
        iran_car: props.car.id,
        iran_car_payment: payment.id,
    }), { preserveScroll: true });
};
</script>

<template>
    <Head :title="car.vin" />
    <AppLayout>
        <template #header>{{ car.vin }}</template>
        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link :href="route('iran-cars.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('iran_cars.back') }}
            </Link>
        </div>

        <PageHeader :kicker="car.border_label" :title="car.model_name" :subtitle="car.vin">
            <template #actions>
                <StatusBadge :tone="car.status_tone" :label="car.status_label" />
                <Link v-if="canManage" :href="route('iran-cars.edit', car.id)" class="btn btn-erp-ghost">
                    {{ t('common.edit') }}
                </Link>
            </template>
        </PageHeader>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('iran_cars.remaining') }}</div>
                    <p class="erp-stat-value">
                        <MoneyAmount :value="car.remaining_amount" :currency="car.currency" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('iran_cars.total') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">
                        <MoneyAmount :value="car.total_amount" :currency="car.currency" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('iran_cars.paid') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">
                        <MoneyAmount :value="car.paid_amount" :currency="car.currency" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('iran_cars.company') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.05rem">{{ car.company_name }}</p>
                    <div class="erp-stat-hint">{{ [car.year, car.color].filter(Boolean).join(' · ') || '—' }}</div>
                </div>
            </div>
        </div>

        <div v-if="car.invoice_journal_id" class="small mb-3">
            {{ t('iran_cars.invoice_journal') }}:
            <Link :href="route('journals.show', car.invoice_journal_id)">{{ car.invoice_voucher }}</Link>
        </div>

        <div v-if="canPay" class="erp-form-panel mb-3">
            <h3 class="erp-panel-title">{{ t('iran_cars.add_payment') }}</h3>
            <form class="row g-3" @submit.prevent="submitPayment">
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.date') }}</label>
                    <input v-model="paymentForm.payment_date" type="date" class="form-control form-erp-control" required />
                    <InputError :message="paymentForm.errors.payment_date" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.amount') }}</label>
                    <input v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="form-control form-erp-control" required />
                    <InputError :message="paymentForm.errors.amount" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('iran_cars.cash_bank') }}</label>
                    <select v-model="paymentForm.debit_account_id" class="form-select form-erp-control" required>
                        <option v-for="account in cashAccounts" :key="account.id" :value="account.id">{{ account.label }}</option>
                    </select>
                    <InputError :message="paymentForm.errors.debit_account_id" />
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-erp w-100" :disabled="paymentForm.processing">
                        {{ paymentForm.processing ? t('common.posting') : t('iran_cars.post_payment') }}
                    </button>
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('common.reference') }}</label>
                    <input v-model="paymentForm.reference" class="form-control form-erp-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <input v-model="paymentForm.notes" class="form-control form-erp-control" />
                </div>
            </form>
        </div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="p-3 border-bottom">
                <h3 class="erp-panel-title mb-0">{{ t('iran_cars.payments') }}</h3>
            </div>
            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('iran_cars.voucher') }}</th>
                            <th>{{ t('common.date') }}</th>
                            <th>{{ t('iran_cars.cash_bank') }}</th>
                            <th>{{ t('common.reference') }}</th>
                            <th class="text-end">{{ t('common.amount') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!car.payments?.length">
                            <td colspan="6">
                                <EmptyState icon="$">{{ t('iran_cars.no_payments') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="payment in car.payments" :key="payment.id">
                            <td class="ps-4 fw-semibold">
                                {{ payment.voucher_number }}
                                <div v-if="payment.journal_entry_id" class="small">
                                    <Link :href="route('journals.show', payment.journal_entry_id)">
                                        {{ payment.journal_voucher }}
                                    </Link>
                                </div>
                            </td>
                            <td>{{ payment.payment_date }}</td>
                            <td>{{ payment.debit_account || '—' }}</td>
                            <td>{{ payment.reference || '—' }}</td>
                            <td class="text-end">
                                <MoneyAmount :value="payment.amount" :currency="payment.currency" show-zero />
                            </td>
                            <td class="text-end pe-4">
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="destroyPayment(payment)"
                                >
                                    {{ t('iran_cars.reverse') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
