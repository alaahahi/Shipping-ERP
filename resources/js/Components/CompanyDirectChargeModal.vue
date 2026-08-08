<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    companyId: { type: Number, required: true },
    currency: { type: String, default: 'USD' },
    creditAccounts: { type: Array, default: () => [] },
    defaultCreditAccountId: { type: Number, default: null },
});

const emit = defineEmits(['close']);
const { t } = useI18n();

const form = useForm({
    charge_date: new Date().toISOString().slice(0, 10),
    amount: '',
    currency: props.currency || 'USD',
    credit_account_id: props.defaultCreditAccountId,
    reference: '',
    description: '',
});

watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.charge_date = new Date().toISOString().slice(0, 10);
        form.amount = '';
        form.currency = props.currency || 'USD';
        form.credit_account_id = props.defaultCreditAccountId;
        form.reference = '';
        form.description = '';
    }
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        credit_account_id: data.credit_account_id || props.defaultCreditAccountId,
    })).post(route('companies.direct-charges.store', props.companyId), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <div v-if="show" class="erp-modal-backdrop" @click.self="emit('close')">
        <div
            class="erp-modal-dialog erp-card p-0 overflow-hidden"
            style="width: min(520px, 100%)"
            role="dialog"
            aria-modal="true"
            :aria-label="t('companies.direct_charge')"
        >
            <div class="d-flex justify-content-between align-items-start gap-3 p-3 border-bottom">
                <div>
                    <h3 class="h5 erp-display mb-1">{{ t('companies.direct_charge') }}</h3>
                    <p class="small text-secondary mb-0">{{ t('companies.direct_charge_help') }}</p>
                </div>
                <button type="button" class="btn btn-erp-ghost" @click="emit('close')">
                    {{ t('common.cancel') }}
                </button>
            </div>

            <form class="p-3" @submit.prevent="submit">
                <div class="mb-3">
                    <label class="form-label" for="directChargeDate">{{ t('common.date') }}</label>
                    <input
                        id="directChargeDate"
                        v-model="form.charge_date"
                        type="date"
                        class="form-control form-erp-control"
                        required
                    />
                    <InputError :message="form.errors.charge_date" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="directChargeAmount">{{ t('common.amount') }}</label>
                    <div class="input-group">
                        <input
                            id="directChargeAmount"
                            v-model="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="form-control form-erp-control"
                            required
                        />
                        <span class="input-group-text">{{ form.currency }}</span>
                    </div>
                    <InputError :message="form.errors.amount" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="directChargeCredit">{{ t('companies.credit_account') }}</label>
                    <select
                        id="directChargeCredit"
                        v-model="form.credit_account_id"
                        class="form-select form-erp-control"
                    >
                        <option
                            v-for="account in creditAccounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.label }}
                        </option>
                    </select>
                    <p class="small text-secondary mb-0 mt-1">{{ t('companies.credit_account_help') }}</p>
                    <InputError :message="form.errors.credit_account_id" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="directChargeReference">{{ t('common.reference') }}</label>
                    <input
                        id="directChargeReference"
                        v-model="form.reference"
                        type="text"
                        class="form-control form-erp-control"
                        maxlength="120"
                    />
                    <InputError :message="form.errors.reference" />
                </div>

                <div class="mb-0">
                    <label class="form-label" for="directChargeDescription">{{ t('companies.direct_charge_reason') }}</label>
                    <textarea
                        id="directChargeDescription"
                        v-model="form.description"
                        class="form-control form-erp-control"
                        rows="3"
                        required
                    />
                    <InputError :message="form.errors.description" />
                </div>
            </form>

            <div class="erp-form-actions p-3 border-top">
                <button type="button" class="btn btn-erp-ghost" @click="emit('close')">
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="button"
                    class="btn btn-erp"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? t('common.posting') : t('companies.direct_charge_submit') }}
                </button>
            </div>
        </div>
    </div>
</template>
