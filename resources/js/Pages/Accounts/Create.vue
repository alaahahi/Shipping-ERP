<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    types: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    code: '',
    name: '',
    type: 'asset',
    currency: 'USD',
    parent_id: null,
    description: '',
    is_active: true,
});

const submit = () => form.post(route('accounts.store'));
</script>

<template>
    <Head :title="t('accounts.add')" />
    <AppLayout>
        <template #header>{{ t('accounts.add') }}</template>
        <div class="mb-3">
            <Link :href="route('accounts.index')" class="text-decoration-none small fw-semibold">← {{ t('accounts.back') }}</Link>
        </div>

        <form class="erp-card p-4 col-lg-8" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('accounts.code') }}</label>
                    <input v-model="form.code" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="col-md-8">
                    <label class="form-erp-label">{{ t('common.name') }}</label>
                    <input v-model="form.name" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('accounts.type') }}</label>
                    <select v-model="form.type" class="form-select form-erp-control" required>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                    <InputError :message="form.errors.type" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('common.currency') }}</label>
                    <select v-model="form.currency" class="form-select form-erp-control" required>
                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                    </select>
                    <p class="small text-secondary mt-1 mb-0">{{ t('accounts.aed_hint') }}</p>
                    <InputError :message="form.errors.currency" />
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
                    <InputError :message="form.errors.description" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('accounts.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button class="btn btn-erp" :disabled="form.processing">{{ form.processing ? t('common.saving') : t('accounts.create_account') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
