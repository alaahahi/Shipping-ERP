<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReportsNav from '@/Components/ReportsNav.vue';
import { sanitizeChassisNumber } from '@/composables/useChassisLetterO';
import { fbButton, fbCheckbox, fbGhostButton, fbInput, fbLabel } from '@/flowbite';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    cars: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ countries: [], locations: [] }) },
    scoped: { type: Boolean, default: false },
    missingChassis: { type: Array, default: () => [] },
    duplicateChassis: { type: Array, default: () => [] },
});

const { t } = useI18n();
const pendingRawChassis = ref('');
const localDuplicates = ref([]);
const syncingChassis = ref(false);

const filterForm = useForm({
    country_ids: [...(props.filters.country_ids ?? [])].map((id) => String(id)),
    location_status_ids: [...(props.filters.location_status_ids ?? [])].map((id) => String(id)),
    chassis_text: props.filters.chassis_text ?? '',
    duplicate_chassis: [...(props.duplicateChassis ?? [])],
});

const selectedCount = computed(() => (
    filterForm.country_ids.length
    + filterForm.location_status_ids.length
    + (String(filterForm.chassis_text ?? '').trim() === '' ? 0 : 1)
));

const duplicateList = computed(() => {
    const fromServer = (props.duplicateChassis ?? []).map((vin) => String(vin));
    if (fromServer.length) {
        return fromServer;
    }

    return localDuplicates.value;
});

const inspectChassisPaste = (raw) => {
    const parts = String(raw ?? '').split(/[\r\n\t,;/]+/);
    const counts = {};
    const order = [];

    for (const part of parts) {
        const chassis = sanitizeChassisNumber(part).replace(/I/g, '1');
        if (!chassis) {
            continue;
        }
        if (counts[chassis] === undefined) {
            if (order.length >= 300) {
                continue;
            }
            order.push(chassis);
            counts[chassis] = 0;
        }
        counts[chassis] += 1;
    }

    return {
        text: order.join('\n'),
        duplicates: order.filter((vin) => counts[vin] > 1),
    };
};

const formatChassisLines = (raw, duplicates = []) => {
    const inspected = inspectChassisPaste(raw);
    const dupSet = new Set((duplicates.length ? duplicates : inspected.duplicates).map((vin) => String(vin)));

    return inspected.text
        .split('\n')
        .filter(Boolean)
        .map((vin) => (dupSet.has(vin) ? `⚠ ${vin}` : vin))
        .join('\n');
};

const applyCleanedChassis = (raw) => {
    const inspected = inspectChassisPaste(raw);
    syncingChassis.value = true;
    filterForm.chassis_text = formatChassisLines(inspected.text, inspected.duplicates);
    localDuplicates.value = inspected.duplicates;
    queueMicrotask(() => {
        syncingChassis.value = false;
    });

    return inspected;
};

const onChassisPaste = (event) => {
    event.preventDefault();
    const pasted = event.clipboardData?.getData('text') ?? '';
    const existing = String(filterForm.chassis_text ?? '').trim();
    const combined = existing === '' ? pasted : `${existing}\n${pasted}`;
    pendingRawChassis.value = combined;
    applyCleanedChassis(combined);
};

const onChassisInput = () => {
    if (syncingChassis.value) {
        return;
    }
    pendingRawChassis.value = '';
    localDuplicates.value = inspectChassisPaste(filterForm.chassis_text).duplicates;
};

const onChassisBlur = () => {
    if (!pendingRawChassis.value) {
        pendingRawChassis.value = filterForm.chassis_text;
    }
    applyCleanedChassis(pendingRawChassis.value || filterForm.chassis_text);
};

const toggleId = (field, id) => {
    const value = String(id);
    const current = filterForm[field];
    filterForm[field] = current.includes(value)
        ? current.filter((item) => item !== value)
        : [...current, value];
};

const isChecked = (field, id) => filterForm[field].includes(String(id));

const applyFilters = () => {
    const raw = pendingRawChassis.value || filterForm.chassis_text;
    const inspected = applyCleanedChassis(raw);
    filterForm.duplicate_chassis = inspected.duplicates;
    filterForm.get(route('reports.land-trips'), {
        preserveState: true,
        replace: true,
        onSuccess: () => {
            pendingRawChassis.value = '';
            filterForm.chassis_text = formatChassisLines(
                props.filters.chassis_text ?? inspected.text,
                props.duplicateChassis ?? inspected.duplicates,
            );
            filterForm.duplicate_chassis = [...(props.duplicateChassis ?? [])];
        },
    });
};

