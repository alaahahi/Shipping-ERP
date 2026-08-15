<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import LandTripCompanyWallet from '@/Components/LandTripCompanyWallet.vue';
import LandTripCarsModal from '@/Components/LandTripCarsModal.vue';
import CompanyCountryMap from '@/Components/LandTrips/CompanyCountryMap.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Toast from '@/Components/Toast.vue';
import { useLandTripStation } from '@/composables/useLandTripStation';
import { statusRowStyle, normalizeHexColor } from '@/composables/useLandTripStatusColor';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    cars: { type: Object, required: true },
    statusSummary: { type: Array, default: () => [] },
    carStatuses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    highlightCarId: { type: [Number, String], default: null },
    locationLog: { type: Object, default: () => ({ can_undo: false }) },
    wallet: { type: Object, default: () => ({ balances: [], entries: [], currencies: ['USD'] }) },
});

const page = usePage();
const { t } = useI18n();
const { stationLabel } = useLandTripStation();
const success = computed(() => page.props.flash?.success);
const showCars = ref(false);
const selectedIds = ref([]);
const hubTab = ref('cars');

const filterForm = useForm({
    search: props.filters.search ?? '',
    location_status_id: props.filters.location_status_id ?? '',
});

const emptyCarRow = () => ({
    voyage_car_id: null,
    chassis_no: '',
    cmr_waybill: '',
    consignee_name: '',
    description: '',
    weight: '',
    notes: '',
    location_status_id: props.carStatuses[0]?.id ?? '',
});

const carsForm = useForm({
    cars: [emptyCarRow()],
});

const moveForm = useForm({
    location_status_id: '',
    scope: 'selected',
    car_ids: [],
});

watch(
    showCars,
    (open) => {
        if (open) {
            carsForm.cars = [emptyCarRow()];
            carsForm.clearErrors();
        }
    },
);

const loadedCars = ref([...(props.cars.data ?? [])]);
const currentPage = ref(props.cars.current_page ?? 1);
const lastPage = ref(props.cars.last_page ?? 1);
const loadingMore = ref(false);
const loadMoreSentinel = ref(null);
const didScrollHighlight = ref(false);
const replaceLoadedCars = ref(true);
const toastMessage = ref('');
let loadMoreObserver = null;

const companyUrl = (overrides = {}) => {
    const base = route('land-trips.companies.show', props.company.id);
    const params = new URLSearchParams();
    const search = overrides.search !== undefined ? overrides.search : filterForm.search;
    const locationId = overrides.location_status_id !== undefined
        ? overrides.location_status_id
        : filterForm.location_status_id;
    const highlight = overrides.highlight !== undefined ? overrides.highlight : props.highlightCarId;

    if (String(search ?? '').trim() !== '') {
        params.set('search', String(search).trim());
    }
    if (locationId) {
        params.set('location_status_id', String(locationId));
    }
    if (highlight) {
        params.set('highlight', String(highlight));
    }

    const query = params.toString();

    return query ? `${base}?${query}` : base;
};

watch(success, (message) => {
    if (!message) {
        return;
    }
    if (/updated \d+ cars/i.test(String(message)) || /location change undone/i.test(String(message))) {
        return;
    }
    toastMessage.value = message;
});

const exportHref = computed(() => {
    const base = route('land-trips.companies.export', props.company.id);
    const params = new URLSearchParams();
    if (String(filterForm.search ?? '').trim() !== '') {
        params.set('search', String(filterForm.search).trim());
    }
    if (filterForm.location_status_id) {
        params.set('location_status_id', String(filterForm.location_status_id));
    }
    const query = params.toString();

    return query ? `${base}?${query}` : base;
});
const totalCars = computed(() => locationChips.value.reduce((sum, item) => sum + (item.count || 0), 0));
const locationChips = computed(() => (props.statusSummary ?? []).filter((item) => !item.is_archive));
const archiveChip = computed(() => (props.statusSummary ?? []).find((item) => item.is_archive) ?? null);
const countryMapRows = computed(() => {
    const groups = new Map();

    locationChips.value.forEach((item) => {
        if (!item.country_id || !Number(item.count)) {
            return;
        }

        if (!groups.has(item.country_id)) {
            groups.set(item.country_id, {
                id: item.country_id,
                label: item.country_label || item.label,
                iso_code: item.country_iso,
                latitude: item.latitude,
                longitude: item.longitude,
                cars_count: 0,
                locations: [],
            });
        }

        const group = groups.get(item.country_id);
        group.cars_count += Number(item.count) || 0;
        group.locations.push({
            id: item.id,
            label: item.label,
            color: item.color,
            count: item.count,
        });
    });

    return [...groups.values()].sort((a, b) => b.cars_count - a.cars_count);
});
const archiveStatusIds = computed(() => (props.carStatuses ?? [])
    .filter((status) => status.is_archive)
    .map((status) => String(status.id)));
