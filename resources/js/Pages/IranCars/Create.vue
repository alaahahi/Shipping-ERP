<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    borders: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    company_id: props.companies[0]?.id ?? '',
    border: props.borders[0]?.value ?? 'amir_abad',
    model_name: '',
    year: '',
    color: '',
    vin: '',
    total_amount: 0,
    notes: '',
});

const submit = () => form.post(route('iran-cars.store'));
</script>

<template>
    <Head :title="t('iran_cars.add')" />
    <AppLayout>
        <template #header>{{ t('iran_cars.add') }}</template>

        <div class="mb-3">
            <Link :href="route('iran-cars.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('iran_cars.back') }}
            </Link>
        </div>

        <PageHeader :title="t('iran_cars.add')" :subtitle="t('iran_cars.form_help')" />

        <form class="erp-form-panel" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.company') }}</label>
                    <select v-model="form.company_id" class="form-select form-erp-control" required>
                        <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.label }}</option>
                    </select>
                    <InputError :message="form.errors.company_id" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.border') }}</label>
                    <select v-model="form.border" class="form-select form-erp-control" required>
                        <option v-for="border in borders" :key="border.value" :value="border.value">{{ border.label }}</option>
                    </select>
                    <InputError :message="form.errors.border" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.model') }}</label>
                    <input v-model="form.model_name" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.model_name" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('iran_cars.year') }}</label>
                    <input v-model="form.year" type="number" min="1980" max="2100" class="form-control form-erp-control" />
                    <InputError :message="form.errors.year" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('iran_cars.color') }}</label>
                    <input v-model="form.color" class="form-control form-erp-control" />
                    <InputError :message="form.errors.color" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.vin') }}</label>
                    <input v-model="form.vin" class="form-control form-erp-control font-monospace" required />
                    <InputError :message="form.errors.vin" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.total') }}</label>
                    <input v-model="form.total_amount" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                    <InputError :message="form.errors.total_amount" />
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <textarea v-model="form.notes" rows="2" class="form-control form-erp-control" />
                </div>
            </div>

            <div class="erp-form-actions">
                <Link :href="route('iran-cars.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('iran_cars.add') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