const resetFilters = () => {
    pendingRawChassis.value = '';
    localDuplicates.value = [];
    filterForm.country_ids = [];
    filterForm.location_status_ids = [];
    filterForm.chassis_text = '';
    filterForm.duplicate_chassis = [];
    filterForm.get(route('reports.land-trips'), { preserveState: true, replace: true });
};

const exportUrl = (name) => {
    const params = new URLSearchParams();
    filterForm.country_ids.forEach((id) => params.append('country_ids[]', id));
    filterForm.location_status_ids.forEach((id) => params.append('location_status_ids[]', id));
    const chassis = String(filterForm.chassis_text ?? '').trim();
    if (chassis !== '') {
        params.set('chassis_text', chassis);
    }
    duplicateList.value.forEach((vin) => params.append('duplicate_chassis[]', vin));
    const query = params.toString();

    return query ? `${route(name)}?${query}` : route(name);
};

const rowSerial = (index) => (Number(props.cars.from) || 1) + index;

const filteredChassisCount = computed(() => {
    const nos = props.filters.chassis_nos;
    if (Array.isArray(nos) && nos.length) {
        return nos.length;
    }

    const text = String(props.filters.chassis_text ?? '').trim();
    if (text === '') {
        return 0;
    }

    return inspectChassisPaste(text).text.split('\n').filter(Boolean).length;
});

const locationHint = (item) => {
    if (item.country_label) {
        return item.country_label;
    }

    return item.is_archive ? t('reports.archive_location') : '';
};
</script>

