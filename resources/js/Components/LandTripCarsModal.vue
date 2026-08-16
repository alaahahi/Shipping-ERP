<script setup>
import { useLandTripStation } from '@/composables/useLandTripStation';
import { statusRowStyle } from '@/composables/useLandTripStatusColor';
import { sanitizeChassisNumber } from '@/composables/useChassisLetterO';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    cars: { type: Array, default: () => [] },
    voyageCars: { type: Array, default: () => [] },
    carStatuses: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
    addOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'save', 'update:cars']);

const { t } = useI18n();
const { stationLabel } = useLandTripStation();

const emptyRow = () => ({
    voyage_car_id: null,
    chassis_no: '',
    cmr_waybill: '',
    consignee_name: '',
    model: '',
    color: '',
    description: '',
    weight: '',
    notes: '',
    price: 0,
    location_status_id: props.carStatuses[0]?.id ?? '',
});

const integerPrice = (value) => {
    if (value === '' || value === null || value === undefined) {
        return 0;
    }
    const n = Number(value);
    return Number.isFinite(n) ? Math.round(n) : 0;
};

const rowStyle = (row) => {
    const status = props.carStatuses.find((item) => String(item.id) === String(row.location_status_id));
    return statusRowStyle(status?.color);
};

const addRow = () => {
    emit('update:cars', [...props.cars, emptyRow()]);
};

const removeRow = (index) => {
    emit('update:cars', props.cars.filter((_, i) => i !== index));
};

const updateRow = (index, key, value) => {
    const nextValue = key === 'chassis_no' ? sanitizeChassisNumber(value) : value;
    const next = props.cars.map((row, i) => {
        if (i !== index) {
            return row;
        }

        const patch = { ...row, [key]: nextValue };
        if (key === 'model') {
            patch.description = nextValue;
        }

        return patch;
    });
    emit('update:cars', next);
};

const applyVoyageCar = (index, voyageCarId) => {
    const selected = props.voyageCars.find((car) => String(car.id) === String(voyageCarId));
    if (!selected) {
        updateRow(index, 'voyage_car_id', null);
        return;
    }

    const next = props.cars.map((row, i) => {
        if (i !== index) return row;
        return {
            ...row,
            voyage_car_id: selected.id,
            chassis_no: sanitizeChassisNumber(selected.chassis_no ?? ''),
            consignee_name: selected.consignee_name ?? '',
            model: selected.model || selected.description || '',
            color: selected.color ?? '',
            description: selected.description || selected.model || '',
            weight: selected.weight ?? '',
            price: integerPrice(row.price),
        };
    });
    emit('update:cars', next);
};

const fillFromVoyage = () => {
    if (props.voyageCars.length === 0) return;
    emit(
        'update:cars',
        props.voyageCars.map((car) => ({
            voyage_car_id: car.id,
            chassis_no: sanitizeChassisNumber(car.chassis_no ?? ''),
            cmr_waybill: '',
            consignee_name: car.consignee_name ?? '',
            model: car.model || car.description || '',
            color: car.color ?? '',
            description: car.description || car.model || '',
            weight: car.weight ?? '',
            notes: '',
            price: 0,
            location_status_id: props.carStatuses[0]?.id ?? '',
        })),
    );
};
</script>

