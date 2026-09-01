<script setup>
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import { fbGhostButton } from '@/flowbite';
import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    companyId: { type: [Number, String], required: true },
    car: { type: Object, default: null },
});

const emit = defineEmits(['close']);
const { t } = useI18n();

const loading = ref(false);
const error = ref('');
const payload = ref(null);

const stays = computed(() => payload.value?.stays ?? []);
const headingCar = computed(() => payload.value?.car ?? props.car);

const unspecified = (label) => (label && String(label).trim() !== ''
    ? label
    : t('land_trips.unspecified_location'));

const formatDuration = (duration) => {
    const days = Number(duration?.days ?? 0);
    const hours = Number(duration?.hours ?? 0);
    const minutes = Number(duration?.minutes ?? 0);

    if (days === 0 && hours === 0 && minutes === 0) {
        return t('land_trips.duration_less_than_minute');
    }

    const parts = [];
    if (days > 0) {
        parts.push(t('land_trips.duration_days', { n: days }));
    }
    if (hours > 0) {
        parts.push(t('land_trips.duration_hours', { n: hours }));
    }
    if (minutes > 0) {
        parts.push(t('land_trips.duration_minutes', { n: minutes }));
    }

    return parts.join(' · ');
};

const loadHistory = async () => {
    if (!props.show || !props.car?.id) {
        return;
    }

    loading.value = true;
    error.value = '';
    payload.value = null;

    try {
        const { data } = await axios.get(
            route('land-trips.companies.cars.location-history', [props.companyId, props.car.id]),
        );
        payload.value = data;
    } catch {
        error.value = t('land_trips.location_history_error');
    } finally {
        loading.value = false;
    }
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && props.show) {
        emit('close');
    }
};

watch(
    () => [props.show, props.car?.id],
    ([open]) => {
        if (open) {
            window.addEventListener('keydown', onKeydown);
            loadHistory();
            return;
        }

        window.removeEventListener('keydown', onKeydown);
        payload.value = null;
        error.value = '';
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show && car"
            class="fixed inset-0 z-[1090] flex items-center justify-center bg-slate-900/50 p-4"
            role="presentation"
            @click.self="emit('close')"
        >
            <div
                class="flex max-h-[min(88vh,40rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-teal-200 bg-white shadow-xl dark:border-teal-800 dark:bg-gray-800"
                role="dialog"
                aria-modal="true"
                :aria-label="t('land_trips.location_history')"
            >
                <header class="flex items-start justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <div class="min-w-0">
                        <p class="mb-0 text-xs font-semibold text-teal-700 dark:text-teal-400">
                            {{ t('land_trips.location_history') }}
                        </p>
                        <h3 class="mt-1 break-all text-base font-bold text-gray-900 dark:text-white">
                            <ChassisLetterOWarning :value="headingCar?.chassis_no || car.chassis_no" />
                        </h3>
                        <p v-if="headingCar?.model || car.model" class="mb-0 mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ headingCar?.model || car.model }}
                        </p>
                    </div>
                    <button
                        type="button"
                        :class="fbGhostButton"
                        class="!w-auto shrink-0 cursor-pointer"
                        @click="emit('close')"
                    >
                        {{ t('common.close') }}
                    </button>
                </header>

                <div class="min-h-0 flex-1 overflow-auto px-4 py-4">
                    <p v-if="loading" class="mb-0 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('common.loading') }}
                    </p>
                    <p v-else-if="error" class="mb-0 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
                    <p v-else-if="!stays.length" class="mb-0 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('land_trips.location_history_empty') }}
                    </p>
                    <ol v-else class="relative ms-3 border-s border-gray-200 dark:border-gray-600">
                        <li
                            v-for="(stay, index) in stays"
                            :key="`${stay.arrived_at}-${index}`"
                            class="mb-6 ms-6 last:mb-0"
                        >
                            <span
                                class="absolute -start-2 mt-1.5 h-4 w-4 rounded-full border-2 border-white dark:border-gray-800"
                                :style="{ backgroundColor: stay.location_color || '#0f766e' }"
                            />
                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/60">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <h4 class="mb-0 text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ unspecified(stay.location_label) }}
                                    </h4>
                                    <span
                                        v-if="stay.is_current"
                                        class="rounded-full bg-teal-700 px-2 py-0.5 text-[11px] font-semibold text-white"
                                    >
                                        {{ t('land_trips.location_current') }}
                                    </span>
                                </div>
                                <dl class="mb-0 grid gap-1 text-sm">
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-gray-500 dark:text-gray-400">{{ t('land_trips.arrived_at') }}</dt>
                                        <dd class="mb-0 font-medium text-gray-900 dark:text-gray-100">{{ stay.arrived_at || '—' }}</dd>
                                    </div>
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-gray-500 dark:text-gray-400">{{ t('land_trips.left_at') }}</dt>
                                        <dd class="mb-0 font-medium text-gray-900 dark:text-gray-100">
                                            {{ stay.left_at || t('land_trips.still_there') }}
                                        </dd>
                                    </div>
                                    <div class="flex flex-wrap gap-x-2">
                                        <dt class="text-gray-500 dark:text-gray-400">{{ t('land_trips.stayed_for') }}</dt>
                                        <dd class="mb-0 font-semibold text-teal-800 dark:text-teal-300">
                                            {{ formatDuration(stay.duration) }}
                                        </dd>
                                    </div>
                                    <div v-if="stay.changed_by" class="flex flex-wrap gap-x-2">
                                        <dt class="text-gray-500 dark:text-gray-400">{{ t('land_trips.changed_by') }}</dt>
                                        <dd class="mb-0 text-gray-900 dark:text-gray-100">{{ stay.changed_by }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </Teleport>
</template>
