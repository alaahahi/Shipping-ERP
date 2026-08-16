<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { fbButton, fbGhostButton, fbInput, fbLabel, fbLink } from '@/flowbite';
import { useLandTripStation } from '@/composables/useLandTripStation';
import { sanitizeChassisNumber } from '@/composables/useChassisLetterO';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    trip: { type: Object, required: true },
    preview: { type: Object, default: null },
    carStatuses: { type: Array, default: () => [] },
});

const page = usePage();
const { t, te } = useI18n();
const { stationLabel } = useLandTripStation();
const success = computed(() => page.props.flash?.success);
const fileInput = ref(null);
let nextUid = 1;

const uploadForm = useForm({
    file: null,
});

const confirmForm = useForm({
    rows: [],
});

const resetForm = useForm({});

const rows = ref([]);

const cleanChassis = (value) => sanitizeChassisNumber(value);
const cleanCmr = (value) => String(value || '')
    .replace(/[^A-Za-z0-9\s\-\/]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
const cleanDescription = (value) => String(value || '')
    .replace(/[^A-Za-z\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
const cleanColor = (value) => String(value || '')
    .replace(/[^A-Za-z\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF\s\-]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const clonePreviewRow = (row) => ({
    uid: nextUid++,
    row_number: row.row_number ?? rows.value.length + 1,
    chassis_no: cleanChassis(row.chassis_no),
    cmr_waybill: cleanCmr(row.cmr_waybill),
    model: cleanDescription(row.model || row.description),
    color: cleanColor(row.color),
    year: row.year ?? '',
    notes: String(row.notes || '').trim().slice(0, 1000),
    description: cleanDescription(row.model || row.description),
    consignee_name: row.consignee_name ?? props.preview?.default_consignee ?? '',
    status_text: row.status_text ?? '',
    location_status_id: row.location_status_id != null && row.location_status_id !== ''
        ? String(row.location_status_id)
        : '',
});

const restorePreviewRows = () => {
    nextUid = 1;
    rows.value = (props.preview?.rows ?? [])
        .filter((row) => cleanChassis(row.chassis_no))
        .map((row) => clonePreviewRow(row));
};

watch(
    () => props.preview,
    () => {
        restorePreviewRows();
    },
    { immediate: true },
);

const occupiedSet = computed(() => new Set(
    (props.preview?.occupied_chassis ?? []).map((item) => cleanChassis(item)).filter(Boolean),
));

const companyChassisSet = computed(() => new Set(
    (props.preview?.company_chassis ?? []).map((item) => cleanChassis(item)).filter(Boolean),
));

const inspectRow = (row, seen) => {
    const chassis = cleanChassis(row.chassis_no);
    const model = cleanDescription(row.model || row.description);
    const color = cleanColor(row.color);
    const description = model;
    const cmrWaybill = cleanCmr(row.cmr_waybill);
    let reasonCode = null;

    if (!chassis) {
        reasonCode = 'missing_chassis';
    } else if (occupiedSet.value.has(chassis)) {
        reasonCode = 'chassis_used';
    } else if (seen.has(chassis)) {
        reasonCode = 'duplicate_in_file';
    } else if (model.length > 180 || description.length > 255) {
        reasonCode = 'description_too_long';
    } else if (companyChassisSet.value.has(chassis)) {
        reasonCode = 'already_in_company';
    } else if (chassis.length !== 17) {
        reasonCode = 'invalid_chassis';
    }

    if (chassis && reasonCode !== 'chassis_used' && reasonCode !== 'duplicate_in_file') {
        seen.add(chassis);
    }

    const blocking = ['missing_chassis', 'chassis_used', 'duplicate_in_file', 'description_too_long'].includes(reasonCode);
    const status = props.carStatuses.find((item) => String(item.id) === String(row.location_status_id || ''));

    return {
        ...row,
        chassis_no: chassis,
        cmr_waybill: cmrWaybill,
        model,
        color,
        description,
        status: chassis && !blocking ? 'ready' : 'skipped',
        reason_code: reasonCode,
        location_status_code: status?.code ?? null,
        location_status_label: status?.label ?? null,
        location_status_tone: status?.row_tone ?? null,
    };
};

const inspectedRows = computed(() => {
    const seen = new Set();

    return rows.value.map((row) => inspectRow(row, seen));
});

/** Hide non-empty chassis with length ≤ 16 (not a full VIN); empty add-row chassis stays visible. */
const isShortChassisPreviewRow = (row) => {
    const chassis = cleanChassis(row?.chassis_no);
    return chassis.length > 0 && chassis.length <= 16;
};

const visibleRowEntries = computed(() => rows.value
    .map((row, index) => ({
        row,
        index,
        inspected: inspectedRows.value[index],
    }))
    .filter(({ row }) => !isShortChassisPreviewRow(row)));

const visibleInspectedRows = computed(() => visibleRowEntries.value.map((entry) => entry.inspected));

const readyCount = computed(() => visibleInspectedRows.value.filter((row) => row.status === 'ready').length);
const skippedCount = computed(() => visibleInspectedRows.value.length - readyCount.value);
const invalidCount = computed(() => visibleInspectedRows.value.filter((row) => row.reason_code === 'invalid_chassis').length);

const reasonLabel = (row) => {
    if (row.reason_code) {
        const key = `land_trips.import_reason.${row.reason_code}`;

        return te(key) ? t(key) : row.reason_code;
    }

    if (row.status === 'ready') {
        return t('land_trips.import_ready');
    }

    return row.status;
};

const statusTone = (row) => {
    if (row.reason_code === 'chassis_used' || row.reason_code === 'missing_chassis' || row.reason_code === 'invalid_chassis') {
        return 'danger';
    }
    if (row.reason_code === 'already_in_company') {
        return 'info';
    }
    if (row.reason_code === 'duplicate_in_file' || row.status !== 'ready') {
        return 'warning';
    }
    if (!row.location_status_id) {
        return 'warning';
    }

    return 'success';
};

const rowClass = (row) => {
    if (row.reason_code === 'chassis_used' || row.reason_code === 'invalid_chassis' || row.reason_code === 'missing_chassis') {
        return 'land-trip-import-row-invalid';
    }
    if (row.reason_code === 'already_in_company') {
        return 'land-trip-import-row-info';
    }
    if (row.reason_code === 'duplicate_in_file' || row.status !== 'ready') {
        return 'land-trip-import-row-warning';
    }
    if (row.location_status_tone === 'yellow') {
        return 'land-car-row-yellow';
    }
    if (row.location_status_tone === 'green') {
        return 'land-car-row-green';
    }

    return 'land-trip-import-row-ready';
};

const onFileChange = (event) => {
    uploadForm.file = event.target.files?.[0] ?? null;
};

const submitPreview = () => {
    uploadForm.post(route('land-trips.import.preview', props.trip.id), { forceFormData: true });
};

const addRow = () => {
    const lastNumber = rows.value.reduce((max, row) => Math.max(max, Number(row.row_number) || 0), 0);
    rows.value.unshift(clonePreviewRow({
        row_number: lastNumber + 1,
        consignee_name: props.preview?.default_consignee ?? '',
        location_status_id: props.carStatuses.find((item) => !item.is_archive)?.id
            ?? props.carStatuses[0]?.id
            ?? '',
    }));
};

const removeRow = (index) => {
    rows.value.splice(index, 1);
};

const sanitizeRowField = (index, field) => {
    const row = rows.value[index];
    if (!row) {
        return;
    }

    if (field === 'chassis_no') {
        row.chassis_no = cleanChassis(row.chassis_no);
    }

    if (field === 'cmr_waybill') {
        row.cmr_waybill = cleanCmr(row.cmr_waybill);
    }

    if (field === 'description' || field === 'model') {
        row.model = cleanDescription(row.model || row.description);
        row.description = row.model;
    }

    if (field === 'color') {
        row.color = cleanColor(row.color);
    }
};

const resetPreview = () => {
    nextUid = 1;
    rows.value = [];
    uploadForm.file = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    resetForm.post(route('land-trips.import.reset', props.trip.id));
};

const submitConfirm = () => {
    confirmForm.rows = rows.value
        .map((row, index) => ({
            row_number: Number(row.row_number) || index + 1,
            chassis_no: cleanChassis(row.chassis_no) || null,
            cmr_waybill: cleanCmr(row.cmr_waybill) || null,
            model: cleanDescription(row.model || row.description) || null,
            color: cleanColor(row.color) || null,
            year: row.year === '' || row.year == null ? null : Number(row.year),
            notes: String(row.notes || '').trim().slice(0, 1000) || null,
            description: cleanDescription(row.model || row.description) || null,
            consignee_name: row.consignee_name || null,
            status_text: row.status_text || null,
            location_status_id: row.location_status_id || null,
        }))
        .filter((row) => {
            const chassis = row.chassis_no;
            // Skip empty and short chassis (≤16); backend still re-evaluates and only imports ready rows.
            return Boolean(chassis) && chassis.length > 16;
        });
    confirmForm.post(route('land-trips.import.confirm', props.trip.id));
};
</script>

<template>
    <Head :title="t('land_trips.import')" />
    <AppLayout>
        <template #header>{{ t('land_trips.import') }}</template>
        <FlashMessage :message="success" />

        <div class="land-trip-import mx-auto w-full max-w-none">
            <div class="mb-3">
                <Link
                    :href="route('land-trips.companies.show', trip.company_id)"
                    :class="fbLink"
                >
                    ← {{ t('land_trips.back_company') }}
                </Link>
            </div>

            <PageHeader :title="t('land_trips.import')" :subtitle="t('land_trips.import_help')" />

            <form
                class="land-trip-import-panel land-trip-import-upload mb-4 w-full max-w-2xl rounded-lg border border-gray-200 p-4 shadow-sm transition-shadow duration-200 hover:shadow-md dark:border-slate-700 dark:shadow-none dark:hover:shadow-none"
                @submit.prevent="submitPreview"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label :class="fbLabel" for="land-trip-excel-file">{{ t('land_trips.excel_file') }}</label>
                        <input
                            id="land-trip-excel-file"
                            ref="fileInput"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            :class="[fbInput, 'land-trip-import-file']"
                            @change="onFileChange"
                        />
                        <InputError :message="uploadForm.errors.file" />
                    </div>
                    <button
                        type="submit"
                        :class="fbButton"
                        class="!w-full shrink-0 sm:!w-auto sm:min-w-28"
                        :disabled="uploadForm.processing || !uploadForm.file"
                    >
                        {{ uploadForm.processing ? t('common.saving') : t('land_trips.preview') }}
                    </button>
                </div>
            </form>

            <div v-if="preview" class="land-trip-import-panel overflow-hidden rounded-lg border border-gray-200 dark:border-slate-700">
                <div class="land-trip-import-toolbar flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 p-4 dark:border-slate-700">
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('land_trips.preview_title') }}</h3>
                        <p class="land-trip-import-filename mt-1 text-sm text-gray-700 dark:text-gray-200">
                            {{ preview.original_name }}
                            <span v-if="preview.default_consignee"> · {{ preview.default_consignee }}</span>
                            · {{ t('land_trips.preview_stats', {
                                ready: readyCount,
                                skipped: skippedCount,
                                unmatched: preview.unmatched_status,
                                invalid: invalidCount,
                            }) }}
                        </p>
                        <InputError :message="confirmForm.errors.rows" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" :class="fbGhostButton" class="land-trip-import-ghost" @click="addRow">
                            {{ t('land_trips.add_row') }}
                        </button>
                        <button
                            type="button"
                            :class="fbGhostButton"
                            class="land-trip-import-ghost"
                            :disabled="resetForm.processing"
                            @click="resetPreview"
                        >
                            {{ t('land_trips.reset_preview') }}
                        </button>
                        <button
                            type="button"
                            :class="fbButton"
                            class="!w-auto"
                            :disabled="confirmForm.processing || readyCount < 1"
                            @click="submitConfirm"
                        >
                            {{ confirmForm.processing ? t('common.saving') : t('land_trips.confirm_import') }}
                        </button>
                    </div>
                </div>

                <div class="land-trip-import-table-wrap max-h-[70vh] overflow-auto">
                    <table class="land-trip-import-table w-full min-w-full text-start text-gray-700 dark:text-gray-200">
                        <thead class="sticky top-0 z-10 bg-gray-50 uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                            <tr>
                                <th>#</th>
                                <th>{{ t('common.status') }}</th>
                                <th class="min-w-40">{{ t('land_trips.chassis') }}</th>
                                <th class="min-w-28">{{ t('land_trips.cmr_waybill') }}</th>
                                <th class="min-w-32">{{ t('land_trips.model') }}</th>
                                <th class="min-w-24">{{ t('land_trips.color') }}</th>
                                <th class="min-w-20">{{ t('land_trips.year') }}</th>
                                <th class="min-w-36">{{ t('land_trips.location_status') }}</th>
                                <th class="min-w-28">{{ t('land_trips.consignee') }}</th>
                                <th class="min-w-28">{{ t('common.notes') }}</th>
                                <th>{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in visibleRowEntries"
                                :key="entry.row.uid"
                                :class="rowClass(entry.inspected || entry.row)"
                            >
                                <td class="text-gray-500 dark:text-gray-400">{{ entry.row.row_number }}</td>
                                <td class="land-trip-import-status">
                                    <span
                                        class="land-trip-import-status-wrap"
                                        :title="reasonLabel(entry.inspected || entry.row)"
                                    >
                                        <StatusBadge
                                            :tone="statusTone(entry.inspected || entry.row)"
                                            :label="reasonLabel(entry.inspected || entry.row)"
                                        />
                                    </span>
                                </td>
                                <td>
                                    <input
                                        v-model="entry.row.chassis_no"
                                        type="text"
                                        class="land-trip-import-input font-mono"
                                        autocomplete="off"
                                        @blur="sanitizeRowField(entry.index, 'chassis_no')"
                                    />
                                </td>
                                <td>
                                    <input
                                        v-model="entry.row.cmr_waybill"
                                        type="text"
                                        class="land-trip-import-input"
                                        autocomplete="off"
                                        @blur="sanitizeRowField(entry.index, 'cmr_waybill')"
                                    />
                                </td>
                                <td>
                                    <input
                                        v-model="entry.row.model"
                                        type="text"
                                        class="land-trip-import-input"
                                        autocomplete="off"
                                        @blur="sanitizeRowField(entry.index, 'model')"
                                    />
                                </td>
                                <td>
                                    <input
                                        v-model="entry.row.color"
                                        type="text"
                                        class="land-trip-import-input"
                                        autocomplete="off"
                                        @blur="sanitizeRowField(entry.index, 'color')"
                                    />
                                </td>
                                <td>
                                    <input
                                        v-model="entry.row.year"
                                        type="number"
                                        min="1980"
                                        max="2100"
                                        step="1"
                                        inputmode="numeric"
                                        class="land-trip-import-input land-trip-import-year"
                                    />
                                </td>
                                <td>
                                    <select v-model="entry.row.location_status_id" class="land-trip-import-input land-trip-import-select">
                                        <option value="">{{ t('land_trips.unspecified_location') }}</option>
                                        <option v-for="status in carStatuses" :key="status.id" :value="String(status.id)">
                                            {{ stationLabel(status) }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input v-model="entry.row.consignee_name" type="text" class="land-trip-import-input" autocomplete="off" />
                                </td>
                                <td>
                                    <input
                                        v-model="entry.row.notes"
                                        type="text"
                                        class="land-trip-import-input"
                                        maxlength="1000"
                                        autocomplete="off"
                                    />
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        :class="fbGhostButton"
                                        class="land-trip-import-ghost land-trip-import-delete"
                                        @click="removeRow(entry.index)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