<template>
    <div v-if="show" class="erp-modal-backdrop" @click.self="emit('close')">
        <div class="erp-modal-dialog erp-card p-0 overflow-hidden" role="dialog" aria-modal="true">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <div>
                    <h3 class="h5 erp-display mb-0">{{ addOnly ? t('land_trips.add_cars') : t('land_trips.cars_modal_title') }}</h3>
                    <p class="small text-secondary mb-0">{{ addOnly ? t('land_trips.add_cars_help') : t('land_trips.cars_modal_help') }}</p>
                </div>
                <button type="button" class="btn btn-erp-ghost" @click="emit('close')">{{ t('common.cancel') }}</button>
            </div>

            <div class="p-3 d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-erp" @click="addRow">{{ t('land_trips.add_row') }}</button>
                <button
                    v-if="voyageCars.length"
                    type="button"
                    class="btn btn-erp-ghost"
                    @click="fillFromVoyage"
                >
                    {{ t('land_trips.fill_from_voyage') }}
                </button>
            </div>

            <div class="table-responsive" style="max-height: 55vh">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th v-if="voyageCars.length" class="ps-3">{{ t('land_trips.voyage_car') }}</th>
                            <th :class="{ 'ps-3': !voyageCars.length }">{{ t('land_trips.chassis') }}</th>
                            <th>{{ t('land_trips.model') }}</th>
                            <th>{{ t('land_trips.color') }}</th>
                            <th>{{ t('land_trips.cmr_waybill') }}</th>
                            <th>{{ t('land_trips.consignee') }}</th>
                            <th>{{ t('land_trips.location_status') }}</th>
                            <th>{{ t('land_trips.car_price') }}</th>
                            <th>{{ t('land_trips.weight') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="cars.length === 0">
                            <td :colspan="voyageCars.length ? 10 : 9" class="text-center text-secondary py-4">
                                {{ t('land_trips.no_car_rows') }}
                            </td>
                        </tr>
                        <tr
                            v-for="(row, index) in cars"
                            :key="index"
                            class="land-status-colored"
                            :style="rowStyle(row)"
                        >
                            <td v-if="voyageCars.length" class="ps-3" style="min-width: 180px">
                                <select
                                    class="form-select form-erp-control form-select-sm"
                                    :value="row.voyage_car_id ?? ''"
                                    @change="applyVoyageCar(index, $event.target.value)"
                                >
                                    <option value="">{{ t('land_trips.unspecified_location') }}</option>
                                    <option v-for="car in voyageCars" :key="car.id" :value="car.id">
                                        {{ car.label }}
                                    </option>
                                </select>
                            </td>
                            <td :class="{ 'ps-3': !voyageCars.length }" style="min-width: 150px">
                                <input
                                    class="form-control form-erp-control form-control-sm font-monospace"
                                    :value="row.chassis_no"
                                    @input="updateRow(index, 'chassis_no', $event.target.value)"
                                />
                            </td>
                            <td style="min-width: 140px">
                                <input
                                    class="form-control form-erp-control form-control-sm"
                                    :value="row.model || row.description"
                                    @input="updateRow(index, 'model', $event.target.value)"
                                />
                            </td>
                            <td style="min-width: 110px">
                                <input
                                    class="form-control form-erp-control form-control-sm"
                                    :value="row.color"
                                    @input="updateRow(index, 'color', $event.target.value)"
                                />
                            </td>
                            <td style="min-width: 120px">
                                <input
                                    class="form-control form-erp-control form-control-sm"
                                    :value="row.cmr_waybill"
                                    @input="updateRow(index, 'cmr_waybill', $event.target.value)"
                                />
                            </td>
                            <td style="min-width: 160px">
                                <input
                                    class="form-control form-erp-control form-control-sm"
                                    :value="row.consignee_name"
                                    @input="updateRow(index, 'consignee_name', $event.target.value)"
                                />
                            </td>
                            <td style="min-width: 180px">
                                <select
                                    class="form-select form-erp-control form-select-sm"
                                    :value="row.location_status_id ?? ''"
                                    @change="updateRow(index, 'location_status_id', $event.target.value)"
                                >
                                    <option value="">{{ t('land_trips.unspecified_location') }}</option>
                                    <option v-for="status in carStatuses" :key="status.id" :value="status.id">
                                        {{ stationLabel(status) }}
                                    </option>
                                </select>
                            </td>
                            <td style="min-width: 110px">
                                <input
                                    type="number"
                                    step="1"
                                    min="0"
                                    inputmode="numeric"
                                    class="form-control form-erp-control form-control-sm land-hub-price-input"
                                    :value="integerPrice(row.price)"
                                    @change="updateRow(index, 'price', integerPrice($event.target.value))"
                                />
                            </td>
                            <td style="min-width: 90px">
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    class="form-control form-erp-control form-control-sm"
                                    :value="row.weight"
                                    @input="updateRow(index, 'weight', $event.target.value)"
                                />
                            </td>
                            <td class="pe-3">
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeRow(index)">
                                    {{ t('common.delete') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="erp-form-actions p-3 border-top">
                <button type="button" class="btn btn-erp-ghost" @click="emit('close')">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-erp" :disabled="processing" @click="emit('save')">
                    {{ processing ? t('common.saving') : (addOnly ? t('land_trips.add_cars') : t('land_trips.save_cars')) }}
                </button>
            </div>
        </div>
    </div>
</template>
