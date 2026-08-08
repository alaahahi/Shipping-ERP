<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    borders: { type: Array, default: () => [] },
    preview: { type: Object, default: null },
    defaults: { type: Object, default: () => ({}) },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const fileInput = ref(null);

const uploadForm = useForm({
    file: null,
    company_id: props.defaults.company_id || props.companies[0]?.id || '',
    border: props.defaults.border || '',
    sale_state: props.defaults.sale_state || 'unsold',
});

const confirmForm = useForm({
    company_id: props.defaults.company_id || props.companies[0]?.id || '',
    border: props.defaults.border || '',
    sale_state: props.defaults.sale_state || 'unsold',
});

const onFileChange = (event) => {
    uploadForm.file = event.target.files?.[0] ?? null;
};

const submitPreview = () => {
    uploadForm.post(route('iran-cars.import.preview'), { forceFormData: true });
};

const submitConfirm = () => {
    confirmForm.post(route('iran-cars.import.confirm'));
};

const statusTone = (status) => {
    if (status === 'ready') return 'success';
    if (status === 'duplicate') return 'warning';
    return 'neutral';
};
</script>

<template>
    <Head :title="t('iran_cars.import')" />
    <AppLayout>
        <template #header>{{ t('iran_cars.import') }}</template>
        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link
                :href="route('iran-cars.index', { sale_state: uploadForm.sale_state })"
                class="text-decoration-none small fw-semibold"
            >
                ← {{ t('iran_cars.back') }}
            </Link>
        </div>

        <PageHeader :title="t('iran_cars.import')" :subtitle="t('iran_cars.import_help')" />

        <form class="erp-form-panel mb-3" @submit.prevent="submitPreview">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('iran_cars.excel_file') }}</label>
                    <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="form-control form-erp-control" @change="onFileChange" />
                    <InputError :message="uploadForm.errors.file" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('iran_cars.import_as') }}</label>
                    <select v-model="uploadForm.sale_state" class="form-select form-erp-control">
                        <option value="unsold">{{ t('iran_cars.unsold') }}</option>
                        <option value="sold">{{ t('iran_cars.sold') }}</option>
                    </select>
                    <InputError :message="uploadForm.errors.sale_state" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('iran_cars.default_company') }}</label>
                    <select v-model="uploadForm.company_id" class="form-select form-erp-control">
                        <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-erp-label">{{ t('iran_cars.default_border') }}</label>
                    <select v-model="uploadForm.border" class="form-select form-erp-control">
                        <option value="">{{ t('iran_cars.from_excel') }}</option>
                        <option v-for="border in borders" :key="border.value" :value="border.value">{{ border.label }}</option>
                    </select>
                </div>
            </div>
            <div class="erp-form-actions">
                <button type="submit" class="btn btn-erp" :disabled="uploadForm.processing || !uploadForm.file">
                    {{ uploadForm.processing ? t('common.saving') : t('iran_cars.preview') }}
                </button>
            </div>
        </form>

        <div v-if="preview" class="erp-card p-0 overflow-hidden">
            <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="erp-panel-title mb-0">{{ t('iran_cars.preview_title') }}</h3>
                    <p class="small text-secondary mb-0">
                        {{ preview.original_name }} · {{ t('iran_cars.preview_stats', {
                            ready: preview.ready,
                            duplicates: preview.duplicates,
                            skipped: preview.skipped,
                        }) }}
                    </p>
                </div>
                <form class="d-flex flex-wrap gap-2 align-items-end" @submit.prevent="submitConfirm">
                    <div>
                        <label class="form-erp-label">{{ t('iran_cars.import_as') }}</label>
                        <select v-model="confirmForm.sale_state" class="form-select form-erp-control">
                            <option value="unsold">{{ t('iran_cars.unsold') }}</option>
                            <option value="sold">{{ t('iran_cars.sold') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-erp-label">{{ t('iran_cars.default_company') }}</label>
                        <select v-model="confirmForm.company_id" class="form-select form-erp-control">
                            <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.label }}</option>
                        </select>
                        <InputError :message="confirmForm.errors.company_id" />
                    </div>
                    <div>
                        <label class="form-erp-label">{{ t('iran_cars.default_border') }}</label>
                        <select v-model="confirmForm.border" class="form-select form-erp-control">
                            <option value="">{{ t('iran_cars.from_excel') }}</option>
                            <option v-for="border in borders" :key="border.value" :value="border.value">{{ border.label }}</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-erp" :disabled="confirmForm.processing || preview.ready < 1">
                        {{ confirmForm.processing ? t('common.saving') : t('iran_cars.confirm_import') }}
                    </button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>{{ t('common.status') }}</th>
                            <th>{{ t('iran_cars.border') }}</th>
                            <th>{{ t('iran_cars.model') }}</th>
                            <th>{{ t('iran_cars.year') }}</th>
                            <th>{{ t('iran_cars.color') }}</th>
                            <th>{{ t('iran_cars.vin') }}</th>
                            <th class="text-end pe-4">{{ t('iran_cars.price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in preview.rows" :key="`${row.row_number}-${row.vin}`">
                            <td class="ps-4 text-secondary">{{ row.row_number }}</td>
                            <td>
                                <StatusBadge :tone="statusTone(row.status)" :label="row.reason || row.status" />
                            </td>
                            <td>{{ row.border_label || '—' }}</td>
                            <td>{{ row.model_name || '—' }}</td>
                            <td>{{ row.year || '—' }}</td>
                            <td>{{ row.color || '—' }}</td>
                            <td class="font-monospace">{{ row.vin || '—' }}</td>
                            <td class="text-end pe-4">
                                <MoneyAmount :value="row.total_amount" currency="USD" show-zero />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
