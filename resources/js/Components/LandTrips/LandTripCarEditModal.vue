<script setup>
import InputError from '@/Components/InputError.vue';
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import { sanitizeChassisNumber } from '@/composables/useChassisLetterO';
import { useLandTripStation } from '@/composables/useLandTripStation';
import { fbButton, fbGhostButton, fbInput, fbLabel } from '@/flowbite';
import { useForm } from '@inertiajs/vue3';
import { nextTick, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    companyId: { type: [Number, String], required: true },
    car: { type: Object, default: null },
    carStatuses: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const { t } = useI18n();
const { stationLabel } = useLandTripStation();

const form = useForm({
    company_id: props.companyId,
    chassis_no: '',
    model: '',
    color: '',
    year: '',
    cmr_waybill: '',
    consignee_name: '',
    price: 0,
    location_status_id: '',
    notes: '',
});

const fillFromCar = (car) => {
    form.clearErrors();
    form.company_id = props.companyId;
    form.chassis_no = sanitizeChassisNumber(car?.chassis_no ?? '');
    form.model = car?.model || car?.description || '';
    form.color = car?.color || '';
    form.year = car?.year ?? '';
    form.cmr_waybill = car?.cmr_waybill || '';
    form.consignee_name = car?.consignee_name || '';
    form.price = Math.round(Number(car?.price ?? 0) || 0);
    form.location_status_id = car?.location_status_id ?? '';
    form.notes = car?.notes || '';
};

watch(
    () => [props.show, props.car],
    async ([open]) => {
        if (!open || !props.car) {
            return;
        }
        fillFromCar(props.car);
        await nextTick();
        document.getElementById('land-car-edit-chassis')?.focus();
    },
);

const onChassisInput = (event) => {
    form.chassis_no = sanitizeChassisNumber(event.target.value);
};

const submit = () => {
    if (!props.car?.id) {
        return;
    }

    form
        .transform((data) => ({
            ...data,
            chassis_no: sanitizeChassisNumber(data.chassis_no),
            year: data.year === '' || data.year === null ? null : Number(data.year),
            location_status_id: data.location_status_id === '' ? null : data.location_status_id,
            price: Math.round(Number(data.price) || 0),
        }))
        .put(route('land-trips.companies.cars.full-update', [props.companyId, props.car.id]), {
            preserveScroll: true,
            preserveState: true,
            only: ['chassisLetterOCount', 'statusSummary', 'flash'],
            onSuccess: () => {
                emit('saved', {
                    id: props.car.id,
                    chassis_no: form.chassis_no,
                    model: form.model,
                    color: form.color,
                    year: form.year === '' || form.year === null ? null : Number(form.year),
                    cmr_waybill: form.cmr_waybill,
                    consignee_name: form.consignee_name,
                    price: Math.round(Number(form.price) || 0),
                    location_status_id: form.location_status_id === '' ? null : form.location_status_id,
                    notes: form.notes,
                    description: form.model || '',
                });
                emit('close');
            },
        });
};
</script>

<template>
    <div
        v-if="show && car"
        class="erp-modal-backdrop"
        @click.self="emit('close')"
        @keydown.escape.prevent="emit('close')"
    >
        <div
            class="erp-modal-dialog erp-card p-0 overflow-hidden land-car-edit-modal"
            style="width: min(640px, 100%)"
            role="dialog"
            aria-modal="true"
            :aria-label="t('land_trips.edit_car')"
        >
            <div class="d-flex justify-content-between align-items-start gap-3 p-3 border-bottom">
                <div>
                    <h3 class="h5 erp-display mb-1">{{ t('land_trips.edit_car') }}</h3>
                    <p class="small text-secondary mb-0">{{ t('land_trips.edit_car_help') }}</p>
                </div>
                <button type="button" :class="fbGhostButton" class="cursor-pointer" @click="emit('close')">
                    {{ t('common.cancel') }}
                </button>
            </div>

            <form class="p-3" @submit.prevent="submit">
                <div class="mb-3">
                    <label :class="fbLabel" for="land-car-edit-chassis">{{ t('land_trips.chassis') }}</label>
                    <input
                        id="land-car-edit-chassis"
                        type="text"
                        maxlength="64"
                        autocomplete="off"
                        :class="[fbInput, 'font-monospace']"
                        :value="form.chassis_no"
                        required
                        @input="onChassisInput"
                    />
                    <ChassisLetterOWarning :value="form.chassis_no" />
                    <InputError :message="form.errors.chassis_no" />
                </div>

                <div class="grid gap-3 md:grid-cols-3 mb-3">
                    <div>
                        <label :class="fbLabel" for="land-car-edit-model">{{ t('land_trips.model') }}</label>
                        <input id="land-car-edit-model" v-model="form.model" type="text" maxlength="180" :class="fbInput" autocomplete="off" />
                        <InputError :message="form.errors.model" />
                    </div>
                    <div>
                        <label :class="fbLabel" for="land-car-edit-color">{{ t('land_trips.color') }}</label>
                        <input id="land-car-edit-color" v-model="form.color" type="text" maxlength="80" :class="fbInput" autocomplete="off" />
                        <InputError :message="form.errors.color" />
                    </div>
                    <div>
                        <label :class="fbLabel" for="land-car-edit-year">{{ t('land_trips.year') }}</label>
                        <input
                            id="land-car-edit-year"
                            v-model="form.year"
                            type="number"
                            min="1980"
                            max="2100"
                            step="1"
                            inputmode="numeric"
                            :class="fbInput"
                        />
                        <InputError :message="form.errors.year" />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 mb-3">
                    <div>
                        <label :class="fbLabel" for="land-car-edit-cmr">{{ t('land_trips.cmr_waybill') }}</label>
                        <input id="land-car-edit-cmr" v-model="form.cmr_waybill" type="text" maxlength="80" :class="fbInput" autocomplete="off" />
                        <InputError :message="form.errors.cmr_waybill" />
                    </div>
                    <div>
                        <label :class="fbLabel" for="land-car-edit-consignee">{{ t('land_trips.consignee') }}</label>
                        <input id="land-car-edit-consignee" v-model="form.consignee_name" type="text" maxlength="180" :class="fbInput" autocomplete="off" />
                        <InputError :message="form.errors.consignee_name" />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 mb-3">
                    <div>
                        <label :class="fbLabel" for="land-car-edit-price">{{ t('land_trips.car_price') }}</label>
                        <input
                            id="land-car-edit-price"
                            v-model="form.price"
                            type="number"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            :class="fbInput"
                        />
                        <InputError :message="form.errors.price" />
                    </div>
                    <div>
                        <label :class="fbLabel" for="land-car-edit-location">{{ t('land_trips.location_status') }}</label>
                        <select id="land-car-edit-location" v-model="form.location_status_id" :class="fbInput">
                            <option value="">{{ t('land_trips.unspecified_location') }}</option>
                            <option v-for="status in carStatuses" :key="status.id" :value="status.id">
                                {{ stationLabel(status) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.location_status_id" />
                    </div>
                </div>

                <div class="mb-4">
                    <label :class="fbLabel" for="land-car-edit-notes">{{ t('common.notes') }}</label>
                    <textarea id="land-car-edit-notes" v-model="form.notes" rows="2" maxlength="1000" :class="fbInput" />
                    <InputError :message="form.errors.notes || form.errors.car" />
                </div>

                <div role="alert" aria-live="polite" class="visually-hidden">
                    <span v-if="Object.keys(form.errors).length">{{ t('land_trips.edit_car_error') }}</span>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" :class="fbGhostButton" class="cursor-pointer" @click="emit('close')">
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :class="[fbButton, '!w-auto min-w-40 cursor-pointer']"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? t('common.saving') : t('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