<template>
    <Head :title="t('reports.land_trips')" />
    <AppLayout>
        <template #header>{{ t('reports.land_trips') }}</template>

        <ReportsNav current="land-trips" />

        <PageHeader :kicker="t('nav.reports')" :title="t('reports.land_trips')" :subtitle="t('reports.land_trips_help')">
            <template #actions>
                <a
                    :href="scoped ? exportUrl('reports.land-trips.export.excel') : undefined"
                    :class="[fbGhostButton, (!scoped || filterForm.processing) && 'pointer-events-none opacity-50']"
                    :aria-disabled="!scoped"
                >
                    {{ t('reports.export_excel') }}
                </a>
                <a
                    :href="scoped ? exportUrl('reports.land-trips.export.pdf') : undefined"
                    :class="[fbButton, 'w-auto', (!scoped || filterForm.processing) && 'pointer-events-none opacity-50']"
                    :aria-disabled="!scoped"
                >
                    {{ t('reports.export_pdf') }}
                </a>
            </template>
        </PageHeader>

        <form class="erp-card p-4 mb-3" @submit.prevent="applyFilters">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">{{ t('reports.land_trips_filter_help') }}</p>
            <InputError :message="filterForm.errors.country_ids || filterForm.errors.location_status_ids || filterForm.errors.chassis_text" />

            <div class="grid gap-4 md:grid-cols-2">
                <fieldset>
                    <legend :class="fbLabel">{{ t('reports.countries') }}</legend>
                    <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/50">
                        <p v-if="!options.countries?.length" class="mb-0 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('reports.no_countries') }}
                        </p>
                        <label
                            v-for="country in options.countries"
                            :key="country.id"
                            class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white"
                        >
                            <input
                                :checked="isChecked('country_ids', country.id)"
                                :class="fbCheckbox"
                                type="checkbox"
                                @change="toggleId('country_ids', country.id)"
                            >
                            <span class="flex-1">{{ country.label }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ country.count }}</span>
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend :class="fbLabel">{{ t('reports.locations') }}</legend>
                    <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/50">
                        <p v-if="!options.locations?.length" class="mb-0 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('reports.no_locations') }}
                        </p>
                        <label
                            v-for="location in options.locations"
                            :key="location.id"
                            class="flex cursor-pointer items-center gap-2 text-sm text-gray-900 dark:text-white"
                        >
                            <input
                                :checked="isChecked('location_status_ids', location.id)"
                                :class="fbCheckbox"
                                type="checkbox"
                                @change="toggleId('location_status_ids', location.id)"
                            >
                            <span class="flex-1">
                                {{ location.label }}
                                <span v-if="locationHint(location)" class="ms-1 text-xs text-gray-500 dark:text-gray-400">
                                    · {{ locationHint(location) }}
                                </span>
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ location.count }}</span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <div class="mt-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <label :class="[fbLabel, 'mb-0']" for="land-report-chassis">{{ t('reports.chassis_paste') }}</label>
                    <span
                        v-if="filteredChassisCount > 0"
                        class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-900/40 dark:text-teal-200"
                    >
                        {{ t('reports.chassis_count', { count: filteredChassisCount }) }}
                    </span>
                </div>
                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">{{ t('reports.chassis_paste_help') }}</p>
                <textarea
                    id="land-report-chassis"
                    v-model="filterForm.chassis_text"
                    :class="fbInput"
                    rows="6"
                    :placeholder="t('reports.chassis_paste_placeholder')"
                    @paste="onChassisPaste"
                    @input="onChassisInput"
                    @blur="onChassisBlur"
                />
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button :class="[fbButton, 'w-auto']" :disabled="filterForm.processing" type="submit">
                    {{ t('common.filter') }}
                </button>
                <button
                    :class="[fbGhostButton, 'w-auto']"
                    :disabled="filterForm.processing || selectedCount === 0"
                    type="button"
                    @click="resetFilters"
                >
                    {{ t('common.reset') }}
                </button>
            </div>
        </form>

        <p
            v-if="missingChassis.length"
            class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
        >
            {{ t('reports.chassis_missing', { count: missingChassis.length }) }}
        </p>

        <div class="erp-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table erp-table land-report-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('land_trips.sequence') }}</th>
                            <th>{{ t('companies.name') }}</th>
                            <th>{{ t('reports.country') }}</th>
                            <th>{{ t('reports.location') }}</th>
                            <th>{{ t('land_trips.chassis') }}</th>
                            <th>{{ t('land_trips.model') }}</th>
                            <th>{{ t('land_trips.consignee') }}</th>
                            <th class="text-end pe-4">{{ t('land_trips.car_price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!scoped">
                            <td class="ps-4 pe-4" colspan="8">
                                <EmptyState icon="R">{{ t('reports.land_trips_pick') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-else-if="!cars.data.length">
                            <td class="ps-4 pe-4" colspan="8">
                                <EmptyState icon="R">{{ t('reports.land_trips_none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr
                            v-for="(car, index) in cars.data"
                            v-else
                            :key="car.id"
                        >
                            <td class="ps-4 font-monospace">{{ rowSerial(index) }}</td>
                            <td class="font-medium">{{ car.company_name || '—' }}</td>
                            <td>{{ car.country_label || '—' }}</td>
                            <td>{{ car.location_status_label || '—' }}</td>
                            <td class="font-monospace">{{ car.chassis_no || '—' }}</td>
                            <td>{{ car.model || '—' }}</td>
                            <td>{{ car.consignee_name || '—' }}</td>
                            <td class="text-end pe-4 font-monospace">{{ car.price }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="scoped && (cars.prev_page_url || cars.next_page_url)"
                class="d-flex justify-content-between align-items-center p-3 border-top"
            >
                <Link
                    v-if="cars.prev_page_url"
                    :href="cars.prev_page_url"
                    class="btn btn-sm btn-erp-ghost"
                    preserve-scroll
                >
                    {{ t('common.prev') }}
                </Link>
                <span v-else />
                <span class="small text-secondary">{{ cars.from }}–{{ cars.to }} / {{ cars.total }}</span>
                <Link
                    v-if="cars.next_page_url"
                    :href="cars.next_page_url"
                    class="btn btn-sm btn-erp-ghost"
                    preserve-scroll
                >
                    {{ t('common.next') }}
                </Link>
                <span v-else />
            </div>
        </div>

        <div v-if="duplicateList.length" class="erp-card mt-3 p-0 overflow-hidden">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h2 class="mb-0 text-base font-semibold text-gray-900 dark:text-white">
                    {{ t('reports.chassis_duplicate_title') }}
                    <span class="ms-2 text-sm font-normal text-gray-500 dark:text-gray-400">{{ duplicateList.length }}</span>
                </h2>
            </div>
            <div class="table-responsive">
                <table class="table erp-table land-report-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('land_trips.sequence') }}</th>
                            <th class="pe-4">{{ t('land_trips.chassis') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(chassis, index) in duplicateList"
                            :key="chassis"
                            class="land-report-dup"
                        >
                            <td class="ps-4 font-monospace">{{ index + 1 }}</td>
                            <td class="pe-4 font-monospace">
                                {{ chassis }}
                                <span class="ms-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                                    {{ t('reports.chassis_duplicate') }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="missingChassis.length" class="erp-card mt-3 p-0 overflow-hidden">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h2 class="mb-0 text-base font-semibold text-gray-900 dark:text-white">
                    {{ t('reports.chassis_not_found_title') }}
                    <span class="ms-2 text-sm font-normal text-gray-500 dark:text-gray-400">{{ missingChassis.length }}</span>
                </h2>
            </div>
            <div class="table-responsive">
                <table class="table erp-table land-report-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('land_trips.sequence') }}</th>
                            <th class="pe-4">{{ t('land_trips.chassis') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(chassis, index) in missingChassis"
                            :key="chassis"
                        >
                            <td class="ps-4 font-monospace">{{ index + 1 }}</td>
                            <td class="pe-4 font-monospace">{{ chassis }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
