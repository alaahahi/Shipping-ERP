<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    trips: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    company_id: props.filters.company_id ?? '',
});

const applyFilters = () => {
    filterForm.get(route('land-trips.index'), { preserveState: true, replace: true });
};

const destroy = (trip) => {
    if (!window.confirm(t('land_trips.delete_confirm', { cmr: trip.cmr_number }))) return;
    router.delete(route('land-trips.destroy', trip.id));
};
</script>

<template>
    <Head :title="t('land_trips.title')" />
    <AppLayout>
        <template #header>{{ t('land_trips.title') }}</template>
        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.operations')" :title="t('land_trips.title')" :subtitle="t('land_trips.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('land-trips.create')" class="btn btn-erp">
                    {{ t('land_trips.add') }}
                </Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-4">
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="form-control form-erp-control"
                        :placeholder="t('land_trips.search')"
                    />
                </div>
                <div class="col-md-3">
                    <select v-model="filterForm.status" class="form-select form-erp-control">
                        <option value="">{{ t('common.all') }}</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select v-model="filterForm.company_id" class="form-select form-erp-control">
                        <option value="">{{ t('common.all') }}</option>
                        <option v-for="company in companies" :key="company.id" :value="company.id">
                            {{ company.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-erp-ghost w-100">{{ t('common.filter') }}</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('land_trips.cmr') }}</th>
                            <th>{{ t('land_trips.driver') }}</th>
                            <th>{{ t('land_trips.route') }}</th>
                            <th>{{ t('common.date') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end">{{ t('land_trips.freight') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="trips.data.length === 0">
                            <td colspan="7">
                                <EmptyState icon="T">{{ t('land_trips.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="trip in trips.data" :key="trip.id">
                            <td class="ps-4 fw-semibold">
                                <Link :href="route('land-trips.show', trip.id)" class="text-decoration-none">
                                    {{ trip.cmr_number }}
                                </Link>
                                <div class="small text-secondary">{{ trip.cars_count }} {{ t('land_trips.cars') }}</div>
                            </td>
                            <td>{{ trip.driver_name }}</td>
                            <td>{{ trip.route }}</td>
                            <td>{{ trip.departure_date }}</td>
                            <td>
                                <StatusBadge :tone="trip.status_tone" :label="trip.status_label" />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="trip.freight_amount" :currency="trip.currency" show-zero />
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-2">
                                    <Link :href="route('land-trips.show', trip.id)" class="btn btn-sm btn-erp-ghost">
                                        {{ t('common.open') }}
                                    </Link>
                                    <Link
                                        v-if="canManage && trip.is_editable"
                                        :href="route('land-trips.edit', trip.id)"
                                        class="btn btn-sm btn-erp-ghost"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        v-if="canManage && trip.is_editable"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroy(trip)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="trips.links?.length > 3" class="p-3 border-top d-flex flex-wrap gap-2 justify-content-center">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, index) in trips.links"
                    :key="index"
                    :href="link.url || undefined"
                    class="btn btn-sm"
                    :class="link.active ? 'btn-erp' : 'btn-erp-ghost'"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
