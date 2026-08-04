<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    currencies: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
});

const { t } = useI18n();
const emptyLine = () => ({ account_id: null, debit: 0, credit: 0, memo: '' });

const form = useForm({
    entry_date: new Date().toISOString().slice(0, 10),
    currency: 'USD',
    reference: '',
    description: '',
    lines: [emptyLine(), emptyLine()],
});

const addLine = () => form.lines.push(emptyLine());
const removeLine = (index) => {
    if (form.lines.length <= 2) return;
    form.lines.splice(index, 1);
};

const submit = () => form.post(route('journals.store'));
</script>

<template>
    <Head :title="t('journals.new')" />
    <AppLayout>
        <template #header>{{ t('journals.new') }}</template>
        <div class="mb-3">
            <Link :href="route('journals.index')" class="text-decoration-none small fw-semibold">← {{ t('journals.back') }}</Link>
        </div>

        <form class="erp-card p-4" @submit.prevent="submit">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.date') }}</label>
                    <input v-model="form.entry_date" type="date" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.entry_date" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.currency') }}</label>
                    <select v-model="form.currency" class="form-select form-erp-control" required>
                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                    </select>
                    <p class="small text-secondary mt-1 mb-0">{{ t('journals.aed_hint') }}</p>
                    <InputError :message="form.errors.currency" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.reference') }}</label>
                    <input v-model="form.reference" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.description') }}</label>
                    <input v-model="form.description" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.description" />
                </div>
            </div>

            <InputError :message="form.errors.lines" />

            <div class="table-responsive">
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

            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-erp-ghost" @click="addLine">{{ t('journals.add_line') }}</button>
                <div class="d-flex gap-2">
                    <Link :href="route('journals.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                    <button class="btn btn-erp" :disabled="form.processing">{{ form.processing ? t('common.saving') : t('journals.save_draft') }}</button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