const viewingArchive = computed(() => archiveStatusIds.value.includes(String(filterForm.location_status_id || '')));
const selectedCount = computed(() => selectedIds.value.length);
const activeStatusId = computed(() => String(filterForm.location_status_id || ''));
const filtering = ref(false);
const pageIds = computed(() => loadedCars.value.map((car) => car.id));
const hasMoreCars = computed(() => currentPage.value < lastPage.value);
const allPageSelected = computed(
    () => pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.includes(id)),
);

const applyFilters = () => {
    selectedIds.value = [];
    replaceLoadedCars.value = true;
    router.get(
        companyUrl({ page: 1 }),
        {},
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            onStart: () => {
                filtering.value = true;
            },
            onFinish: () => {
                filtering.value = false;
            },
        },
    );
};

let filterTimer = null;
watch(
    () => filterForm.search,
    () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => applyFilters(), 350);
    },
);
onBeforeUnmount(() => {
    clearTimeout(filterTimer);
    loadMoreObserver?.disconnect();
});

const scrollToHighlight = async () => {
    if (!props.highlightCarId || didScrollHighlight.value) {
        return;
    }
    await nextTick();
    const row = document.getElementById(`land-car-${props.highlightCarId}`);
    if (!row) {
        return;
    }
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    didScrollHighlight.value = true;
};

const mergeLoadedCars = (paginator) => {
    const pageNum = paginator.current_page ?? 1;
    const rows = paginator.data ?? [];

    if (replaceLoadedCars.value) {
        loadedCars.value = [...rows];
        currentPage.value = paginator.current_page ?? 1;
        lastPage.value = paginator.last_page ?? 1;
        replaceLoadedCars.value = false;
        return;
    }

    const incoming = new Map(rows.map((car) => [car.id, car]));
    loadedCars.value = loadedCars.value.map((car) => incoming.get(car.id) ?? car);

    if (loadingMore.value || pageNum > 1) {
        const seen = new Set(loadedCars.value.map((car) => car.id));
        loadedCars.value = [
            ...loadedCars.value,
            ...rows.filter((car) => !seen.has(car.id)),
        ];
    }
};

watch(
    () => props.cars,
    (paginator) => {
        mergeLoadedCars(paginator);
        scrollToHighlight();
    },
    { immediate: true },
);

const loadMore = async () => {
    if (loadingMore.value || filtering.value || !hasMoreCars.value) {
        return;
    }

    loadingMore.value = true;
    try {
        const { data } = await axios.get(route('land-trips.companies.cars', props.company.id), {
            params: {
                page: currentPage.value + 1,
                search: filterForm.search || undefined,
                location_status_id: filterForm.location_status_id || undefined,
            },
        });
        currentPage.value = data.current_page ?? currentPage.value + 1;
        lastPage.value = data.last_page ?? lastPage.value;
        mergeLoadedCars(data);
    } finally {
        loadingMore.value = false;
    }
};

onMounted(() => {
    const url = new URL(window.location.href);
    if (url.searchParams.has('page')) {
        url.searchParams.delete('page');
        const query = url.searchParams.toString();
        window.history.replaceState(window.history.state, '', `${url.pathname}${query ? `?${query}` : ''}${url.hash}`);
    }

    loadMoreObserver = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                loadMore();
            }
        },
        { root: null, rootMargin: '280px 0px', threshold: 0 },
    );
    if (loadMoreSentinel.value) {
        loadMoreObserver.observe(loadMoreSentinel.value);
    }
});

watch(loadMoreSentinel, (el, previous) => {
    if (previous) {
        loadMoreObserver?.unobserve(previous);
    }
    if (el) {
        loadMoreObserver?.observe(el);
    }
});

const filterByStatus = (statusId) => {
    filterForm.location_status_id = statusId == null ? '' : String(statusId);
    applyFilters();
};

const rowClass = (car) => {
    const classes = ['land-status-colored'];
    if (props.highlightCarId && String(car.id) === String(props.highlightCarId)) {
        classes.push('land-car-highlight');
    }
    if (selectedIds.value.includes(car.id)) {
        classes.push('land-car-selected');
    }

    return classes;
};

