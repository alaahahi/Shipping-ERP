<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
});

const { t } = useI18n();

const form = useForm({
    name: props.company.name,
    contact_name: props.company.contact_name ?? '',
    contact_phone: props.company.contact_phone ?? '',
    whatsapp_phone: props.company.whatsapp_phone ?? '',
    notify_whatsapp: props.company.notify_whatsapp ?? false,
    email: props.company.email ?? '',
    address: props.company.address ?? '',
    notes: props.company.notes ?? '',
    is_active: props.company.is_active,
});

const submit = () => {
    form.put(route('companies.update', props.company.id));
};
</script>

<template>
    <Head :title="t('companies.edit')" />
    <AppLayout>
        <template #header>{{ t('companies.edit') }}</template>

        <div class="mb-3">
            <Link :href="route('companies.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('companies.back') }}
            </Link>
        </div>

        <PageHeader :title="t('companies.edit')" :subtitle="company.name" />

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
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input id="notifyWhatsapp" v-model="form.notify_whatsapp" class="form-check-input" type="checkbox" />
                        <label class="form-check-label" for="notifyWhatsapp">{{ t('companies.notify_whatsapp') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('common.email') }}</label>
                    <input v-model="form.email" type="email" class="form-control form-erp-control" />
                </div>
                <div class="col-md-5">
                    <label class="form-erp-label">{{ t('companies.address') }}</label>
                    <input v-model="form.address" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input id="companyActive" v-model="form.is_active" class="form-check-input" type="checkbox" />
                        <label class="form-check-label" for="companyActive">{{ t('common.active') }}</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <textarea v-model="form.notes" rows="2" class="form-control form-erp-control" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('companies.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('users.save_changes') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
