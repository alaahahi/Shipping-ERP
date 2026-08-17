<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import LandTripSearchBar from '@/Components/LandTrips/LandTripSearchBar.vue';
import { statusSurfaceStyle } from '@/composables/useLandTripStatusColor';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companies: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const search = ref(props.filters.search ?? '');
const searching = ref(false);
let searchTimer = null;
let searchVisit = 0;

const normalizedSearch = () => String(search.value ?? '').trim();
const appliedSearch = () => String(props.filters.search ?? '').trim();

watch(
    () => props.filters.search,
    (value) => {
        const next = value ?? '';
        if (next !== search.value) {
            search.value = next;
        }
    },
);

const applySearch = () => {
    const value = normalizedSearch();
    if (value === appliedSearch()) {
        searching.value = false;
        return;
    }

    const visit = ++searchVisit;
    router.get(
        route('land-trips.index'),
        { search: value || undefined },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            only: ['companies', 'filters'],
            onStart: () => {
                if (visit === searchVisit) {
                    searching.value = true;
                }
            },
            onFinish: () => {
                if (visit === searchVisit) {
                    searching.value = false;
                }
            },
        },
    );
};

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applySearch, 300);
});

onBeforeUnmount(() => clearTimeout(searchTimer));

const matchesCompany = (company, query) => {
    if (!query) {
        return true;
    }

    const hay = [
        company.name,
        company.contact_name,
        company.contact_phone,
        company.matched_car?.chassis_no,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return hay.includes(query);
};

const visibleCompanies = computed(() => {
    const rows = props.companies.data ?? [];
    const query = normalizedSearch().toLowerCase();
    if (!query) {
        return rows;
    }

    const local = rows.filter((company) => matchesCompany(company, query));
    if (local.length === 0 && searching.value) {
        return rows;
    }

    return local;
});

const emptyMessage = computed(() => {
    if (searching.value && normalizedSearch() !== '') {
        return t('land_trips.check_searching');
    }
    if (normalizedSearch() !== '') {
        return t('common.no_results');
    }

    return t('land_trips.no_companies');
});

const cardStyle = (company) => statusSurfaceStyle(company.card_color || '#0F766E', { solid: true });

const companyHref = (company) => {
    if (company.matched_car?.id) {
        return route('land-trips.companies.show', {
            company: company.id,
            highlight: company.matched_car.id,
        });
    }

    return route('land-trips.companies.show', company.id);
};
</script>

<template>
    <Head :title="t('land_trips.title')" />
    <AppLayout>
        <template #header>{{ t('land_trips.title') }}</template>
        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.operations')" :title="t('land_trips.title')" :subtitle="t('land_trips.companies_help')" />

        <LandTripSearchBar
            v-model="search"
            input-id="land-trips-company-search"
            :placeholder="t('land_trips.search_companies')"
            :searching="searching"
        />

        <EmptyState v-if="!visibleCompanies.length" icon="C">{{ emptyMessage }}</EmptyState>

        <div v-else class="row g-3" :class="{ 'land-live-search-results--busy': searching }">
            <div v-for="company in visibleCompanies" :key="company.id" class="col-6 col-md-4 col-xl-3">
                <Link :href="companyHref(company)" class="text-decoration-none">
                    <div class="erp-stat is-clickable land-company-card h-100" :style="cardStyle(company)">
                        <div class="erp-stat-label">{{ company.name }}</div>
                        <div class="erp-stat-value">{{ company.cars_count }}</div>
                        <div class="erp-stat-hint">{{ t('land_trips.cars') }}</div>
                        <div v-if="company.matched_car?.chassis_no" class="erp-stat-hint">
                            <ChassisLetterOWarning :value="company.matched_car.chassis_no" />
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <div v-if="companies.links?.length > 3" class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, index) in companies.links"
                :key="index"
                :href="link.url || undefined"
                class="btn btn-sm"
                :class="link.active ? 'btn-erp' : 'btn-erp-ghost'"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