const rowStyle = (car) => statusRowStyle(car.location_status_color);

const chipStyle = (color) => ({
    '--land-chip-color': normalizeHexColor(color || '#0F766E'),
});

const isChipActive = (statusId) => {
    if (statusId == null || statusId === '') {
        return activeStatusId.value === '';
    }

    return activeStatusId.value === String(statusId);
};

const carStationLabel = (car) => {
    const status = props.carStatuses.find((item) => String(item.id) === String(car.location_status_id));

    return stationLabel(status || car);
};

const toggleSelectAll = () => {
    selectedIds.value = allPageSelected.value ? [] : [...pageIds.value];
};

const toggleRow = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }
    selectedIds.value = [...selectedIds.value, id];
};

const saveCars = () => {
    carsForm.post(route('land-trips.companies.cars.sync', props.company.id), {
        preserveScroll: true,
        onSuccess: () => {
            replaceLoadedCars.value = true;
            showCars.value = false;
        },
    });
};

const locationPatch = (statusId) => {
    const status = props.carStatuses.find((item) => String(item.id) === String(statusId));

    return {
        location_status_id: status?.id ?? statusId,
        location_status_code: status?.code ?? null,
        location_status_label: status ? stationLabel(status) : '',
        location_status_tone: status?.row_tone ?? 'neutral',
        location_status_color: status?.color ?? null,
    };
};

const applyLocationLocally = (carIds, statusId) => {
    const patch = locationPatch(statusId);
    const filterId = String(filterForm.location_status_id || '');
    const movingToArchive = archiveStatusIds.value.includes(String(statusId));
    const hideMoved = (filterId !== '' && String(statusId) !== filterId)
        || (!viewingArchive.value && movingToArchive)
        || (viewingArchive.value && !movingToArchive);
    const selected = new Set((carIds ?? []).map((id) => Number(id)));

    loadedCars.value = loadedCars.value.flatMap((car) => {
        if (!selected.has(Number(car.id))) {
            return [car];
        }
        if (hideMoved) {
            return [];
        }

        return [{ ...car, ...patch }];
    });
};

const applyLocation = (carIds = []) => {
    if (!moveForm.location_status_id) {
        return;
    }

    const snapshot = loadedCars.value.map((car) => ({ ...car }));
    applyLocationLocally(carIds, moveForm.location_status_id);

    moveForm.scope = 'selected';
    moveForm.car_ids = carIds;

    moveForm.post(route('land-trips.companies.cars.location', props.company.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['cars', 'statusSummary', 'locationLog'],
        onSuccess: () => {
            selectedIds.value = [];
            toastMessage.value = t('land_trips.location_updated');
        },
        onError: () => {
            loadedCars.value = snapshot;
        },
    });
};

const moveSelected = () => applyLocation(selectedIds.value);

const undoLastLocation = () => {
    replaceLoadedCars.value = true;
    router.post(route('land-trips.companies.location-logs.undo', props.company.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['cars', 'statusSummary', 'locationLog'],
        onSuccess: () => {
            selectedIds.value = [];
            toastMessage.value = t('land_trips.location_undone');
        },
    });
};

const moveOne = (carId, statusId) => {
    if (!statusId) {
        return;
    }
    moveForm.location_status_id = String(statusId);
    applyLocation([carId]);
};
</script>

