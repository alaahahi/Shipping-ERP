<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({ unsold: 0, sold: 0 }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    borders: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const saleState = computed(() => props.filters.sale_state || 'unsold');
const isSoldTab = computed(() => saleState.value === 'sold');
const hasCars = computed(() => props.groups.some((group) => group.cars?.length));
const sellingCar = ref(null);

const filterForm = useForm({
    search: props.filters.search ?? '',
    company_id: props.filters.company_id ?? '',
    border: props.filters.border ?? '',
    remaining_only: Boolean(props.filters.remaining_only),
});

const sellForm = useForm({
    sale_price: '',
    sold_at: new Date().toISOString().slice(0, 10),
    notes: '',
});

const query = (extra = {}) => ({
    sale_state: saleState.value,
    search: filterForm.search,
    company_id: filterForm.company_id,
    border: filterForm.border,
    remaining_only: isSoldTab.value && filterForm.remaining_only ? 1 : '',
    ...extra,
});

const applyFilters = () => {
    router.get(route('iran-cars.index'), query(), { preserveState: true, replace: true });
};

const switchTab = (state) => {
    router.get(route('iran-cars.index'), query({ sale_state: state, remaining_only: state === 'sold' && filterForm.remaining_only ? 1 : '' }), {
        preserveState: true,
        replace: true,
    });
};

const exportUrl = computed(() => route('iran-cars.export', query()));
const printUrl = computed(() => route('iran-cars.print', query()));
const importUrl = computed(() => route('iran-cars.import', { sale_state: saleState.value }));

const destroy = (car) => {
    if (!window.confirm(t('iran_cars.delete_confirm', { vin: car.vin }))) return;
    router.delete(route('iran-cars.destroy', car.id));
};

const openSell = (car) => {
    sellingCar.value = car;
    sellForm.sale_price = car.total_amount || '';
    sellForm.sold_at = new Date().toISOString().slice(0, 10);
    sellForm.notes = '';
    sellForm.clearErrors();
};

const closeSell = () => {
    sellingCar.value = null;
};

const submitSell = () => {
    if (!sellingCar.value) return;
    sellForm.post(route('iran-cars.sell', sellingCar.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            sellingCar.value = null;
        },
    });
};

const colCount = computed(() => (isSoldTab.value ? 11 : 9));
</script>

