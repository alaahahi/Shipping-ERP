<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useLandTripStation } from '@/composables/useLandTripStation';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    trip: { type: Object, required: true },
    preview: { type: Object, default: null },
});

const page = usePage();
const { t } = useI18n();
const { stationLabel } = useLandTripStation();
const success = computed(() => page.props.flash?.success);
const fileInput = ref(null);

const uploadForm = useForm({
    file: null,
});

const onFileChange = (event) => {
    uploadForm.file = event.target.files?.[0] ?? null;
};

const submitPreview = () => {
    uploadForm.post(route('land-trips.import.preview', props.trip.id), { forceFormData: true });
};

const submitConfirm = () => {
    router.post(route('land-trips.import.confirm', props.trip.id));
};

const statusTone = (row) => {
    if (row.status !== 'ready') return 'neutral';
    if (!row.location_status_id) return 'warning';
    return 'success';
};
</script>

<template>
    <Head :title="t('land_trips.import')" />
    <AppLayout>
        <template #header>{{ t('land_trips.import') }}</template>
        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link
                :href="route('land-trips.companies.show', trip.company_id)"
                class="text-decoration-none small fw-semibold"
            >
                ← {{ t('land_trips.back_company') }}
            </Link>
        </div>

        <PageHeader :title="t('land_trips.import')" :subtitle="t('land_trips.import_help')" />

        <form class="erp-form-panel mb-3" @submit.prevent="submitPreview">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-erp-label">{{ t('land_trips.excel_file') }}</label>
                    <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="form-control form-erp-control" @change="onFileChange" />
                    <InputError :message="uploadForm.errors.file" />
                </div>
            </div>
            <div class="erp-form-actions">
                <button type="submit" class="btn btn-erp" :disabled="uploadForm.processing || !uploadForm.file">
                    {{ uploadForm.processing ? t('common.saving') : t('land_trips.preview') }}
                </button>
            </div>
        </form>

        <div v-if="preview" class="erp-card p-0 overflow-hidden">
            <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="erp-panel-title mb-0">{{ t('land_trips.preview_title') }}</h3>
                    <p class="small text-secondary mb-0">
                        {{ preview.original_name }}
                        <span v-if="preview.default_consignee"> · {{ preview.default_consignee }}</span>
                        · {{ t('land_trips.preview_stats', {
                            ready: preview.ready,
                            skipped: preview.skipped,
                            unmatched: preview.unmatched_status,
                        }) }}
                    </p>
                </div>
                <form @submit.prevent="submitConfirm">
                    <button type="submit" class="btn btn-erp" :disabled="preview.ready < 1">
                        {{ t('land_trips.confirm_import') }}
                    </button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>{{ t('common.status') }}</th>
                            <th>{{ t('land_trips.chassis') }}</th>
                            <th>{{ t('land_trips.cmr_waybill') }}</th>
                            <th>{{ t('common.description') }}</th>
                            <th>{{ t('land_trips.location_status') }}</th>
                            <th class="pe-4">{{ t('land_trips.consignee') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in preview.rows"
                            :key="`${row.row_number}-${row.chassis_no}`"
                            :class="row.location_status_tone === 'yellow' ? 'land-car-row-yellow' : row.location_status_tone === 'green' ? 'land-car-row-green' : ''"
                        >
                            <td class="ps-4 text-secondary">{{ row.row_number }}</td>
                            <td>
                                <StatusBadge :tone="statusTone(row)" :label="row.reason || row.status" />
                            </td>
                            <td class="font-monospace">{{ row.chassis_no || '—' }}</td>
                            <td>{{ row.cmr_waybill || '—' }}</td>
                            <td>{{ row.description || '—' }}</td>
                            <td>{{ stationLabel(row) }}</td>
                            <td class="pe-4">{{ row.consignee_name || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
