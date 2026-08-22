<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import LandTripCompanyWallet from '@/Components/LandTripCompanyWallet.vue';
import LandTripCarsModal from '@/Components/LandTripCarsModal.vue';
import CompanyCountryMap from '@/Components/LandTrips/CompanyCountryMap.vue';
import LandTripCarCheck from '@/Components/LandTrips/LandTripCarCheck.vue';
import LandTripCarEditModal from '@/Components/LandTrips/LandTripCarEditModal.vue';
import LandTripCarBulkPriceModal from '@/Components/LandTrips/LandTripCarBulkPriceModal.vue';
import LandTripCarViewModal from '@/Components/LandTrips/LandTripCarViewModal.vue';
import LandTripCmrGroups from '@/Components/LandTrips/LandTripCmrGroups.vue';
import LandTripModelGroups from '@/Components/LandTrips/LandTripModelGroups.vue';
import LandTripSearchBar from '@/Components/LandTrips/LandTripSearchBar.vue';
import LandTripCarTransferModal from '@/Components/LandTrips/LandTripCarTransferModal.vue';
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
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
    importLog: { type: Object, default: () => ({ can_undo: false }) },
    priceLog: { type: Object, default: () => ({ has_entries: false }) },
    wallet: { type: Object, default: () => ({ balances: [], summary: null, entries: [], currencies: ['USD'] }) },
    chassisLetterOCount: { type: Number, default: 0 },
});

const page = usePage();
const { t } = useI18n();
const { stationLabel } = useLandTripStation();
const success = computed(() => page.props.flash?.success);
const showCars = ref(false);
const editingCar = ref(null);
const viewingCar = ref(null);
const showDuplicates = ref(false);
const duplicateGroups = ref([]);
const duplicatesLoading = ref(false);
const duplicatesError = ref('');
const selectedIds = ref([]);
const showTransfer = ref(false);
const showBulkPrice = ref(false);
const hubTab = ref('cars');
const carsViewMode = ref('list');
const cmrGroupsRef = ref(null);

watch(hubTab, (tab) => {
    if (tab !== 'wallet') {
        return;
    }

    router.reload({
        only: ['wallet'],
        preserveState: true,
        preserveScroll: true,
    });
});

const filterForm = useForm({
    location_status_id: props.filters.location_status_id ?? '',
    sort: props.filters.sort || 'newest',
});
const carSearch = ref(String(props.filters.search ?? '').trim());