<template>
    <Head :title="t('iran_cars.title')" />
    <AppLayout>
        <template #header>{{ t('iran_cars.title') }}</template>
        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.finance')" :title="t('iran_cars.title')" :subtitle="t('iran_cars.help')">
            <template #actions>
                <a :href="printUrl" class="btn btn-erp-ghost">{{ t('common.print') }}</a>
                <a :href="exportUrl" class="btn btn-erp-ghost">{{ t('iran_cars.export') }}</a>
                <Link v-if="canManage" :href="importUrl" class="btn btn-erp-ghost">
                    {{ t('iran_cars.import') }}
                </Link>
                <Link v-if="canManage && !isSoldTab" :href="route('iran-cars.create')" class="btn btn-erp">
                    {{ t('iran_cars.add') }}
                </Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <div class="erp-tabs">
                <button
                    type="button"
                    class="erp-tab"
                    :class="{ active: !isSoldTab }"
                    @click="switchTab('unsold')"
                >
                    {{ t('iran_cars.unsold') }}
                    <span class="erp-tab-count">{{ counts.unsold }}</span>
                </button>
                <button
                    type="button"
                    class="erp-tab"
                    :class="{ active: isSoldTab }"
                    @click="switchTab('sold')"
                >
                    {{ t('iran_cars.sold') }}
                    <span class="erp-tab-count">{{ counts.sold }}</span>
                </button>
            </div>

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
                <div v-if="isSoldTab" class="col-md-2">
                    <label class="form-check d-flex align-items-center gap-2 mb-0 py-2">
                        <input v-model="filterForm.remaining_only" type="checkbox" class="form-check-input" />
                        <span class="small">{{ t('iran_cars.remaining_only') }}</span>
                    </label>
                </div>
                <div :class="isSoldTab ? 'col-md-2' : 'col-md-4'">
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
                            <th v-if="!isSoldTab" class="text-end">{{ t('iran_cars.list_price') }}</th>
                            <template v-else>
                                <th class="text-end">{{ t('iran_cars.sale_price') }}</th>
                                <th class="text-end">{{ t('iran_cars.paid') }}</th>
                                <th class="text-end">{{ t('iran_cars.remaining') }}</th>
                            </template>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!hasCars">
                            <td :colspan="colCount">
                                <EmptyState icon="C">{{ isSoldTab ? t('iran_cars.none_sold') : t('iran_cars.none') }}</EmptyState>
                            </td>
                        </tr>
                        <template v-for="group in groups" :key="group.border">
                            <tr class="table-light">
                                <td :colspan="colCount" class="ps-4 fw-semibold py-2">
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
                                        <StatusBadge
                                            :tone="isSoldTab ? car.status_tone : car.sale_state_tone"
                                            :label="isSoldTab ? car.status_label : t('iran_cars.unsold')"
                                        />
                                    </div>
                                </td>
                                <td>{{ car.year || '—' }}</td>
                                <td>{{ car.color || '—' }}</td>
                                <td class="font-monospace">{{ car.vin }}</td>
                                <td>{{ car.company_name }}</td>
                                <td v-if="!isSoldTab" class="text-end">
                                    <MoneyAmount :value="car.total_amount" :currency="car.currency" show-zero />
                                </td>
                                <template v-else>
                                    <td class="text-end">
                                        <MoneyAmount :value="car.sale_price" :currency="car.currency" show-zero />
                                    </td>
                                    <td class="text-end">
                                        <MoneyAmount :value="car.paid_amount" :currency="car.currency" show-zero />
                                    </td>
                                    <td class="text-end fw-semibold">
                                        <MoneyAmount :value="car.remaining_amount" :currency="car.currency" show-zero />
                                    </td>
                                </template>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                        <Link :href="route('iran-cars.show', car.id)" class="btn btn-sm btn-erp-ghost">
                                            {{ t('common.open') }}
                                        </Link>
                                        <button
                                            v-if="canManage && !isSoldTab"
                                            type="button"
                                            class="btn btn-sm btn-erp"
                                            @click="openSell(car)"
                                        >
                                            {{ t('iran_cars.move_to_sold') }}
                                        </button>
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

        <Modal :show="Boolean(sellingCar)" max-width="md" @close="closeSell">
            <div class="p-4">
                <h3 class="erp-panel-title mb-1">{{ t('iran_cars.move_to_sold') }}</h3>
                <p class="small text-secondary mb-3">
                    {{ sellingCar?.model_name }} · {{ sellingCar?.vin }}
                </p>
                <form class="row g-3" @submit.prevent="submitSell">
                    <div class="col-md-6">
                        <label class="form-erp-label">{{ t('iran_cars.sale_price') }}</label>
                        <input v-model="sellForm.sale_price" type="number" min="0" step="0.01" class="form-control form-erp-control" required />
                        <InputError :message="sellForm.errors.sale_price" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-erp-label">{{ t('iran_cars.sold_at') }}</label>
                        <input v-model="sellForm.sold_at" type="date" class="form-control form-erp-control" required />
                        <InputError :message="sellForm.errors.sold_at" />
                    </div>
                    <div class="col-12">
                        <label class="form-erp-label">{{ t('common.notes') }}</label>
                        <input v-model="sellForm.notes" class="form-control form-erp-control" />
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-erp-ghost" @click="closeSell">{{ t('common.cancel') }}</button>
                        <button type="submit" class="btn btn-erp" :disabled="sellForm.processing">
                            {{ sellForm.processing ? t('common.saving') : t('iran_cars.confirm_sale') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
