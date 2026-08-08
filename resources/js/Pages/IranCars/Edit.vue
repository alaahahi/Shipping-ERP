<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    car: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
    borders: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    company_id: props.car.company_id,
    border: props.car.border,
    model_name: props.car.model_name,
    year: props.car.year ?? '',
    color: props.car.color ?? '',
    vin: props.car.vin,
    total_amount: props.car.total_amount,
    sale_price: props.car.sale_price ?? props.car.total_amount,
    notes: props.car.notes ?? '',
});

const submit = () => form.put(route('iran-cars.update', props.car.id));
</script>

<template>
    <Head :title="t('iran_cars.edit')" />
    <AppLayout>
        <template #header>{{ t('iran_cars.edit') }}</template>

        <div class="mb-3">
            <Link :href="route('iran-cars.show', car.id)" class="text-decoration-none small fw-semibold">
                ← {{ t('iran_cars.back') }}
            </Link>
        </div>

        <PageHeader :title="t('iran_cars.edit')" :subtitle="car.vin" />

        <form class="erp-form-panel" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.company') }}</label>
                    <select
                        v-model="form.company_id"
                        class="form-select form-erp-control"
                        :disabled="car.is_total_locked"
                        required
                    >
                        <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.label }}</option>
                    </select>
                    <InputError :message="form.errors.company_id" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.border') }}</label>
                    <select v-model="form.border" class="form-select form-erp-control" required>
                        <option v-for="border in borders" :key="border.value" :value="border.value">{{ border.label }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.model') }}</label>
                    <input v-model="form.model_name" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.model_name" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('iran_cars.year') }}</label>
                    <input v-model="form.year" type="number" min="1980" max="2100" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('iran_cars.color') }}</label>
                    <input v-model="form.color" class="form-control form-erp-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.vin') }}</label>
                    <input v-model="form.vin" class="form-control form-erp-control font-monospace" required />
                    <InputError :message="form.errors.vin" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.list_price') }}</label>
                    <input
                        v-model="form.total_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control form-erp-control"
                    />
                    <InputError :message="form.errors.total_amount" />
                </div>
                <div v-if="car.is_sold" class="col-md-6">
                    <label class="form-erp-label">{{ t('iran_cars.sale_price') }}</label>
                    <input
                        v-model="form.sale_price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control form-erp-control"
                        :disabled="car.is_total_locked"
                    />
                    <p v-if="car.is_total_locked" class="small text-secondary mb-0 mt-1">{{ t('iran_cars.sale_price_locked') }}</p>
                    <InputError :message="form.errors.sale_price" />
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <textarea v-model="form.notes" rows="2" class="form-control form-erp-control" />
                </div>
            </div>

            <div class="erp-form-actions">
                <Link :href="route('iran-cars.show', car.id)" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('users.save_changes') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
