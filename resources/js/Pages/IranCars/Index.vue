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
    groups: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    borders: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const hasCars = computed(() => props.groups.some((group) => group.cars?.length));

const filterForm = useForm({
    search: props.filters.search ?? '',
    company_id: props.filters.company_id ?? '',
    border: props.filters.border ?? '',
    remaining_only: Boolean(props.filters.remaining_only),
});

const applyFilters = () => {
    router.get(route('iran-cars.index'), {
        search: filterForm.search,
        company_id: filterForm.company_id,
        border: filterForm.border,
        remaining_only: filterForm.remaining_only ? 1 : '',
    }, { preserveState: true, replace: true });
};

const exportUrl = computed(() => route('iran-cars.export', {
    search: filterForm.search,
    company_id: filterForm.company_id,
    border: filterForm.border,
    remaining_only: filterForm.remaining_only ? 1 : '',
}));

const destroy = (car) => {
    if (!window.confirm(t('iran_cars.delete_confirm', { vin: car.vin }))) return;
    router.delete(route('iran-cars.destroy', car.id));
};
</script>

<template>
    <Head :title="t('iran_cars.title')" />
    <AppLayout>
        <template #header>{{ t('iran_cars.title') }}</template>
        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.finance')" :title="t('iran_cars.title')" :subtitle="t('iran_cars.help')">
            <template #actions>
                <a :href="exportUrl" class="btn btn-erp-ghost">{{ t('iran_cars.export') }}</a>
                <Link v-if="canManage" :href="route('iran-cars.import')" class="btn btn-erp-ghost">
                    {{ t('iran_cars.import') }}
                </Link>
                <Link v-if="canManage" :href="route('iran-cars.create')" class="btn btn-erp">
                    {{ t('iran_cars.add') }}
                </Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0 align-items-end" @submit.prevent="applyFilters">
                <div class="col-md-3">
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="form-control form-erp-control"
                        :placeholder="t('iran_cars.search')"
                    />
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
                    <select v-model="filterForm.border" class="form-select form-erp-control">
                        <option value="">{{ t('common.all') }}</option>
                        <option v-for="border in borders" :key="border.value" :value="border.value">
                            {{ border.label }}
                        </option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-check d-flex align-items-center gap-2 mb-0 py-2">
                        <input v-model="filterForm.remaining_only" type="checkbox" class="form-check-input" />
                        <span class="small">{{ t('iran_cars.remaining_only') }}</span>
                    </label>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-erp-ghost w-100">{{ t('common.filter') }}</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 4rem">#</th>
                            <th>{{ t('iran_cars.model') }}</th>
                            <th>{{ t('iran_cars.year') }}</th>
                            <th>{{ t('iran_cars.color') }}</th>
                            <th>{{ t('iran_cars.vin') }}</th>
                            <th>{{ t('iran_cars.company') }}</th>
                            <th class="text-end">{{ t('iran_cars.total') }}</th>
                            <th class="text-end">{{ t('iran_cars.paid') }}</th>
                            <th class="text-end">{{ t('iran_cars.remaining') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!hasCars">
                            <td colspan="10">
                                <EmptyState icon="C">{{ t('iran_cars.none') }}</EmptyState>
                            </td>
                        </tr>
                        <template v-for="group in groups" :key="group.border">
                            <tr class="table-light">
                                <td colspan="10" class="ps-4 fw-semibold py-2">
                                    {{ group.label }}
                                    <span class="small text-secondary ms-2">{{ group.count }}</span>
                                </td>
                            </tr>
                            <tr v-for="car in group.cars" :key="car.id">
                                <td class="ps-4 text-secondary">{{ car.index }}</td>
                                <td>
                                    <Link :href="route('iran-cars.show', car.id)" class="text-decoration-none fw-semibold">
                                        {{ car.model_name }}
                                    </Link>
                                    <div class="small">
                                        <StatusBadge :tone="car.status_tone" :label="car.status_label" />
                                    </div>
                                </td>
                                <td>{{ car.year || '—' }}</td>
                                <td>{{ car.color || '—' }}</td>
                                <td class="font-monospace">{{ car.vin }}</td>
                                <td>{{ car.company_name }}</td>
                                <td class="text-end">
                                    <MoneyAmount :value="car.total_amount" :currency="car.currency" show-zero />
                                </td>
                                <td class="text-end">
                                    <MoneyAmount :value="car.paid_amount" :currency="car.currency" show-zero />
                                </td>
                                <td class="text-end fw-semibold">
                                    <MoneyAmount :value="car.remaining_amount" :currency="car.currency" show-zero />
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-2">
                                        <Link :href="route('iran-cars.show', car.id)" class="btn btn-sm btn-erp-ghost">
                                            {{ t('common.open') }}
                                        </Link>
                                        <Link
                                            v-if="canManage"
                                            :href="route('iran-cars.edit', car.id)"
                                            class="btn btn-sm btn-erp-ghost"
                                        >
                                            {{ t('common.edit') }}
                                        </Link>
                                        <button
                                            v-if="canManage && !car.is_total_locked"
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            @click="destroy(car)"
                                        >
                                            {{ t('common.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
