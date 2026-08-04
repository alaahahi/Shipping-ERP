<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    voyages: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    ships: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    ship_id: props.filters.ship_id ?? '',
});

const applyFilters = () => {
    filterForm.get(route('voyages.index'), { preserveState: true, replace: true });
};

const destroy = (voyage) => {
    if (!window.confirm(t('voyages.delete_confirm', { number: voyage.voyage_number }))) return;
    router.delete(route('voyages.destroy', voyage.id));
};
</script>

<template>
    <Head :title="t('voyages.title')" />
    <AppLayout>
        <template #header>{{ t('voyages.title') }}</template>

        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.operations')" :title="t('voyages.title')" :subtitle="t('voyages.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('voyages.create')" class="btn btn-erp">{{ t('voyages.add') }}</Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-4">
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="form-control form-erp-control"
                        :placeholder="t('voyages.search_placeholder')"
                    />
                </div>
                <div class="col-md-3">
                    <select v-model="filterForm.status" class="form-select form-erp-control">
                        <option value="">{{ t('voyages.all_statuses') }}</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select v-model="filterForm.ship_id" class="form-select form-erp-control">
                        <option value="">{{ t('voyages.all_ships') }}</option>
                        <option v-for="ship in ships" :key="ship.id" :value="ship.id">{{ ship.label }}</option>
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
                            <th class="ps-4">{{ t('voyages.number') }}</th>
                            <th>{{ t('ships.name') }}</th>
                            <th>{{ t('voyages.sailing') }}</th>
                            <th>{{ t('voyages.route') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="voyages.data.length === 0">
                            <td colspan="6">
                                <EmptyState icon="V">{{ t('voyages.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="voyage in voyages.data" :key="voyage.id">
                            <td class="ps-4">
                                <Link :href="route('voyages.show', voyage.id)" class="fw-semibold text-decoration-none">
                                    {{ voyage.voyage_number }}
                                </Link>
                                <div v-if="voyage.captain" class="small text-secondary">{{ voyage.captain }}</div>
                            </td>
                            <td>{{ voyage.ship?.name || '—' }}</td>
                            <td>{{ voyage.sailing_date }}</td>
                            <td>
                                <span>{{ voyage.pol || '—' }}</span>
                                <span class="text-secondary mx-1">→</span>
                                <span>{{ voyage.pod || '—' }}</span>
                            </td>
                            <td>
                                <StatusBadge :tone="voyage.status_tone" :label="voyage.status_label" />
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-2">
                                    <Link :href="route('voyages.show', voyage.id)" class="btn btn-sm btn-erp-ghost">
                                        {{ t('common.open') }}
                                    </Link>
                                    <Link
                                        v-if="canManage && voyage.is_editable"
                                        :href="route('voyages.edit', voyage.id)"
                                        class="btn btn-sm btn-erp-ghost"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        v-if="canManage && voyage.is_editable"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroy(voyage)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
