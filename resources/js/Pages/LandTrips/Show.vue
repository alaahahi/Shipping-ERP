<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import LandTripCarsModal from '@/Components/LandTripCarsModal.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    trip: { type: Object, required: true },
    voyageCars: { type: Array, default: () => [] },
    transitions: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canPost: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const showCars = ref(false);
const posting = ref(false);

const carsForm = useForm({
    cars: (props.trip.cars ?? []).map((car) => ({
        voyage_car_id: car.voyage_car_id,
        chassis_no: car.chassis_no ?? '',
        consignee_name: car.consignee_name ?? '',
        description: car.description ?? '',
        weight: car.weight ?? '',
        notes: car.notes ?? '',
    })),
});

const saveCars = () => {
    carsForm.post(route('land-trips.cars.sync', props.trip.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCars.value = false;
        },
    });
};

const transition = (status) => {
    router.post(route('land-trips.transition', props.trip.id), { status }, { preserveScroll: true });
};

const postFreight = () => {
    if (!window.confirm(t('land_trips.post_confirm'))) return;
    router.post(route('land-trips.post', props.trip.id), {}, {
        preserveScroll: true,
        onStart: () => {
            posting.value = true;
        },
        onFinish: () => {
            posting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="trip.cmr_number" />
    <AppLayout>
        <template #header>{{ trip.cmr_number }}</template>
        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link :href="route('land-trips.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('land_trips.back') }}
            </Link>
        </div>

        <PageHeader :kicker="t('land_trips.title')" :title="trip.cmr_number" :subtitle="trip.route">
            <template #actions>
                <StatusBadge :tone="trip.status_tone" :label="trip.status_label" />
                <Link v-if="canManage && trip.is_editable" :href="route('land-trips.edit', trip.id)" class="btn btn-erp-ghost">
                    {{ t('common.edit') }}
                </Link>
                <button
                    v-if="canPost && !trip.is_posted"
                    type="button"
                    class="btn btn-erp"
                    :class="{ 'is-posting': posting }"
                    :disabled="posting"
                    @click="postFreight"
                >
                    {{ posting ? t('common.posting') : t('land_trips.post') }}
                </button>
            </template>
        </PageHeader>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('land_trips.driver') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.05rem">{{ trip.driver_name }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('land_trips.company') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.05rem">{{ trip.company_name }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('land_trips.freight') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">
                        <MoneyAmount :value="trip.freight_amount" :currency="trip.currency" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('land_trips.sea_voyage') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.05rem">
                        <Link v-if="trip.voyage_id" :href="route('voyages.show', trip.voyage_id)">{{ trip.voyage_number }}</Link>
                        <span v-else>—</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="erp-card p-3 mb-3 d-flex flex-wrap gap-2 align-items-center" v-if="canManage && transitions.length">
            <span class="small text-secondary me-2">{{ t('land_trips.change_status') }}</span>
            <button
                v-for="item in transitions"
                :key="item.value"
                type="button"
                class="btn btn-sm btn-erp-ghost"
                @click="transition(item.value)"
            >
                {{ item.label }}
            </button>
        </div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="erp-panel-title mb-0">{{ t('land_trips.cars') }}</h3>
                    <p class="small text-secondary mb-0">{{ t('land_trips.cars_help') }}</p>
                </div>
                <button v-if="canManage && trip.is_editable" type="button" class="btn btn-erp" @click="showCars = true">
                    {{ t('land_trips.manage_cars') }}
                </button>
            </div>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('land_trips.chassis') }}</th>
                            <th>{{ t('land_trips.consignee') }}</th>
                            <th>{{ t('common.description') }}</th>
                            <th>{{ t('land_trips.weight') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!trip.cars?.length">
                            <td colspan="4">
                                <EmptyState icon="C">{{ t('land_trips.no_cars') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="car in trip.cars" :key="car.id">
                            <td class="ps-4 font-monospace">{{ car.chassis_no || '—' }}</td>
                            <td>{{ car.consignee_name }}</td>
                            <td>{{ car.description || '—' }}</td>
                            <td>{{ car.weight || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="trip.journal_voucher" class="mt-3 small">
            {{ t('land_trips.journal') }}:
            <Link :href="route('journals.show', trip.journal_entry_id)">{{ trip.journal_voucher }}</Link>
        </div>

        <LandTripCarsModal
            :show="showCars"
            :cars="carsForm.cars"
            :voyage-cars="voyageCars"
            :processing="carsForm.processing"
            @update:cars="carsForm.cars = $event"
            @close="showCars = false"
            @save="saveCars"
        />
    </AppLayout>
</template>
