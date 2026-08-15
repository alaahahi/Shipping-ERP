<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import LandTripCarsModal from '@/Components/LandTripCarsModal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    countries: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    voyages: { type: Array, default: () => [] },
    voyageCars: { type: Array, default: () => [] },
    carStatuses: { type: Array, default: () => [] },
    selectedVoyageId: { type: [Number, String], default: null },
    selectedCompanyId: { type: [Number, String], default: null },
    companyLocked: { type: Boolean, default: false },
});

const { t } = useI18n();
const showCars = ref(false);

const form = useForm({
    cmr_number: '',
    driver_name: '',
    from_country_id: props.countries[0]?.id ?? '',
    to_country_id: props.countries[1]?.id ?? props.countries[0]?.id ?? '',
    departure_date: new Date().toISOString().slice(0, 10),
    arrival_date: '',
    company_id: props.selectedCompanyId || props.companies[0]?.id || '',
    freight_amount: 0,
    currency: 'USD',
    voyage_id: props.selectedVoyageId ?? '',
    notes: '',
    cars: [],
});

const backHref = computed(() => {
    if (props.selectedCompanyId) {
        return route('land-trips.companies.show', props.selectedCompanyId);
    }

    return route('land-trips.index');
});

const backLabel = computed(() => {
    if (props.selectedCompanyId) {
        return t('land_trips.back_company');
    }

    return t('land_trips.back_companies');
});

const selectedCompanyLabel = computed(() => {
    const match = props.companies.find((item) => String(item.id) === String(form.company_id));

    return match?.label || '—';
});

const loadVoyageCars = () => {
    router.get(
        route('land-trips.create'),
        {
            voyage_id: form.voyage_id || '',
            company_id: form.company_id || props.selectedCompanyId || '',
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['voyageCars', 'selectedVoyageId', 'selectedCompanyId', 'companyLocked'],
        },
    );
};

const submit = () => form.post(route('land-trips.store'));
</script>

<template>
    <Head :title="t('land_trips.add')" />
    <AppLayout>
        <template #header>{{ t('land_trips.add') }}</template>

        <div class="mb-3">
            <Link :href="backHref" class="text-decoration-none small fw-semibold">
                ← {{ backLabel }}
            </Link>
        </div>

        <PageHeader :title="t('land_trips.add')" :subtitle="t('land_trips.form_help')" />

        <form class="erp-form-panel" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('land_trips.company') }}</label>
                    <select
                        v-if="!companyLocked"
                        v-model="form.company_id"
                        class="form-select form-erp-control"
                        required
                    >
                        <option v-for="company in companies" :key="company.id" :value="company.id">
                            {{ company.label }}
                        </option>
                    </select>
                    <input
                        v-else
                        class="form-control form-erp-control"
                        :value="selectedCompanyLabel"
                        readonly
                    />
                    <InputError :message="form.errors.company_id" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('land_trips.cmr') }}</label>
                    <input v-model="form.cmr_number" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.cmr_number" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('land_trips.driver') }}</label>
                    <input v-model="form.driver_name" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.driver_name" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('land_trips.from_country') }}</label>
                    <select v-model="form.from_country_id" class="form-select form-erp-control" required>
                        <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.label }}</option>
                    </select>
                    <InputError :message="form.errors.from_country_id" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('land_trips.to_country') }}</label>
                    <select v-model="form.to_country_id" class="form-select form-erp-control" required>
                        <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.label }}</option>
                    </select>
                    <InputError :message="form.errors.to_country_id" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('land_trips.departure') }}</label>
                    <input v-model="form.departure_date" type="date" class="form-control form-erp-control" required />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('land_trips.arrival') }}</label>
                    <input v-model="form.arrival_date" type="date" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('land_trips.freight') }}</label>
                    <input v-model="form.freight_amount" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('common.currency') }}</label>
                    <select v-model="form.currency" class="form-select form-erp-control">
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('land_trips.sea_voyage') }}</label>
                    <select v-model="form.voyage_id" class="form-select form-erp-control" @change="loadVoyageCars">
                        <option value="">{{ t('common.none') }}</option>
                        <option v-for="voyage in voyages" :key="voyage.id" :value="voyage.id">{{ voyage.label }}</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <textarea v-model="form.notes" rows="2" class="form-control form-erp-control" />
                </div>
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-erp" @click="showCars = true">
                            {{ t('land_trips.manage_cars') }} ({{ form.cars.length }})
                        </button>
                        <span class="small text-secondary">{{ t('land_trips.cars_after_save_hint') }}</span>
                    </div>
                    <InputError :message="form.errors.cars" />
                </div>
            </div>

            <div class="erp-form-actions">
                <Link :href="backHref" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('land_trips.add') }}
                </button>
            </div>
        </form>

        <LandTripCarsModal
            :show="showCars"
            :cars="form.cars"
            :voyage-cars="voyageCars"
            :car-statuses="carStatuses"
            :processing="form.processing"
            @update:cars="form.cars = $event"
            @close="showCars = false"
            @save="showCars = false"
        />
    </AppLayout>
</template>
