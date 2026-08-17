<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import LandTripSearchBar from '@/Components/LandTrips/LandTripSearchBar.vue';
import { statusSurfaceStyle } from '@/composables/useLandTripStatusColor';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companies: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const search = ref('');
const remoteCompanies = ref(null);
const searching = ref(false);
let searchTimer = null;
let searchRequest = 0;

const stripSearchFromHref = (href) => {
    if (!href) {
        return href;
    }

    try {
        const url = new URL(href, window.location.origin);
        if (!url.searchParams.has('search')) {
            return href;
        }
        url.searchParams.delete('search');
        const query = url.searchParams.toString();

        return `${url.pathname}${query ? `?${query}` : ''}${url.hash}`;
    } catch {
        return href;
    }
};

const stripSearchFromAddressBar = () => {
    const next = stripSearchFromHref(window.location.href);
    if (!next || next === window.location.href) {
        return;
    }

    const url = new URL(next, window.location.origin);
    const path = `${url.pathname}${url.search}${url.hash}`;
    const state = window.history.state;
    if (state && typeof state === 'object' && state.page && typeof state.page === 'object') {
        window.history.replaceState(
            { ...state, page: { ...state.page, url: `${url.pathname}${url.search}` } },
            '',
            path,
        );
        return;
    }
    window.history.replaceState(state, '', path);
};

const normalizedSearch = () => String(search.value ?? '').trim().toLowerCase();

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
    if (remoteCompanies.value !== null) {
        return remoteCompanies.value;
    }

    const rows = props.companies.data ?? [];
    const query = normalizedSearch();
    if (!query) {
        return rows;
    }

    return rows.filter((company) => matchesCompany(company, query));
});

const emptyMessage = computed(() => {
    if (normalizedSearch() !== '') {
        return t('common.no_results');
    }

    return t('land_trips.no_companies');
});

const companyPageLinks = computed(() => (props.companies.links ?? []).map((link) => ({
    ...link,
    url: stripSearchFromHref(link.url),
})));

const cardStyle = (company) => statusSurfaceStyle(company.card_color || '#0F766E', { solid: true });

const companyHref = (company) => {
    const base = route('land-trips.companies.show', company.id);
    const params = new URLSearchParams();
    const query = String(search.value ?? '').trim();
    const matchedId = company.matched_car?.id;

    if (matchedId) {
        params.set('highlight', String(matchedId));
    }
    if (query !== '') {
        params.set('search', query);
    }

    const qs = params.toString();

    return qs ? `${base}?${qs}` : base;
};

const runCompanySearch = async () => {
    const query = String(search.value ?? '').trim();
    if (query === '') {
        remoteCompanies.value = null;
        searching.value = false;
        return;
    }

    const requestId = ++searchRequest;
    searching.value = true;

    try {
        const { data } = await axios.get(route('land-trips.search-companies'), {
            params: { search: query },
        });
        if (requestId !== searchRequest) {
            return;
        }
        remoteCompanies.value = data.companies ?? [];
    } catch {
        if (requestId !== searchRequest) {
            return;
        }
        remoteCompanies.value = [];
    } finally {
        if (requestId === searchRequest) {
            searching.value = false;
        }
    }
};

watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    if (String(search.value ?? '').trim() === '') {
        searchRequest += 1;
        remoteCompanies.value = null;
        searching.value = false;
        stripSearchFromAddressBar();
        return;
    }

    searchTimer = setTimeout(() => {
        runCompanySearch();
    }, 280);
});

onMounted(() => {
    stripSearchFromAddressBar();
});

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});
</script>

<template>
    <Head :title="t('land_trips.title')" />
    <AppLayout>
        <template #header>{{ t('land_trips.title') }}</template>
        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.operations')" :title="t('land_trips.title')" :subtitle="t('land_trips.companies_help')" />

        <div class="land-filter-compact mb-3">
            <LandTripSearchBar
                v-model="search"
                input-id="land-trips-company-search"
                :placeholder="t('land_trips.search_companies')"
            />
        </div>

        <EmptyState v-if="!visibleCompanies.length && !searching" icon="C">{{ emptyMessage }}</EmptyState>

        <div v-else class="row g-3">
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

        <div
            v-if="!normalizedSearch() && companyPageLinks.length > 3"
            class="mt-3 d-flex flex-wrap gap-2 justify-content-center"
        >
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, index) in companyPageLinks"
                :key="index"
                :href="link.url || undefined"
                class="btn btn-sm"
                :class="link.active ? 'btn-erp' : 'btn-erp-ghost'"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