const emptyCarRow = () => ({
    voyage_car_id: null,
    chassis_no: '',
    cmr_waybill: '',
    consignee_name: '',
    model: '',
    color: '',
    year: '',
    description: '',
    weight: '',
    price: 0,
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
const deletingSelected = ref(false);
const loadMoreSentinel = ref(null);
const didScrollHighlight = ref(false);
const replaceLoadedCars = ref(true);
const toastMessage = ref('');
let loadMoreObserver = null;

const companyUrl = (overrides = {}) => {
    const base = route('land-trips.companies.show', props.company.id);
    const params = new URLSearchParams();
    const locationId = overrides.location_status_id !== undefined
        ? overrides.location_status_id
        : filterForm.location_status_id;
    const sort = overrides.sort !== undefined ? overrides.sort : filterForm.sort;
    const highlight = overrides.highlight !== undefined ? overrides.highlight : props.highlightCarId;

    if (locationId) {
        params.set('location_status_id', String(locationId));
    }
    if (sort && sort !== 'newest') {
        params.set('sort', String(sort));
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
    if (/updated \d+ cars/i.test(String(message)) || /deleted \d+ cars/i.test(String(message)) || /location change undone/i.test(String(message)) || /excel import undone/i.test(String(message)) || /transferred \d+ cars/i.test(String(message))) {
        return;
    }
    toastMessage.value = message;
});

const carsOutputParams = () => {
    const params = new URLSearchParams();
    if (selectedIds.value.length) {
        selectedIds.value.forEach((id) => params.append('car_ids[]', String(id)));
    } else {
        if (String(carSearch.value ?? '').trim() !== '') {
            params.set('search', String(carSearch.value).trim());
        }
        if (filterForm.location_status_id) {
            params.set('location_status_id', String(filterForm.location_status_id));
        }
    }
    if (filterForm.sort && filterForm.sort !== 'newest') {
        params.set('sort', String(filterForm.sort));
    }

    return params;
};

const hrefWithOutputParams = (base) => {
    const query = carsOutputParams().toString();

    return query ? `${base}?${query}` : base;
};

const exportHref = computed(() => hrefWithOutputParams(route('land-trips.companies.export', props.company.id)));
const pdfHref = computed(() => hrefWithOutputParams(route('land-trips.companies.export.pdf', props.company.id)));
const printHref = computed(() => hrefWithOutputParams(route('land-trips.companies.print', props.company.id)));
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
let searchTimer = null;
let searchRequest = 0;

const carSearchHay = (car) => [
    car.chassis_no,
    car.consignee_name,
    car.description,
    car.model,
    car.color,
    car.notes,
    car.cmr_waybill,
    car.year,
].filter((value) => value !== null && value !== undefined && String(value) !== '').join(' ').toLowerCase();

const displayedCars = computed(() => {
    const query = String(carSearch.value ?? '').trim().toLowerCase();
    if (!query) {
        return loadedCars.value;
    }

    return loadedCars.value.filter((car) => carSearchHay(car).includes(query));
});

const pageIds = computed(() => displayedCars.value.map((car) => car.id));
const hasMoreCars = computed(() => currentPage.value < lastPage.value);
const allPageSelected = computed(
    () => pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.includes(id)),
);

const applyFilters = () => {
    selectedIds.value = [];
    replaceLoadedCars.value = true;
    router.get(
        companyUrl(),
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
                if (String(carSearch.value ?? '').trim() !== '') {
                    runCarSearch();
                }
            },
        },
    );
};

watch(
    () => filterForm.sort,
    () => {
        applyFilters();
    },
);

const carsQueryParams = (page = 1) => {
    const params = { page };
    const query = String(carSearch.value ?? '').trim();
    if (query !== '') {
        params.search = query;
    }
    if (filterForm.location_status_id) {
        params.location_status_id = filterForm.location_status_id;
    }
    if (filterForm.sort && filterForm.sort !== 'newest') {
        params.sort = filterForm.sort;
    }

    return params;
};

const restoreCarsFromProps = () => {
    loadedCars.value = [...(props.cars.data ?? [])];
    currentPage.value = props.cars.current_page ?? 1;
    lastPage.value = props.cars.last_page ?? 1;
    replaceLoadedCars.value = false;
    selectedIds.value = [];
};

const stripSearchFromAddressBar = () => {
    const url = new URL(window.location.href);
    if (!url.searchParams.has('page') && !url.searchParams.has('search')) {
        return;
    }
    url.searchParams.delete('page');
    url.searchParams.delete('search');
    const query = url.searchParams.toString();
    const next = `${url.pathname}${query ? `?${query}` : ''}${url.hash}`;
    const state = window.history.state;
    if (state && typeof state === 'object' && state.page && typeof state.page === 'object') {
        window.history.replaceState(
            { ...state, page: { ...state.page, url: `${url.pathname}${query ? `?${query}` : ''}` } },
            '',
            next,
        );
        return;
    }
    window.history.replaceState(state, '', next);
};

const runCarSearch = async () => {
    const requestId = ++searchRequest;
    filtering.value = true;
    replaceLoadedCars.value = true;
    selectedIds.value = [];

    try {
        const { data } = await axios.get(route('land-trips.companies.cars', props.company.id), {
            params: carsQueryParams(1),
        });
        if (requestId !== searchRequest) {
            return;
        }
        loadedCars.value = [...(data.data ?? [])];
        currentPage.value = data.current_page ?? 1;
        lastPage.value = data.last_page ?? 1;
        replaceLoadedCars.value = false;
    } finally {
        if (requestId === searchRequest) {
            filtering.value = false;
        }
    }
};