<template>
    <Head :title="company.name" />
    <AppLayout>
        <template #header>{{ company.name }}</template>
        <Toast :message="toastMessage" @dismiss="toastMessage = ''" />

        <div class="land-hub">
            <Link :href="route('land-trips.index')" class="land-hub-back">
                {{ t('land_trips.back_companies') }}
            </Link>

            <PageHeader
                :kicker="t('land_trips.title')"
                :title="company.name"
                :subtitle="hubTab === 'wallet' ? t('land_trips.wallet_help') : t('land_trips.company_cars_help')"
            >
                <template #actions>
                    <template v-if="hubTab === 'cars'">
                        <a
                            :href="route('land-trips.companies.location-logs', company.id)"
                            class="btn btn-erp-ghost"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ t('land_trips.location_log') }}
                        </a>
                        <button
                            v-if="canManage"
                            type="button"
                            class="btn btn-erp-ghost"
                            :disabled="!locationLog.can_undo"
                            @click="undoLastLocation"
                        >
                            {{ t('land_trips.undo_last') }}
                        </button>
                        <a :href="exportHref" class="btn btn-erp-ghost">
                            {{ t('land_trips.export') }}
                        </a>
                        <Link
                            v-if="canManage"
                            :href="route('land-trips.companies.import', company.id)"
                            class="btn btn-erp-ghost"
                        >
                            {{ t('land_trips.import') }}
                        </Link>
                        <button v-if="canManage" type="button" class="btn btn-erp" @click="showCars = true">
                            {{ t('land_trips.manage_cars') }}
                        </button>
                    </template>
                </template>
            </PageHeader>

            <div class="erp-tabs mb-3">
                <button
                    type="button"
                    class="erp-tab"
                    :class="{ active: hubTab === 'cars' }"
                    @click="hubTab = 'cars'"
                >
                    {{ t('land_trips.cars_tab') }}
                </button>
                <button
                    type="button"
                    class="erp-tab"
                    :class="{ active: hubTab === 'wallet' }"
                    @click="hubTab = 'wallet'"
                >
                    {{ t('land_trips.wallet_tab') }}
                </button>
            </div>

            <CompanyCountryMap v-show="hubTab === 'cars'" :active="hubTab === 'cars'" :countries="countryMapRows" />

            <div v-show="hubTab === 'cars'" class="erp-card p-0 overflow-hidden land-hub-workspace">
                <div class="land-hub-toolbar">
                    <div class="land-hub-chips" role="tablist" :aria-label="t('land_trips.location_filters')">
                        <button
                            type="button"
                            class="land-hub-chip"
                            :class="{ 'is-active': isChipActive('') }"
                            :aria-pressed="isChipActive('')"
                            :style="chipStyle('#0F766E')"
                            @click="filterByStatus(null)"
                        >
                            <span class="land-hub-chip-dot" aria-hidden="true" />
                            <span class="land-hub-chip-label">{{ t('land_trips.all_cars') }}</span>
                            <span class="land-hub-chip-count">{{ totalCars }}</span>
                        </button>
                        <button
                            v-for="item in locationChips"
                            :key="`${item.id ?? 'none'}-${item.code}`"
                            type="button"
                            class="land-hub-chip"
                            :class="{ 'is-active': isChipActive(item.id) }"
                            :aria-pressed="isChipActive(item.id)"
                            :style="chipStyle(item.color)"
                            @click="filterByStatus(item.id)"
                        >
                            <span class="land-hub-chip-dot" aria-hidden="true" />
                            <span class="land-hub-chip-label">{{ stationLabel(item) }}</span>
                            <span class="land-hub-chip-count">{{ item.count }}</span>
                        </button>
                        <button
                            v-if="archiveChip"
                            type="button"
                            class="land-hub-chip is-archive"
                            :class="{ 'is-active': isChipActive(archiveChip.id) }"
                            :aria-pressed="isChipActive(archiveChip.id)"
                            :style="chipStyle(archiveChip.color)"
                            @click="filterByStatus(archiveChip.id)"
                        >
                            <span class="land-hub-chip-dot" aria-hidden="true" />
                            <span class="land-hub-chip-label">{{ stationLabel(archiveChip) }}</span>
                            <span class="land-hub-chip-count">{{ archiveChip.count }}</span>
                        </button>
                    </div>

                    <form class="land-hub-search" @submit.prevent>
                        <label class="visually-hidden" for="land-hub-search">{{ t('land_trips.search_cars') }}</label>
                        <input
                            id="land-hub-search"
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-erp-control"
                            :placeholder="t('land_trips.search_cars')"
                            autocomplete="off"
                        />
                    </form>

                    <div v-if="canManage" class="land-hub-bulk">
                        <label class="form-erp-label mb-0 land-hub-bulk-label" for="land-hub-move">
                            {{ t('land_trips.move_to') }}
                        </label>
                        <select
                            id="land-hub-move"
                            v-model="moveForm.location_status_id"
                            class="form-select form-erp-control land-hub-bulk-select"
                        >
                            <option value="">{{ t('land_trips.choose_location') }}</option>
                            <option v-for="status in carStatuses" :key="status.id" :value="status.id">
                                {{ stationLabel(status) }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="btn btn-erp"
                            :disabled="moveForm.processing || !moveForm.location_status_id || !selectedCount"
                            @click="moveSelected"
                        >
                            {{ t('land_trips.apply_selected') }}
                            <span class="land-hub-bulk-n">({{ selectedCount }})</span>
                        </button>
                        <p class="land-hub-bulk-hint mb-0">{{ t('land_trips.bulk_move_hint') }}</p>
                        <InputError :message="moveForm.errors.location_status_id || moveForm.errors.car_ids || moveForm.errors.cars" />
                    </div>
                </div>

                <div class="table-responsive land-hub-table" :class="{ 'is-loading': filtering && !loadingMore }">
                    <table class="table erp-table align-middle mb-0 land-hub-cars">
                        <thead>
                            <tr>
                                <th v-if="canManage" class="ps-3" style="width: 3rem">
                                    <label class="land-hub-check">
                                        <span class="visually-hidden">{{ t('land_trips.select_all_cars') }}</span>
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :checked="allPageSelected"
                                            @change="toggleSelectAll"
                                        />
                                    </label>
                                </th>
                                <th :class="['land-hub-seq-col', canManage ? '' : 'ps-4']">{{ t('land_trips.sequence') }}</th>
                                <th>{{ t('land_trips.chassis') }}</th>
                                <th>{{ t('land_trips.cmr_waybill') }}</th>
                                <th>{{ t('land_trips.consignee') }}</th>
                                <th>{{ t('common.description') }}</th>
                                <th>{{ t('land_trips.location_status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!loadedCars.length">
                                <td :colspan="canManage ? 7 : 6">
                                    <EmptyState
                                        :title="viewingArchive ? t('land_trips.empty_archive') : t('land_trips.empty_cars')"
                                        icon="C"
                                    >
                                        <template v-if="!viewingArchive">
                                            {{ t('land_trips.no_cars_action') }}
                                            <div v-if="canManage" class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                                                <Link :href="route('land-trips.companies.import', company.id)" class="btn btn-erp">
                                                    {{ t('land_trips.import') }}
                                                </Link>
                                                <button type="button" class="btn btn-erp-ghost" @click="showCars = true">
                                                    {{ t('land_trips.manage_cars') }}
                                                </button>
                                            </div>
                                        </template>
                                        <template v-else>
                                            {{ t('land_trips.empty_archive_help') }}
                                        </template>
                                    </EmptyState>
                                </td>
                            </tr>
                            <tr
                                v-for="(car, index) in loadedCars"
                                :id="`land-car-${car.id}`"
                                :key="car.id"
                                :class="rowClass(car)"
                                :style="rowStyle(car)"
                            >
                                <td v-if="canManage" class="ps-3">
                                    <label class="land-hub-check">
                                        <span class="visually-hidden">{{ car.chassis_no || t('land_trips.chassis') }}</span>
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :checked="selectedIds.includes(car.id)"
                                            @change="toggleRow(car.id)"
                                        />
                                    </label>
                                </td>
                                <td :class="['land-hub-seq', canManage ? '' : 'ps-4']">{{ index + 1 }}</td>
                                <td class="font-monospace fw-semibold">{{ car.chassis_no || '—' }}</td>
                                <td>{{ car.cmr_waybill || '—' }}</td>
                                <td>{{ car.consignee_name || '—' }}</td>
                                <td>{{ car.description || '—' }}</td>
                                <td>
                                    <select
                                        v-if="canManage"
                                        class="form-select form-select-sm form-erp-control land-hub-row-select"
                                        :value="car.location_status_id || ''"
                                        :disabled="moveForm.processing"
                                        :aria-label="t('land_trips.location_status')"
                                        @change="moveOne(car.id, $event.target.value)"
                                    >
                                        <option value="">{{ t('land_trips.unspecified_location') }}</option>
                                        <option v-for="status in carStatuses" :key="status.id" :value="status.id">
                                            {{ stationLabel(status) }}
                                        </option>
                                    </select>
                                    <span v-else>{{ carStationLabel(car) }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="loadedCars.length" class="land-hub-pager">
                    <div
                        v-if="hasMoreCars"
                        ref="loadMoreSentinel"
                        class="land-hub-infinite"
                        aria-live="polite"
                    >
                        <span v-if="loadingMore">{{ t('land_trips.loading_more') }}</span>
                    </div>
                </div>
            </div>

            <div v-show="hubTab === 'wallet'">
                <LandTripCompanyWallet :company="company" :wallet="wallet" :can-manage="canManage" />
            </div>
        </div>

        <LandTripCarsModal
            :show="showCars"
            add-only
            :cars="carsForm.cars"
            :car-statuses="carStatuses"
            :processing="carsForm.processing"
            @update:cars="carsForm.cars = $event"
            @close="showCars = false"
            @save="saveCars"
        />
    </AppLayout>
</template>
