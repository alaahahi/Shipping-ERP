<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    account: { type: Object, required: true },
    types: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    code: props.account.code,
    name: props.account.name,
    type: props.account.type,
    currency: props.account.currency,
    parent_id: props.account.parent_id,
    description: props.account.description ?? '',
    is_active: props.account.is_active,
    show_on_dashboard: props.account.show_on_dashboard,
});

const submit = () => form.put(route('accounts.update', props.account.id));
</script>

<template>
    <Head :title="`${t('accounts.edit_account')} ${account.code}`" />
    <AppLayout>
        <template #header>{{ t('accounts.edit_account') }}</template>
        <div class="mb-3">
            <Link :href="route('accounts.index')" class="text-decoration-none small fw-semibold">← {{ t('accounts.back') }}</Link>
        </div>

        <form class="erp-card p-4 col-lg-8" @submit.prevent="submit">
            <div v-if="account.is_system" class="alert alert-light border mb-3">{{ t('accounts.system_locked') }}</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('accounts.code') }}</label>
                    <input v-model="form.code" class="form-control form-erp-control" :disabled="account.is_system" required />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="col-md-8">
                    <label class="form-erp-label">{{ t('common.name') }}</label>
                    <input v-model="form.name" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('accounts.type') }}</label>
                    <select v-model="form.type" class="form-select form-erp-control" :disabled="account.is_system" required>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('common.currency') }}</label>
                    <select v-model="form.currency" class="form-select form-erp-control" :disabled="account.is_system" required>
                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('accounts.parent') }}</label>
                    <select v-model="form.parent_id" class="form-select form-erp-control">
                        <option :value="null">{{ t('accounts.none_parent') }}</option>
                        <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.label }}</option>
                    </select>
                    <InputError :message="form.errors.parent_id" />
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('accounts.description') }}</label>
                    <textarea v-model="form.description" class="form-control form-erp-control" rows="3" />
                </div>
                <div class="col-12 form-check">
                    <input id="is_active" v-model="form.is_active" class="form-check-input" type="checkbox" />
                    <label for="is_active" class="form-check-label">{{ t('common.active') }}</label>
                </div>
                <div class="col-12 form-check">
                    <input id="show_on_dashboard" v-model="form.show_on_dashboard" class="form-check-input" type="checkbox" />
                    <label for="show_on_dashboard" class="form-check-label">{{ t('accounts.show_on_dashboard') }}</label>
                    <p class="small text-secondary mb-0">{{ t('accounts.show_on_dashboard_help') }}</p>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('accounts.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button class="btn btn-erp" :disabled="form.processing">{{ form.processing ? t('common.saving') : t('users.save_changes') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
