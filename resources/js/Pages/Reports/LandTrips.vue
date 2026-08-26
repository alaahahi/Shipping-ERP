<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ReportsNav from '@/Components/ReportsNav.vue';
import { fbButton, fbCheckbox, fbGhostButton, fbLabel } from '@/flowbite';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    cars: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ countries: [], locations: [] }) },
    scoped: { type: Boolean, default: false },
});

const { t } = useI18n();

const filterForm = useForm({
    country_ids: [...(props.filters.country_ids ?? [])].map((id) => String(id)),
    location_status_ids: [...(props.filters.location_status_ids ?? [])].map((id) => String(id)),
});

const selectedCount = computed(() => (
    filterForm.country_ids.length + filterForm.location_status_ids.length
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
    filterForm.get(route('reports.land-trips'), { preserveState: true, replace: true });
};

const exportUrl = (name) => {
    const params = new URLSearchParams();
    filterForm.country_ids.forEach((id) => params.append('country_ids[]', id));
    filterForm.location_status_ids.forEach((id) => params.append('location_status_ids[]', id));
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

        <form class="mb-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800" @submit.prevent="applyFilters">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">{{ t('reports.land_trips_filter_help') }}</p>
            <InputError :message="filterForm.errors.country_ids || filterForm.errors.location_status_ids" />

            <div class="grid gap-4 md:grid-cols-2">
                <fieldset>
                    <legend :class="fbLabel">{{ t('reports.countries') }}</legend>
                    <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-600">
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
                    <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-600">
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

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm text-gray-700 dark:text-gray-300">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">{{ t('companies.name') }}</th>
                            <th class="px-4 py-3">{{ t('reports.country') }}</th>
                            <th class="px-4 py-3">{{ t('reports.location') }}</th>
                            <th class="px-4 py-3">{{ t('land_trips.chassis') }}</th>
                            <th class="px-4 py-3">{{ t('land_trips.model') }}</th>
                            <th class="px-4 py-3">{{ t('land_trips.consignee') }}</th>
                            <th class="px-4 py-3 text-end">{{ t('land_trips.car_price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!scoped">
                            <td class="px-4 py-6" colspan="7">
                                <EmptyState icon="R">{{ t('reports.land_trips_pick') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-else-if="!cars.data.length">
                            <td class="px-4 py-6" colspan="7">
                                <EmptyState icon="R">{{ t('reports.land_trips_none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr
                            v-for="car in cars.data"
                            v-else
                            :key="car.id"
                            class="border-t border-gray-200 dark:border-gray-700"
                        >
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ car.company_name || '—' }}</td>
                            <td class="px-4 py-3">{{ car.country_label || '—' }}</td>
                            <td class="px-4 py-3">{{ car.location_status_label || '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ car.chassis_no || '—' }}</td>
                            <td class="px-4 py-3">{{ car.model || '—' }}</td>
                            <td class="px-4 py-3">{{ car.consignee_name || '—' }}</td>
                            <td class="px-4 py-3 text-end font-mono">{{ car.price }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="scoped && (cars.prev_page_url || cars.next_page_url)"
                class="flex items-center justify-between border-t border-gray-200 p-3 dark:border-gray-700"
            >
                <Link
                    v-if="cars.prev_page_url"
                    :href="cars.prev_page_url"
                    class="text-sm font-medium text-teal-700 hover:underline dark:text-teal-400"
                    preserve-scroll
                >
                    {{ t('common.prev') }}
                </Link>
                <span v-else />
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ cars.from }}–{{ cars.to }} / {{ cars.total }}</span>
                <Link
                    v-if="cars.next_page_url"
                    :href="cars.next_page_url"
                    class="text-sm font-medium text-teal-700 hover:underline dark:text-teal-400"
                    preserve-scroll
                >
                    {{ t('common.next') }}
                </Link>
                <span v-else />
            </div>
        </div>
    </AppLayout>
</template>