watch(
    carSearch,
    (value) => {
        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        const query = String(value ?? '').trim();
        if (query === '') {
            searchRequest += 1;
            restoreCarsFromProps();
            filtering.value = false;
            stripSearchFromAddressBar();
            searchTimer = setTimeout(() => {
                runCarSearch();
            }, 0);
            return;
        }

        searchTimer = setTimeout(() => {
            runCarSearch();
        }, 280);
    },
);

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
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
    const shouldReplace = replaceLoadedCars.value
        || (pageNum <= 1 && !loadingMore.value && loadedCars.value.length === 0);

    if (shouldReplace) {
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
        currentPage.value = paginator.current_page ?? currentPage.value;
        lastPage.value = paginator.last_page ?? lastPage.value;
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
    if (loadingMore.value || filtering.value || deletingSelected.value || !hasMoreCars.value) {
        return;
    }

    loadingMore.value = true;
    try {
        const { data } = await axios.get(route('land-trips.companies.cars', props.company.id), {
            params: carsQueryParams(currentPage.value + 1),
        });
        currentPage.value = data.current_page ?? currentPage.value + 1;
        lastPage.value = data.last_page ?? lastPage.value;
        mergeLoadedCars(data);
    } finally {
        loadingMore.value = false;
    }
};

onMounted(() => {
    stripSearchFromAddressBar();

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
    replaceLoadedCars.value = true;

    moveForm.scope = 'selected';
    moveForm.car_ids = carIds;

    moveForm.post(route('land-trips.companies.cars.location', props.company.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['cars', 'statusSummary', 'locationLog', 'importLog'],
        onSuccess: () => {
            selectedIds.value = [];
            mergeLoadedCars(props.cars);
            toastMessage.value = t('land_trips.location_updated');
        },
        onError: () => {
            loadedCars.value = snapshot;
        },
    });
};

const deleteSelected = () => {
    if (!selectedCount.value) {
        return;
    }

    if (!window.confirm(t('land_trips.delete_selected_confirm', { count: selectedCount.value }))) {
        return;
    }

    const carIds = [...selectedIds.value];
    const snapshot = loadedCars.value.map((car) => ({ ...car }));
    loadedCars.value = loadedCars.value.filter((car) => !carIds.includes(car.id) && !carIds.includes(Number(car.id)));
    replaceLoadedCars.value = true;
    deletingSelected.value = true;

    router.delete(route('land-trips.companies.cars.destroy', props.company.id), {
        data: { car_ids: carIds },
        preserveScroll: true,
        preserveState: true,
        only: ['cars', 'statusSummary', 'locationLog'],
        onSuccess: () => {
            selectedIds.value = [];
            mergeLoadedCars(props.cars);
            toastMessage.value = t('land_trips.cars_deleted');
        },
        onError: () => {
            loadedCars.value = snapshot;
        },
        onFinish: () => {
            deletingSelected.value = false;
        },
    });
};

const moveSelected = () => applyLocation(selectedIds.value);

const openTransfer = () => {
    if (!selectedCount.value) {
        return;
    }
    showTransfer.value = true;
};

const onCarsTransferred = () => {
    const moved = new Set(selectedIds.value.map((id) => Number(id)));
    loadedCars.value = loadedCars.value.filter((car) => !moved.has(Number(car.id)));
    selectedIds.value = [];
    replaceLoadedCars.value = true;
    toastMessage.value = t('land_trips.cars_transferred');
};

const openBulkPrice = () => {
    if (!selectedCount.value) {
        return;
    }
    showBulkPrice.value = true;
};

const onBulkPriceSaved = ({ price, carIds }) => {
    const selected = new Set((carIds ?? []).map((id) => Number(id)));
    const nextPrice = Number.isFinite(Number(price)) ? Number(price) : 0;
    loadedCars.value = loadedCars.value.map((car) => (
        selected.has(Number(car.id))
            ? { ...car, price: nextPrice.toFixed(2) }
            : car
    ));
    selectedIds.value = [];
    toastMessage.value = t('land_trips.bulk_price_updated');
};

const undoLastLocation = () => {
    replaceLoadedCars.value = true;
    router.post(route('land-trips.companies.location-logs.undo', props.company.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['cars', 'statusSummary', 'locationLog', 'importLog'],
        onSuccess: () => {
            selectedIds.value = [];
            toastMessage.value = t('land_trips.location_undone');
        },
    });
};

const undoLastImport = () => {
    replaceLoadedCars.value = true;
    router.post(route('land-trips.companies.import-logs.undo', props.company.id), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ['cars', 'statusSummary', 'locationLog', 'importLog'],
        onSuccess: () => {
            selectedIds.value = [];
            toastMessage.value = t('land_trips.import_undone');
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

const integerPrice = (value) => {
    if (value === '' || value === null || value === undefined) {
        return 0;
    }
    const n = Number(value);
    return Number.isFinite(n) ? Math.round(n) : 0;
};

const savePrice = (car, value) => {
    const price = integerPrice(value);
    if (integerPrice(car.price) === price) {
        return;
    }

    router.patch(route('land-trips.companies.cars.price', [props.company.id, car.id]), {
        price,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['wallet'],
        onSuccess: () => {
            loadedCars.value = loadedCars.value.map((row) => (
                row.id === car.id ? { ...row, price } : row
            ));
        },
    });
};

const saveCarDetails = (car, field, value) => {
    let next = String(value ?? '').trim();
    let current = String(car[field] ?? '').trim();

    if (field === 'year') {
        next = next === '' ? '' : String(Number.parseInt(next, 10) || '');
        current = car.year == null || car.year === '' ? '' : String(car.year);
    }

    if (next === current) {
        return;
    }

    const payload = field === 'year'
        ? { year: next === '' ? null : Number(next) }
        : { [field]: next };

    router.patch(route('land-trips.companies.cars.update', [props.company.id, car.id]), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            loadedCars.value = loadedCars.value.map((row) => {
                if (row.id !== car.id) {
                    return row;
                }

                const patch = { ...row };
                if (field === 'year') {
                    patch.year = next === '' ? null : Number(next);
                } else {
                    patch[field] = next;
                }
                if (field === 'model') {
                    patch.description = next || row.description;
                }

                return patch;
            });
        },
    });
};

const openEditCar = (car) => {
    editingCar.value = { ...car };
};

const openViewCar = (car) => {
    viewingCar.value = { ...car };
};

const onCarEdited = (patch) => {
    const status = props.carStatuses.find((item) => String(item.id) === String(patch.location_status_id ?? ''));
    loadedCars.value = loadedCars.value.map((row) => (
        row.id === patch.id
            ? {
                ...row,
                ...patch,
                model: patch.model || patch.description || row.model,
                description: patch.model || row.description,
                price: String(Number(patch.price ?? row.price).toFixed(2)),
                location_status_label: status ? stationLabel(status) : (patch.location_status_id ? row.location_status_label : null),
                location_status_code: status?.code ?? (patch.location_status_id ? row.location_status_code : null),
                location_status_color: status?.color ?? (patch.location_status_id ? row.location_status_color : null),
                location_status_tone: status?.row_tone ?? (patch.location_status_id ? row.location_status_tone : 'neutral'),
            }
            : row
    ));
    toastMessage.value = t('land_trips.car_updated');
};

const checkDuplicates = async () => {
    showDuplicates.value = true;
    duplicatesLoading.value = true;
    duplicatesError.value = '';
    try {
        const { data } = await axios.get(route('land-trips.companies.cars.duplicates', props.company.id));
        duplicateGroups.value = data.groups ?? [];
    } catch {
        duplicatesError.value = t('land_trips.duplicates_load_fail');
        duplicateGroups.value = [];
    } finally {
        duplicatesLoading.value = false;
    }
};

const focusDuplicateCar = (carId) => {
    showDuplicates.value = false;
    const el = document.getElementById(`land-car-${carId}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('land-hub-row-flash');
        window.setTimeout(() => el.classList.remove('land-hub-row-flash'), 1800);
        return;
    }

    router.get(companyUrl({ highlight: carId }), {}, {
        preserveState: false,
        preserveScroll: false,
    });
};

const duplicateCarCount = computed(() => (
    duplicateGroups.value.reduce((sum, group) => sum + (group.count || 0), 0)
));
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
                :subtitle="hubTab === 'wallet'
                    ? t('land_trips.wallet_help')
                    : hubTab === 'check'
                        ? t('land_trips.check_help')
                        : ''"
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
                        <a
                            :href="route('land-trips.companies.transfer-logs', company.id)"
                            class="btn btn-erp-ghost"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ t('land_trips.transfer_log') }}
                        </a>
                        <a
                            :href="route('land-trips.companies.price-logs', company.id)"
                            class="btn btn-erp-ghost"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ t('land_trips.price_log') }}
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
                        <a
                            :href="route('land-trips.companies.import-logs', company.id)"
                            class="btn btn-erp-ghost"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ t('land_trips.import_log') }}
                        </a>
                        <button
                            v-if="canManage"
                            type="button"
                            class="btn btn-erp-ghost"
                            :disabled="!importLog.can_undo"
                            @click="undoLastImport"
                        >
                            {{ t('land_trips.undo_last_import') }}
                        </button>
                        <a :href="printHref" class="btn btn-erp-ghost">
                            {{ selectedCount ? t('land_trips.print_n', { count: selectedCount }) : t('common.print') }}
                        </a>
                        <a :href="exportHref" class="btn btn-erp-ghost">
                            {{ selectedCount ? t('land_trips.export_n', { count: selectedCount }) : t('land_trips.export') }}
                        </a>
                        <a :href="pdfHref" class="btn btn-erp-ghost">
                            {{ selectedCount ? t('land_trips.export_pdf_n', { count: selectedCount }) : t('land_trips.export_pdf') }}
                        </a>
                        <button
                            type="button"
                            class="btn btn-erp-ghost"
                            :disabled="duplicatesLoading"
                            @click="checkDuplicates"
                        >
                            {{ duplicatesLoading ? t('land_trips.duplicates_checking') : t('land_trips.check_duplicates') }}
                        </button>
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

            <div class="erp-tabs mb-3 land-hub-top-tabs">
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
                <button
                    type="button"
                    class="erp-tab"
                    :class="{ active: hubTab === 'check' }"
                    @click="hubTab = 'check'"
                >
                    {{ t('land_trips.check_tab') }}
                </button>

                <div
                    v-if="hubTab === 'cars'"
                    class="land-hub-view-toggle land-hub-view-toggle--tabs"
                    role="group"
                    :aria-label="t('land_trips.cars_view_mode')"
                >
                    <button
                        type="button"
                        class="land-hub-view-btn"
                        :class="{ 'is-active': carsViewMode === 'list' }"
                        :aria-pressed="carsViewMode === 'list'"
                        @click="carsViewMode = 'list'"
                    >
                        {{ t('land_trips.view_list') }}
                    </button>
                    <button
                        type="button"
                        class="land-hub-view-btn"
                        :class="{ 'is-active': carsViewMode === 'cmr' }"
                        :aria-pressed="carsViewMode === 'cmr'"
                        @click="carsViewMode = 'cmr'"
                    >
                        {{ t('land_trips.view_by_cmr') }}
                    </button>
                    <button
                        type="button"
                        class="land-hub-view-btn"
                        :class="{ 'is-active': carsViewMode === 'model' }"
                        :aria-pressed="carsViewMode === 'model'"
                        @click="carsViewMode = 'model'"
                    >
                        {{ t('land_trips.view_by_model') }}
                    </button>
                </div>
            </div>

            <CompanyCountryMap v-show="hubTab === 'cars'" :active="hubTab === 'cars'" :countries="countryMapRows" />

            <LandTripCarCheck v-if="hubTab === 'check'" :active="true" :company-id="company.id" />

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

                    <div class="land-hub-controls">
                        <div class="land-hub-search">
                            <LandTripSearchBar
                                v-model="carSearch"
                                input-id="land-hub-search"
                                :placeholder="t('land_trips.search_cars')"
                            />
                        </div>
                        <div class="land-hub-sort">
                            <label class="visually-hidden" for="land-hub-sort">{{ t('land_trips.sort_cars') }}</label>
                            <select
                                id="land-hub-sort"
                                v-model="filterForm.sort"
                                class="form-select form-erp-control"
                                :disabled="carsViewMode !== 'list'"
                            >
                                <option value="newest">{{ t('land_trips.sort_newest') }}</option>
                                <option value="oldest">{{ t('land_trips.sort_oldest') }}</option>
                                <option value="location">{{ t('land_trips.sort_location') }}</option>
                                <option value="sequence">{{ t('land_trips.sort_sequence') }}</option>
                            </select>
                        </div>
                        <div v-if="canManage && carsViewMode === 'list'" class="land-hub-bulk">
                            <label class="land-hub-bulk-label" for="land-hub-move">{{ t('land_trips.move_to') }}</label>
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
                                :disabled="moveForm.processing || deletingSelected || !moveForm.location_status_id || !selectedCount"
                                @click="moveSelected"
                            >
                                {{ t('land_trips.apply_selected') }}
                                <span class="land-hub-bulk-n">({{ selectedCount }})</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-erp-ghost"
                                :disabled="moveForm.processing || deletingSelected || !selectedCount"
                                @click="openBulkPrice"
                            >
                                {{ t('land_trips.bulk_price_action') }}
                                <span class="land-hub-bulk-n">({{ selectedCount }})</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-erp-ghost"
                                :disabled="moveForm.processing || deletingSelected || !selectedCount"
                                @click="openTransfer"
                            >
                                {{ t('land_trips.move_to_company') }}
                                <span class="land-hub-bulk-n">({{ selectedCount }})</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-danger"
                                :disabled="moveForm.processing || deletingSelected || !selectedCount"
                                @click="deleteSelected"
                            >
                                {{ t('common.delete') }}
                                <span class="land-hub-bulk-n">({{ selectedCount }})</span>
                            </button>
                            <InputError :message="moveForm.errors.location_status_id || moveForm.errors.car_ids || moveForm.errors.cars" />
                        </div>
                    </div>
                </div>

                <div
                    v-if="chassisLetterOCount > 0 && hubTab === 'cars'"
                    class="land-chassis-o-banner"
                    role="status"
                >
                    {{ t('land_trips.chassis_letter_o_banner', { count: chassisLetterOCount }) }}
                </div>

                <div
                    v-if="showDuplicates && hubTab === 'cars'"
                    class="land-duplicates-panel"
                    role="region"
                    :aria-label="t('land_trips.check_duplicates')"
                >
                    <div class="land-duplicates-head">
                        <div>
                            <h3 class="land-duplicates-title">{{ t('land_trips.duplicates_title') }}</h3>
                            <p class="land-duplicates-help mb-0">
                                {{ duplicatesLoading
                                    ? t('land_trips.duplicates_checking')
                                    : t('land_trips.duplicates_help', { groups: duplicateGroups.length, cars: duplicateCarCount }) }}
                            </p>
                        </div>
                        <button type="button" class="btn btn-erp-ghost btn-sm" @click="showDuplicates = false">
                            {{ t('common.close') }}
                        </button>
                    </div>
                    <p v-if="duplicatesError" class="land-duplicates-error mb-0" role="alert">{{ duplicatesError }}</p>
                    <div v-else-if="!duplicatesLoading && duplicateGroups.length === 0" class="land-duplicates-empty">
                        {{ t('land_trips.duplicates_none') }}
                    </div>
                    <div v-else class="land-duplicates-list">
                        <article
                            v-for="group in duplicateGroups"
                            :key="`${group.match}-${group.chassis_no}`"
                            class="land-duplicates-group"
                        >
                            <div class="land-duplicates-group-head">
                                <code class="land-duplicates-chassis">{{ group.match === 'last6' ? `…${group.chassis_no}` : group.chassis_no }}</code>
                                <span v-if="group.match === 'last6'" class="land-duplicates-match">{{ t('land_trips.duplicates_last6') }}</span>
                                <span class="land-duplicates-count">{{ t('land_trips.duplicates_count', { count: group.count }) }}</span>
                            </div>
                            <ul class="land-duplicates-cars">
                                <li v-for="item in group.cars" :key="item.id">
                                    <button
                                        type="button"
                                        class="land-duplicates-car-btn"
                                        @click="focusDuplicateCar(item.id)"
                                    >
                                        <span class="font-monospace">{{ item.chassis_no || '—' }}</span>
                                        <span>{{ item.model || '—' }}</span>
                                        <span>{{ item.year || '—' }}</span>
                                        <span>{{ item.location_status_label || t('land_trips.unspecified_location') }}</span>
                                    </button>
                                </li>
                            </ul>
                        </article>
                    </div>
                </div>

                <LandTripCmrGroups
                    v-if="carsViewMode === 'cmr'"
                    ref="cmrGroupsRef"
                    :company-id="company.id"
                    :can-manage="canManage"
                    :search="carSearch"
                    :location-status-id="filters.location_status_id || ''"
                    @toast="toastMessage = $event"
                    @renamed="applyFilters"
                />

                <LandTripModelGroups
                    v-else-if="carsViewMode === 'model'"
                    :company-id="company.id"
                    :search="carSearch"
                    :location-status-id="filters.location_status_id || ''"
                />

                <div
                    v-show="carsViewMode === 'list'"
                    class="table-responsive land-hub-table"
                    :class="{ 'is-loading': filtering && !loadingMore }"
                >
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
                                <th>{{ t('land_trips.model') }}</th>
                                <th>{{ t('land_trips.color') }}</th>
                                <th>{{ t('land_trips.year') }}</th>
                                <th>{{ t('land_trips.cmr_waybill') }}</th>
                                <th>{{ t('land_trips.car_price') }}</th>
                                <th>{{ t('land_trips.location_status') }}</th>
                                <th class="pe-3 text-end">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!displayedCars.length">
                                <td :colspan="canManage ? 10 : 9">
                                    <EmptyState
                                        :title="viewingArchive
                                    ? t('land_trips.empty_archive')
                                    : (String(carSearch ?? '').trim()
                                        ? t('common.no_results')
                                        : t('land_trips.empty_cars'))"
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
                                v-for="(car, index) in displayedCars"
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
                                <td>
                                    <div class="land-hub-chassis-cell">
                                        <ChassisLetterOWarning :value="car.chassis_no" />
                                        <time
                                            v-if="car.created_at"
                                            class="land-hub-entered-at"
                                            :datetime="car.created_at"
                                            :title="car.created_at_label || car.created_at"
                                        >
                                            {{ car.created_at }}
                                        </time>
                                    </div>
                                </td>
                                <td style="min-width: 8.5rem">
                                    <input
                                        v-if="canManage"
                                        type="text"
                                        class="form-control form-control-sm form-erp-control"
                                        :value="car.model || car.description || ''"
                                        :disabled="moveForm.processing || deletingSelected"
                                        :aria-label="t('land_trips.model')"
                                        @change="saveCarDetails(car, 'model', $event.target.value)"
                                    />
                                    <span v-else>{{ car.model || car.description || '—' }}</span>
                                </td>
                                <td style="min-width: 7rem">
                                    <input
                                        v-if="canManage"
                                        type="text"
                                        class="form-control form-control-sm form-erp-control"
                                        :value="car.color || ''"
                                        :disabled="moveForm.processing || deletingSelected"
                                        :aria-label="t('land_trips.color')"
                                        @change="saveCarDetails(car, 'color', $event.target.value)"
                                    />
                                    <span v-else>{{ car.color || '—' }}</span>
                                </td>
                                <td style="min-width: 5.25rem">
                                    <input
                                        v-if="canManage"
                                        type="number"
                                        min="1980"
                                        max="2100"
                                        step="1"
                                        inputmode="numeric"
                                        class="form-control form-control-sm form-erp-control land-hub-year-input"
                                        :value="car.year || ''"
                                        :disabled="moveForm.processing || deletingSelected"
                                        :aria-label="t('land_trips.year')"
                                        @change="saveCarDetails(car, 'year', $event.target.value)"
                                    />
                                    <span v-else class="tabular-nums">{{ car.year || '—' }}</span>
                                </td>
                                <td style="min-width: 7.5rem">
                                    <input
                                        v-if="canManage"
                                        type="text"
                                        class="form-control form-control-sm form-erp-control"
                                        :value="car.cmr_waybill || ''"
                                        :disabled="moveForm.processing || deletingSelected"
                                        :aria-label="t('land_trips.cmr_waybill')"
                                        maxlength="80"
                                        @change="saveCarDetails(car, 'cmr_waybill', $event.target.value)"
                                    />
                                    <span v-else>{{ car.cmr_waybill || '—' }}</span>
                                </td>
                                <td style="min-width: 7.5rem">
                                    <input
                                        v-if="canManage"
                                        type="number"
                                        min="0"
                                        step="1"
                                        inputmode="numeric"
                                        class="form-control form-control-sm form-erp-control land-hub-price-input"
                                        :value="integerPrice(car.price)"
                                        :disabled="moveForm.processing || deletingSelected"
                                        :aria-label="t('land_trips.car_price')"
                                        @change="savePrice(car, $event.target.value)"
                                    />
                                    <span v-else>{{ integerPrice(car.price) }}</span>
                                </td>
                                <td>
                                    <select
                                        v-if="canManage"
                                        class="form-select form-select-sm form-erp-control land-hub-row-select"
                                        :value="car.location_status_id || ''"
                                        :disabled="moveForm.processing || deletingSelected"
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
                                <td class="pe-3 text-end">
                                    <div class="land-hub-row-actions">
                                        <button
                                            type="button"
                                            class="btn btn-erp-ghost btn-sm land-hub-icon-btn"
                                            :aria-label="t('land_trips.view_car')"
                                            :title="t('land_trips.view_car')"
                                            @click="openViewCar(car)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="land-hub-edit-icon" aria-hidden="true">
                                                <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                                                <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="btn btn-erp-ghost btn-sm land-hub-icon-btn"
                                            :disabled="moveForm.processing || deletingSelected"
                                            :aria-label="t('land_trips.edit_car')"
                                            :title="t('land_trips.edit_car')"
                                            @click="openEditCar(car)"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="land-hub-edit-icon" aria-hidden="true">
                                                <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="carsViewMode === 'list' && (displayedCars.length || hasMoreCars)" class="land-hub-pager">
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

        <LandTripCarEditModal
            :show="!!editingCar"
            :company-id="company.id"
            :car="editingCar"
            :car-statuses="carStatuses"
            @close="editingCar = null"
            @saved="onCarEdited"
        />

        <LandTripCarViewModal
            :show="!!viewingCar"
            :car="viewingCar"
            @close="viewingCar = null"
        />

        <LandTripCarTransferModal
            :show="showTransfer"
            :company="company"
            :car-ids="selectedIds"
            @close="showTransfer = false"
            @transferred="onCarsTransferred"
        />

        <LandTripCarBulkPriceModal
            :show="showBulkPrice"
            :company-id="company.id"
            :car-ids="selectedIds"
            @close="showBulkPrice = false"
            @saved="onBulkPriceSaved"
        />
    </AppLayout>
</template>
