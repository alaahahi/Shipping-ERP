<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    types: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    paymentAccounts: { type: Object, default: () => ({ USD: [], AED: [] }) },
    voyages: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    defaults: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const form = useForm({
    type: props.defaults.type || 'receipt',
    voucher_date: new Date().toISOString().slice(0, 10),
    currency: props.defaults.currency || 'USD',
    amount: props.defaults.amount ? Number(props.defaults.amount) : 0,
    payment_account_id: null,
    company_id: props.defaults.company_id || props.companies[0]?.id || null,
    voyage_id: props.defaults.voyage_id || null,
    counterparty: props.defaults.counterparty || '',
    reference: '',
    description: '',
    allocations: props.defaults.voyage_id
        ? [{ voyage_id: props.defaults.voyage_id, amount: props.defaults.amount ? Number(props.defaults.amount) : 0 }]
        : [],
});

const accountOptions = computed(() => props.paymentAccounts[form.currency] || []);
const isReceipt = computed(() => form.type === 'receipt');
const allocatedTotal = computed(() =>
    form.allocations.reduce((sum, row) => sum + (Number(row.amount) || 0), 0)
);
const remainder = computed(() => Math.max(0, Number(form.amount || 0) - allocatedTotal.value));

watch(
    () => form.currency,
    () => {
        form.payment_account_id = accountOptions.value[0]?.id ?? null;
    },
    { immediate: true }
);

watch(
    () => form.type,
    (type) => {
        if (type !== 'receipt') {
            form.allocations = [];
        }
    }
);

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
        allocations: data.type === 'receipt'
            ? data.allocations.filter((row) => row.voyage_id && Number(row.amount) > 0)
            : [],
    })).post(route('money-vouchers.store'));
};
</script>

<template>
    <Head :title="t('money_vouchers.add')" />
    <AppLayout>
        <template #header>{{ t('money_vouchers.add') }}</template>

        <div class="mb-3">
            <Link :href="route('money-vouchers.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('money_vouchers.back') }}
            </Link>
        </div>

        <PageHeader :title="t('money_vouchers.add')" :subtitle="t('money_vouchers.form_help')" />

        <form class="erp-card p-4" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('money_vouchers.type') }}</label>
                    <select v-model="form.type" class="form-select form-erp-control" required>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </div>
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
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('money_vouchers.payment_account') }}</label>
                    <select v-model="form.payment_account_id" class="form-select form-erp-control" required>
                        <option v-for="account in accountOptions" :key="account.id" :value="account.id">{{ account.label }}</option>
                    </select>
                    <InputError :message="form.errors.payment_account_id" />
                </div>
                <div v-if="isReceipt" class="col-md-6">
                    <label class="form-erp-label">{{ t('money_vouchers.company') }}</label>
                    <select v-model="form.company_id" class="form-select form-erp-control" required>
                        <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.label }}</option>
                    </select>
                    <InputError :message="form.errors.company_id" />
                    <div class="small text-secondary mt-1">{{ t('money_vouchers.company_hint') }}</div>
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

                <div v-if="form.allocations.length === 0" class="small text-secondary mb-2">
                    {{ t('money_vouchers.no_allocations', { amount: remainder.toFixed(2) }) }}
                </div>

                <div v-for="(row, index) in form.allocations" :key="index" class="row g-2 mb-2">
                    <div class="col-md-6">
                        <select v-model="row.voyage_id" class="form-select form-erp-control" required>
                            <option v-for="voyage in voyages" :key="voyage.id" :value="voyage.id">{{ voyage.label }}</option>
                        </select>
                        <InputError :message="form.errors[`allocations.${index}.voyage_id`]" />
                    </div>
                    <div class="col-md-4">
                        <input v-model.number="row.amount" type="number" min="0.01" step="0.01" class="form-control form-erp-control" required />
                        <InputError :message="form.errors[`allocations.${index}.amount`]" />
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
                <Link :href="route('money-vouchers.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('money_vouchers.add') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
