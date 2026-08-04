<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    contact_name: '',
    contact_phone: '',
    notes: '',
    is_active: true,
});

const submit = () => form.post(route('dubai-accounts.store'));
</script>

<template>
    <Head :title="t('dubai_accounts.add')" />
    <AppLayout>
        <template #header>{{ t('dubai_accounts.add') }}</template>

        <div class="mb-3">
            <Link :href="route('dubai-accounts.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('dubai_accounts.back') }}
            </Link>
        </div>

        <PageHeader :title="t('dubai_accounts.add')" :subtitle="t('dubai_accounts.form_help')" />

        <form class="erp-form-panel" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('dubai_accounts.partner') }}</label>
                    <input v-model="form.name" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('dubai_accounts.contact') }}</label>
                    <input v-model="form.contact_name" class="form-control form-erp-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('voyage_companies.phone') }}</label>
                    <input v-model="form.contact_phone" class="form-control form-erp-control" />
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <textarea v-model="form.notes" class="form-control form-erp-control" rows="3" />
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input id="activeSwitch" v-model="form.is_active" class="form-check-input" type="checkbox" />
                        <label class="form-check-label" for="activeSwitch">{{ t('common.active') }}</label>
                    </div>
                </div>
            </div>
            <div class="erp-form-actions">
                <Link :href="route('dubai-accounts.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('dubai_accounts.add') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
