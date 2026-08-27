<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReportsNav from '@/Components/ReportsNav.vue';
import { fbButton, fbCheckbox, fbGhostButton, fbInput, fbLabel } from '@/flowbite';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    cars: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ countries: [], locations: [] }) },
    scoped: { type: Boolean, default: false },
    missingChassis: { type: Array, default: () => [] },
});

const { t } = useI18n();

const filterForm = useForm({
    country_ids: [...(props.filters.country_ids ?? [])].map((id) => String(id)),
    location_status_ids: [...(props.filters.location_status_ids ?? [])].map((id) => String(id)),
    chassis_text: props.filters.chassis_text ?? '',
});

const selectedCount = computed(() => (
    filterForm.country_ids.length
    + filterForm.location_status_ids.length
    + (String(filterForm.chassis_text ?? '').trim() === '' ? 0 : 1)
));

const toggleId = (field, id) => {
    const value = String(id);
    const current = filterForm[field];
    filterForm[field] = current.includes(value)
        ? current.filter((item) => item !== value)
        : [...current, value];
};

const isChecked = (field, id) => filterForm[field].includes(String(id));

const applyFilters = () => {
    filterForm.get(route('reports.land-trips'), { preserveState: true, replace: true });
};

const resetFilters = () => {
    filterForm.country_ids = [];
    filterForm.location_status_ids = [];
    filterForm.chassis_text = '';
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
    const query = params.toString();

    return query ? `${route(name)}?${query}` : route(name);
};

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
                <label :class="fbLabel" for="land-report-chassis">{{ t('reports.chassis_paste') }}</label>
                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">{{ t('reports.chassis_paste_help') }}</p>
                <textarea
                    id="land-report-chassis"
                    v-model="filterForm.chassis_text"
                    :class="fbInput"
                    rows="6"
                    :placeholder="t('reports.chassis_paste_placeholder')"
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
            class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
        >
            {{ t('reports.chassis_missing', { count: missingChassis.length }) }}
            <span class="ms-1 font-mono">{{ missingChassis.join(' · ') }}</span>
        </p>

        <div class="erp-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table erp-table land-report-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('companies.name') }}</th>
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
                            <td class="ps-4 pe-4" colspan="7">
                                <EmptyState icon="R">{{ t('reports.land_trips_pick') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-else-if="!cars.data.length">
                            <td class="ps-4 pe-4" colspan="7">
                                <EmptyState icon="R">{{ t('reports.land_trips_none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="car in cars.data" v-else :key="car.id">
                            <td class="ps-4 font-medium">{{ car.company_name || '—' }}</td>
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
    </AppLayout>
</template>
