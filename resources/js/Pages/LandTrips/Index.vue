<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
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
let searchTimer = null;

watch(
    () => props.filters.search,
    (value) => {
        if (value !== search.value) {
            search.value = value ?? '';
        }
    },
);

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            route('land-trips.index'),
            { search: value || undefined },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 350);
});

onBeforeUnmount(() => clearTimeout(searchTimer));

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

        <div class="land-filter-compact mb-3">
            <input
                v-model="search"
                type="search"
                class="form-control form-erp-control"
                :placeholder="t('land_trips.search_companies')"
            />
        </div>

        <EmptyState v-if="!companies.data?.length" icon="C">{{ t('land_trips.no_companies') }}</EmptyState>

        <div v-else class="row g-3">
            <div v-for="company in companies.data" :key="company.id" class="col-6 col-md-4 col-xl-3">
                <Link :href="companyHref(company)" class="text-decoration-none">
                    <div class="erp-stat is-clickable land-company-card h-100" :style="cardStyle(company)">
                        <div class="erp-stat-label">{{ company.name }}</div>
                        <div class="erp-stat-value">{{ company.cars_count }}</div>
                        <div class="erp-stat-hint">{{ t('land_trips.cars') }}</div>
                        <div v-if="company.matched_car?.chassis_no" class="erp-stat-hint font-monospace">
                            {{ company.matched_car.chassis_no }}
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
