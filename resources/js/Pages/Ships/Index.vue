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
    ships: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const filterForm = useForm({
    search: props.filters.search ?? '',
    active: props.filters.active ?? '',
});

const applyFilters = () => {
    filterForm.get(route('ships.index'), { preserveState: true, replace: true });
};

const destroy = (ship) => {
    if (!window.confirm(t('ships.delete_confirm', { name: ship.name }))) return;
    router.delete(route('ships.destroy', ship.id));
};
</script>

<template>
    <Head :title="t('ships.title')" />
    <AppLayout>
        <template #header>{{ t('ships.title') }}</template>

        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.operations')" :title="t('ships.title')" :subtitle="t('ships.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('ships.create')" class="btn btn-erp">{{ t('ships.add') }}</Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-5">
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="form-control form-erp-control"
                        :placeholder="t('ships.search_placeholder')"
                    />
                </div>
                <div class="col-md-4">
                    <select v-model="filterForm.active" class="form-select form-erp-control">
                        <option value="">{{ t('ships.all_status') }}</option>
                        <option value="1">{{ t('common.active') }}</option>
                        <option value="0">{{ t('common.inactive') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-erp-ghost w-100">{{ t('common.filter') }}</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('ships.name') }}</th>
                            <th>{{ t('ships.flag') }}</th>
                            <th>{{ t('ships.imo') }}</th>
                            <th>{{ t('ships.captain') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th>{{ t('ships.voyages') }}</th>
                            <th>{{ t('ship_owners.title') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="ships.data.length === 0">
                            <td colspan="8">
                                <EmptyState icon="S">{{ t('ships.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="ship in ships.data" :key="ship.id">
                            <td class="ps-4">
                                <Link :href="route('ships.show', ship.id)" class="fw-semibold text-decoration-none">
                                    {{ ship.name }}
                                </Link>
                                <div v-if="ship.call_sign" class="small text-secondary">{{ ship.call_sign }}</div>
                            </td>
                            <td>{{ ship.flag || '—' }}</td>
                            <td class="font-monospace small">{{ ship.imo_number || '—' }}</td>
                            <td>{{ ship.default_captain || '—' }}</td>
                            <td>
                                <StatusBadge
                                    :tone="ship.is_active ? 'success' : 'neutral'"
                                    :label="ship.is_active ? t('common.active') : t('common.inactive')"
                                />
                            </td>
                            <td>{{ ship.voyages_count }}</td>
                            <td>
                                <Link
                                    :href="route('ships.show', { ship: ship.id, tab: 'owners' })"
                                    class="text-decoration-none fw-semibold"
                                >
                                    {{ ship.owners_count ?? 0 }}
                                </Link>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                    <Link
                                        v-if="canManage"
                                        :href="route('ships.show', { ship: ship.id, tab: 'owners' })"
                                        class="btn btn-sm btn-erp"
                                    >
                                        {{ t('ship_owners.manage') }}
                                    </Link>
                                    <Link :href="route('ships.show', ship.id)" class="btn btn-sm btn-erp-ghost">
                                        {{ t('common.open') }}
                                    </Link>
                                    <Link
                                        v-if="canManage"
                                        :href="route('ships.edit', ship.id)"
                                        class="btn btn-sm btn-erp-ghost"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroy(ship)"
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
