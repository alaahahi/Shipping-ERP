<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    voucher: { type: Object, required: true },
    currencies: { type: Array, default: () => [] },
    paymentAccounts: { type: Object, default: () => ({ USD: [], AED: [] }) },
    voyages: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    voucher_date: props.voucher.voucher_date,
    currency: props.voucher.currency,
    amount: Number(props.voucher.amount),
    payment_account_id: props.voucher.payment_account_id,
    company_id: props.voucher.company_id,
    voyage_id: props.voucher.voyage_id,
    counterparty: props.voucher.counterparty ?? '',
    reference: props.voucher.reference ?? '',
    description: props.voucher.description ?? '',
    allocations: (props.voucher.allocations || []).map((row) => ({
        voyage_id: row.voyage_id,
        amount: Number(row.amount),
    })),
});

const accountOptions = computed(() => props.paymentAccounts[form.currency] || []);
const isReceipt = computed(() => props.voucher.type === 'receipt');
const allocatedTotal = computed(() =>
    form.allocations.reduce((sum, row) => sum + (Number(row.amount) || 0), 0)
);
const remainder = computed(() => Math.max(0, Number(form.amount || 0) - allocatedTotal.value));

const addAllocation = () => {
    form.allocations.push({
        voyage_id: props.voyages[0]?.id ?? null,
        amount: remainder.value || 0,
    });
};

const removeAllocation = (index) => {
    form.allocations.splice(index, 1);
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        allocations: isReceipt.value
            ? data.allocations.filter((row) => row.voyage_id && Number(row.amount) > 0)
            : [],
    })).put(route('money-vouchers.update', props.voucher.id));
};
</script>

<template>
    <Head :title="t('money_vouchers.edit')" />
    <AppLayout>
        <template #header>{{ t('money_vouchers.edit') }}</template>

        <div class="mb-3">
            <Link :href="route('money-vouchers.show', voucher.id)" class="text-decoration-none small fw-semibold">
                ← {{ voucher.voucher_number }}
            </Link>
        </div>

        <PageHeader :title="t('money_vouchers.edit')" :subtitle="voucher.voucher_number" />

        <form
            class="erp-card erp-voucher-panel p-4"
            :class="isReceipt ? 'is-receipt' : 'is-payment'"
            @submit.prevent="submit"
        >
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.date') }}</label>
                    <input v-model="form.voucher_date" type="date" class="form-control form-erp-control" required />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.amount') }}</label>
                    <input v-model.number="form.amount" type="number" min="0.01" step="0.01" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.amount" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.currency') }}</label>
                    <select v-model="form.currency" class="form-select form-erp-control" required>
                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('money_vouchers.type') }}</label>
                    <input class="form-control form-erp-control" :value="voucher.type_label" disabled />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('money_vouchers.payment_account') }}</label>
                    <select v-model="form.payment_account_id" class="form-select form-erp-control" required>
                        <option v-for="account in accountOptions" :key="account.id" :value="account.id">{{ account.label }}</option>
                    </select>
                </div>
                <div v-if="isReceipt" class="col-md-6">
                    <label class="form-erp-label">{{ t('money_vouchers.company') }}</label>
                    <select v-model="form.company_id" class="form-select form-erp-control" required>
                        <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.label }}</option>
                    </select>
                    <InputError :message="form.errors.company_id" />
                </div>
                <div v-else class="col-md-6">
                    <label class="form-erp-label">{{ t('money_vouchers.counterparty') }}</label>
                    <input v-model="form.counterparty" class="form-control form-erp-control" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('common.reference') }}</label>
                    <input v-model="form.reference" class="form-control form-erp-control" />
                </div>
                <div class="col-md-8">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <input v-model="form.description" class="form-control form-erp-control" />
                </div>
            </div>

            <div v-if="isReceipt" class="border rounded-3 p-3 mt-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-2">
                    <div>
                        <h4 class="h6 mb-0">{{ t('money_vouchers.allocations') }}</h4>
                        <div class="small text-secondary">{{ t('money_vouchers.allocations_help') }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-erp-ghost" @click="addAllocation">
                        {{ t('money_vouchers.add_allocation') }}
                    </button>
                </div>
                <div v-for="(row, index) in form.allocations" :key="index" class="row g-2 mb-2">
                    <div class="col-md-6">
                        <select v-model="row.voyage_id" class="form-select form-erp-control" required>
                            <option v-for="voyage in voyages" :key="voyage.id" :value="voyage.id">{{ voyage.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input v-model.number="row.amount" type="number" min="0.01" step="0.01" class="form-control form-erp-control" required />
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger w-100" @click="removeAllocation(index)">
                            {{ t('common.delete') }}
                        </button>
                    </div>
                </div>
                <div class="small text-secondary">
                    {{ t('money_vouchers.allocated') }}: {{ allocatedTotal.toFixed(2) }}
                    · {{ t('money_vouchers.remainder') }}: {{ remainder.toFixed(2) }}
                </div>
                <InputError :message="form.errors.allocations" />
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('money-vouchers.show', voucher.id)" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('users.save_changes') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
