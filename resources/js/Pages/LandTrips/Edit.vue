<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import LandTripCarsModal from '@/Components/LandTripCarsModal.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    trip: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    voyages: { type: Array, default: () => [] },
    voyageCars: { type: Array, default: () => [] },
    carStatuses: { type: Array, default: () => [] },
});

const { t } = useI18n();
const showCars = ref(false);

const form = useForm({
    cmr_number: props.trip.cmr_number,
    driver_name: props.trip.driver_name,
    from_country_id: props.trip.from_country_id,
    to_country_id: props.trip.to_country_id,
    departure_date: props.trip.departure_date,
    arrival_date: props.trip.arrival_date ?? '',
    company_id: props.trip.company_id,
    freight_amount: props.trip.freight_amount,
    currency: props.trip.currency,
    voyage_id: props.trip.voyage_id ?? '',
    notes: props.trip.notes ?? '',
    cars: (props.trip.cars ?? []).map((car) => ({
        voyage_car_id: car.voyage_car_id,
        chassis_no: car.chassis_no ?? '',
        cmr_waybill: car.cmr_waybill ?? '',
        consignee_name: car.consignee_name ?? '',
        model: car.model || car.description || '',
        color: car.color ?? '',
        description: car.description || car.model || '',
        weight: car.weight ?? '',
        notes: car.notes ?? '',
        location_status_id: car.location_status_id ?? '',
    })),
});

const loadVoyageCars = () => {
    router.get(route('land-trips.edit', props.trip.id), { voyage_id: form.voyage_id || '' }, {
        preserveState: true,
        preserveScroll: true,
        only: ['voyageCars'],
    });
};

const submit = () => form.put(route('land-trips.update', props.trip.id));
</script>

<template>
    <Head :title="t('land_trips.edit')" />
    <AppLayout>
        <template #header>{{ t('land_trips.edit') }}</template>

        <div class="mb-3">
            <Link :href="route('land-trips.show', trip.id)" class="text-decoration-none small fw-semibold">
                ← {{ t('land_trips.back') }}
            </Link>
        </div>

        <PageHeader :title="t('land_trips.edit')" :subtitle="trip.cmr_number" />

        <form class="erp-form-panel" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('land_trips.cmr') }}</label>
                    <input v-model="form.cmr_number" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.cmr_number" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('land_trips.driver') }}</label>
                    <input v-model="form.driver_name" class="form-control form-erp-control" required />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('land_trips.company') }}</label>
                    <select v-model="form.company_id" class="form-select form-erp-control" required>
                        <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('land_trips.from_country') }}</label>
                    <select v-model="form.from_country_id" class="form-select form-erp-control" required>
                        <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('land_trips.to_country') }}</label>
                    <select v-model="form.to_country_id" class="form-select form-erp-control" required>
                        <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.label }}</option>
                    </select>
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
                    <button type="button" class="btn btn-erp" @click="showCars = true">
                        {{ t('land_trips.manage_cars') }} ({{ form.cars.length }})
                    </button>
                </div>
            </div>

            <div class="erp-form-actions">
                <Link :href="route('land-trips.show', trip.id)" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('users.save_changes') }}
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
