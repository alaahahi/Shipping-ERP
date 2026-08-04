<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    entry: { type: Object, required: true },
    currencies: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
});

const { t } = useI18n();
const emptyLine = () => ({ account_id: null, debit: 0, credit: 0, memo: '' });

const form = useForm({
    entry_date: props.entry.entry_date,
    currency: props.entry.currency,
    reference: props.entry.reference ?? '',
    description: props.entry.description,
    lines: props.entry.lines.length
        ? props.entry.lines.map((line) => ({ ...line }))
        : [emptyLine(), emptyLine()],
});

const addLine = () => form.lines.push(emptyLine());
const removeLine = (index) => {
    if (form.lines.length <= 2) return;
    form.lines.splice(index, 1);
};

const submit = () => form.put(route('journals.update', props.entry.id));
</script>

<template>
    <Head :title="`${t('common.edit')} ${entry.voucher_number}`" />
    <AppLayout>
        <template #header>{{ t('journals.edit_draft') }} · {{ entry.voucher_number }}</template>
        <div class="mb-3">
            <Link :href="route('journals.show', entry.id)" class="text-decoration-none small fw-semibold">← {{ t('journals.back_voucher') }}</Link>
        </div>

        <form class="erp-card p-4" @submit.prevent="submit">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.date') }}</label>
                    <input v-model="form.entry_date" type="date" class="form-control form-erp-control" required />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.currency') }}</label>
                    <select v-model="form.currency" class="form-select form-erp-control" required>
                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.reference') }}</label>
                    <input v-model="form.reference" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.description') }}</label>
                    <input v-model="form.description" class="form-control form-erp-control" required />
                </div>
            </div>

            <InputError :message="form.errors.lines" />

            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ t('journals.account') }}</th>
                            <th style="width: 140px">{{ t('journals.debit') }}</th>
                            <th style="width: 140px">{{ t('journals.credit') }}</th>
                            <th>{{ t('journals.memo') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in form.lines" :key="index">
                            <td>
                                <select v-model="line.account_id" class="form-select form-erp-control" required>
                                    <option :value="null">{{ t('journals.select_account') }}</option>
                                    <option
                                        v-for="account in accounts.filter((item) => item.currency === form.currency)"
                                        :key="account.id"
                                        :value="account.id"
                                    >
                                        {{ account.label }}
                                    </option>
                                </select>
                            </td>
                            <td><input v-model.number="line.debit" type="number" min="0" step="0.01" class="form-control form-erp-control" /></td>
                            <td><input v-model.number="line.credit" type="number" min="0" step="0.01" class="form-control form-erp-control" /></td>
                            <td><input v-model="line.memo" class="form-control form-erp-control" /></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" @click="removeLine(index)">×</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-erp-ghost" @click="addLine">{{ t('journals.add_line') }}</button>
                <button class="btn btn-erp" :disabled="form.processing">{{ form.processing ? t('common.saving') : t('journals.save_draft') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
