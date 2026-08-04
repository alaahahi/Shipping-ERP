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
    whatsapp_phone: '',
    notify_whatsapp: false,
    email: '',
    address: '',
    notes: '',
    is_active: true,
});

const submit = () => {
    form.post(route('companies.store'));
};
</script>

<template>
    <Head :title="t('companies.add')" />
    <AppLayout>
        <template #header>{{ t('companies.add') }}</template>

        <div class="mb-3">
            <Link :href="route('companies.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('companies.back') }}
            </Link>
        </div>

        <PageHeader :title="t('companies.add')" :subtitle="t('companies.form_help')" />

        <form class="erp-card p-4" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('companies.name') }}</label>
                    <input v-model="form.name" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('companies.contact') }}</label>
                    <input v-model="form.contact_name" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('voyage_companies.phone') }}</label>
                    <input v-model="form.contact_phone" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('companies.whatsapp_phone') }}</label>
                    <input v-model="form.whatsapp_phone" class="form-control form-erp-control" :placeholder="t('companies.whatsapp_phone_help')" />
                </div>
                <div class="col-md-2">
                    <label class="form-erp-label">{{ t('companies.notify_whatsapp') }}</label>
                    <div class="form-check mt-2">
                        <input v-model="form.notify_whatsapp" id="notify_whatsapp" type="checkbox" class="form-check-input" />
                        <label class="form-check-label" for="notify_whatsapp">{{ t('common.yes') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('common.email') }}</label>
                    <input v-model="form.email" type="email" class="form-control form-erp-control" />
                </div>
                <div class="col-md-8">
                    <label class="form-erp-label">{{ t('companies.address') }}</label>
                    <input v-model="form.address" class="form-control form-erp-control" />
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <textarea v-model="form.notes" rows="2" class="form-control form-erp-control" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('companies.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('companies.add') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
