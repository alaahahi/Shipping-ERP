<script setup>
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import { useLandTripStation } from '@/composables/useLandTripStation';
import { fbGhostButton } from '@/flowbite';
import { computed, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    car: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const { t } = useI18n();
const { stationLabel } = useLandTripStation();

const rows = computed(() => {
    const car = props.car;
    if (!car) {
        return [];
    }

    return [
        { label: t('land_trips.model'), value: car.model || car.description },
        { label: t('land_trips.color'), value: car.color },
        { label: t('land_trips.year'), value: car.year },
        { label: t('land_trips.cmr_waybill'), value: car.cmr_waybill },
        { label: t('land_trips.consignee'), value: car.consignee_name },
        { label: t('land_trips.car_price'), value: car.price != null && car.price !== '' ? Math.round(Number(car.price) || 0) : null },
        {
            label: t('land_trips.location_status'),
            value: car.location_status_label
                || (car.location_status ? stationLabel(car.location_status) : null)
                || t('land_trips.unspecified_location'),
        },
        { label: t('common.notes'), value: car.notes },
        { label: t('common.date'), value: car.created_at_label || car.created_at },
    ];
});

const onKeydown = (event) => {
    if (event.key === 'Escape' && props.show) {
        emit('close');
    }
};

watch(
    () => props.show,
    (open) => {
        if (open) {
            window.addEventListener('keydown', onKeydown);
            return;
        }
        window.removeEventListener('keydown', onKeydown);
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});

const display = (value) => {
    const text = String(value ?? '').trim();
    return text === '' ? '—' : text;
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show && car"
                class="land-car-view-overlay"
                role="presentation"
                @click.self="emit('close')"
            >
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 translate-y-1 scale-[0.98]"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-1 scale-[0.98]"
                >
                    <div
                        v-if="show && car"
                        class="land-car-view-panel"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="t('land_trips.view_car')"
                    >
                        <header class="land-car-view-head">
                            <div>
                                <p class="land-car-view-kicker mb-0">{{ t('land_trips.view_car') }}</p>
                                <h3 class="land-car-view-title">
                                    <ChassisLetterOWarning :value="car.chassis_no" />
                                </h3>
                            </div>
                            <button
                                type="button"
                                :class="[fbGhostButton, '!w-auto cursor-pointer land-car-view-close']"
                                :aria-label="t('common.close')"
                                @click="emit('close')"
                            >
                                {{ t('common.close') }}
                            </button>
                        </header>

                        <dl class="land-car-view-grid">
                            <div v-for="(row, index) in rows" :key="`${row.label}-${index}`" class="land-car-view-row">
                                <dt>{{ row.label }}</dt>
                                <dd>{{ display(row.value) }}</dd>
                            </div>
                        </dl>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
