<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StatusStepper from '@/Components/StatusStepper.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    voyage: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
    companyOptions: { type: Array, default: () => [] },
    cars: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    expenseTotals: { type: Array, default: () => [] },
    expenseTypes: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    paymentAccounts: { type: Object, default: () => ({ USD: [], AED: [] }) },
    settlements: {
        type: Object,
        default: () => ({
            companies: [],
            consignees: [],
            owners: [],
            summary: {},
        }),
    },
    settlementPosting: {
        type: Object,
        default: () => ({
            revenue_posted: false,
            can_post_revenue: false,
            commission_posted: false,
            can_post_commission: false,
        }),
    },
    companyMovements: { type: Array, default: () => [] },
    importPreview: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    canPostAccounting: { type: Boolean, default: false },
    transitions: { type: Array, default: () => [] },
    statusSteps: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const urlTab = new URL(page.url, window.location.origin).searchParams.get('tab');
const activeTab = ref(props.importPreview ? 'cars' : (urlTab || 'cars'));
const editingCompanyId = ref(null);
const editingCarId = ref(null);
const editingExpenseId = ref(null);
const postingExpenseId = ref(null);
const showCommissionPost = ref(false);
const createNewCompany = ref(props.companyOptions.length === 0);

const companyForm = useForm({
    company_id: props.companyOptions[0]?.id ?? null,
    company_name: '',
    contact_name: '',
    contact_phone: '',
    shipping_price_per_car: 0,
    shipping_price_aed: 0,
    clearance_per_car: 40,
    notes: '',
});

const revenuePostForm = useForm({});
const commissionPostForm = useForm({
    payment_account_id: props.paymentAccounts.AED?.[0]?.id ?? null,
});

const carForm = useForm({
    voyage_company_id: props.companies[0]?.id ?? null,
    chassis_no: '',
    consignee_name: '',
    shipper_name: '',
    description: '',
    weight: null,
    code: '',
});

const importForm = useForm({
    voyage_company_id: props.companies[0]?.id ?? null,
    file: null,
});

const expenseForm = useForm({
    expense_type: props.expenseTypes[0]?.value ?? 'other',
    amount: 0,
    currency: 'USD',
    expense_date: new Date().toISOString().slice(0, 10),
    vendor: '',
    reference: '',
    notes: '',
});

const postForm = useForm({
    payment_account_id: null,
});

const transition = (status) => {
    if (!window.confirm(t('voyages.transition_confirm', { status }))) return;
    router.post(route('voyages.transition', props.voyage.id), { status });
};

const resetCompanyForm = () => {
    editingCompanyId.value = null;
    createNewCompany.value = props.companyOptions.length === 0;
    companyForm.reset();
    companyForm.clearErrors();
    companyForm.company_id = props.companyOptions[0]?.id ?? null;
    companyForm.clearance_per_car = 40;
};

const startEditCompany = (company) => {
    activeTab.value = 'companies';
    editingCompanyId.value = company.id;
    createNewCompany.value = false;
    companyForm.company_id = company.company_id;
    companyForm.company_name = company.company_name;
    companyForm.contact_name = company.contact_name ?? '';
    companyForm.contact_phone = company.contact_phone ?? '';
    companyForm.shipping_price_per_car = Number(company.shipping_price_per_car);
    companyForm.shipping_price_aed = Number(company.shipping_price_aed);
    companyForm.clearance_per_car = Number(company.clearance_per_car);
    companyForm.notes = company.notes ?? '';
};

const submitCompany = () => {
    if (editingCompanyId.value) {
        companyForm.transform(() => ({
            shipping_price_per_car: companyForm.shipping_price_per_car,
            shipping_price_aed: companyForm.shipping_price_aed,
            clearance_per_car: companyForm.clearance_per_car,
            notes: companyForm.notes || null,
        })).put(route('voyages.companies.update', [props.voyage.id, editingCompanyId.value]), {
            preserveScroll: true,
            onSuccess: () => resetCompanyForm(),
        });
        return;
    }

    companyForm.transform((data) => {
        if (createNewCompany.value) {
            return {
                company_name: data.company_name,
                contact_name: data.contact_name || null,
                contact_phone: data.contact_phone || null,
                shipping_price_per_car: data.shipping_price_per_car,
                shipping_price_aed: data.shipping_price_aed,
                clearance_per_car: data.clearance_per_car,
                notes: data.notes || null,
            };
        }

        return {
            company_id: data.company_id,
            shipping_price_per_car: data.shipping_price_per_car,
            shipping_price_aed: data.shipping_price_aed,
            clearance_per_car: data.clearance_per_car,
            notes: data.notes || null,
        };
    }).post(route('voyages.companies.store', props.voyage.id), {
        preserveScroll: true,
        onSuccess: () => resetCompanyForm(),
    });
};

const removeCompany = (company) => {
    if (!window.confirm(t('voyage_companies.delete_confirm', { name: company.company_name }))) return;
    router.delete(route('voyages.companies.destroy', [props.voyage.id, company.id]), {
        preserveScroll: true,
    });
};

const resetCarForm = () => {
    editingCarId.value = null;
    carForm.reset();
    carForm.clearErrors();
    carForm.voyage_company_id = props.companies[0]?.id ?? null;
};

const startEditCar = (car) => {
    activeTab.value = 'cars';
    editingCarId.value = car.id;
    carForm.voyage_company_id = car.voyage_company_id;
    carForm.chassis_no = car.chassis_no ?? '';
    carForm.consignee_name = car.consignee_name;
    carForm.shipper_name = car.shipper_name ?? '';
    carForm.description = car.description ?? '';
    carForm.weight = car.weight ? Number(car.weight) : null;
    carForm.code = car.code ?? '';
};

const submitCar = () => {
    if (editingCarId.value) {
        carForm.put(route('voyages.cars.update', [props.voyage.id, editingCarId.value]), {
            preserveScroll: true,
            onSuccess: () => resetCarForm(),
        });
        return;
    }

    carForm.post(route('voyages.cars.store', props.voyage.id), {
        preserveScroll: true,
        onSuccess: () => resetCarForm(),
    });
};

const removeCar = (car) => {
    if (!window.confirm(t('voyage_cars.delete_confirm', { chassis: car.chassis_no || car.consignee_name }))) return;
    router.delete(route('voyages.cars.destroy', [props.voyage.id, car.id]), {
        preserveScroll: true,
    });
};

const onImportFile = (event) => {
    importForm.file = event.target.files?.[0] ?? null;
};

const submitImportPreview = () => {
    importForm.post(route('voyages.cars.import-preview', props.voyage.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            activeTab.value = 'cars';
            const input = document.getElementById('voyage-car-import');
            if (input) input.value = '';
            importForm.reset('file');
        },
    });
};

const confirmImport = (runAsync = false) => {
    if (!props.importPreview?.company_id) return;
    router.post(
        route('voyages.cars.import-confirm', props.voyage.id),
        {
            voyage_company_id: props.importPreview.company_id,
            run_async: runAsync,
        },
        { preserveScroll: true },
    );
};

const resetExpenseForm = () => {
    editingExpenseId.value = null;
    expenseForm.reset();
    expenseForm.clearErrors();
    expenseForm.expense_type = props.expenseTypes[0]?.value ?? 'other';
    expenseForm.currency = 'USD';
    expenseForm.expense_date = new Date().toISOString().slice(0, 10);
};

const startEditExpense = (expense) => {
    activeTab.value = 'expenses';
    editingExpenseId.value = expense.id;
    expenseForm.expense_type = expense.expense_type;
    expenseForm.amount = Number(expense.amount);
    expenseForm.currency = expense.currency;
    expenseForm.expense_date = expense.expense_date;
    expenseForm.vendor = expense.vendor ?? '';
    expenseForm.reference = expense.reference ?? '';
    expenseForm.notes = expense.notes ?? '';
};

const submitExpense = () => {
    if (editingExpenseId.value) {
        expenseForm.put(route('voyages.expenses.update', [props.voyage.id, editingExpenseId.value]), {
            preserveScroll: true,
            onSuccess: () => resetExpenseForm(),
        });
        return;
    }

    expenseForm.post(route('voyages.expenses.store', props.voyage.id), {
        preserveScroll: true,
        onSuccess: () => resetExpenseForm(),
    });
};

const removeExpense = (expense) => {
    if (!window.confirm(t('voyage_expenses.delete_confirm'))) return;
    router.delete(route('voyages.expenses.destroy', [props.voyage.id, expense.id]), {
        preserveScroll: true,
    });
};

const startPostExpense = (expense) => {
    postingExpenseId.value = expense.id;
    const options = props.paymentAccounts[expense.currency] ?? [];
    postForm.payment_account_id = options[0]?.id ?? null;
    postForm.clearErrors();
};

const cancelPostExpense = () => {
    postingExpenseId.value = null;
    postForm.reset();
    postForm.clearErrors();
};

const submitPostExpense = (expense) => {
    if (!window.confirm(t('voyage_expenses.post_confirm'))) return;
    postForm.post(route('voyages.expenses.post', [props.voyage.id, expense.id]), {
        preserveScroll: true,
        onSuccess: () => cancelPostExpense(),
    });
};

const postRevenue = () => {
    if (!window.confirm(t('voyage_settlements.post_revenue_confirm'))) return;
    revenuePostForm.post(route('voyages.settlements.post-revenue', props.voyage.id), {
        preserveScroll: true,
    });
};

const openCommissionPost = () => {
    showCommissionPost.value = true;
    commissionPostForm.payment_account_id = props.paymentAccounts.AED?.[0]?.id ?? null;
    commissionPostForm.clearErrors();
};

const cancelCommissionPost = () => {
    showCommissionPost.value = false;
    commissionPostForm.reset();
    commissionPostForm.clearErrors();
};

const postCommission = () => {
    if (!window.confirm(t('voyage_settlements.post_commission_confirm'))) return;
    commissionPostForm.post(route('voyages.settlements.post-commission', props.voyage.id), {
        preserveScroll: true,
        onSuccess: () => cancelCommissionPost(),
    });
};
</script>

<template>
    <Head :title="voyage.voyage_number" />
    <AppLayout>
        <template #header>{{ voyage.voyage_number }}</template>

        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link :href="route('voyages.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('voyages.back') }}
            </Link>
        </div>

        <div class="erp-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="erp-hero-kicker">{{ voyage.ship?.name || t('voyages.title') }}</div>
                    <h2 class="erp-hero-title">{{ voyage.voyage_number }}</h2>
                    <p class="erp-hero-subtitle">
                        {{ voyage.pol || '—' }} → {{ voyage.pod || '—' }} · {{ voyage.sailing_date }}
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <StatusBadge :tone="voyage.status_tone" :label="voyage.status_label" />
                    <Link
                        v-if="canManage && voyage.is_editable"
                        :href="route('voyages.edit', voyage.id)"
                        class="btn btn-light"
                    >
                        {{ t('common.edit') }}
                    </Link>
                    <button
                        v-for="item in transitions"
                        :key="item.value"
                        type="button"
                        class="btn btn-erp"
                        @click="transition(item.value)"
                    >
                        {{ t('voyages.mark_as', { status: item.label }) }}
                    </button>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <StatusStepper :steps="statusSteps" :current="voyage.status" />
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('voyages.sailing') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">{{ voyage.sailing_date }}</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('voyages.captain') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">{{ voyage.captain || '—' }}</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="erp-stat is-clickable" role="button" @click="activeTab = 'companies'">
                    <div class="erp-stat-label">{{ t('voyage_companies.title') }}</div>
                    <p class="erp-stat-value">{{ companies.length }}</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="erp-stat is-clickable" role="button" @click="activeTab = 'cars'">
                    <div class="erp-stat-label">{{ t('voyage_cars.title') }}</div>
                    <p class="erp-stat-value">{{ cars.length }}</p>
                </div>
            </div>
        </div>

        <div class="erp-card p-0 overflow-hidden mb-3">
                <div class="erp-toolbar gap-2">
                <Link
                    :href="route('voyages.tracking', voyage.id)"
                    class="btn btn-sm btn-erp-ghost"
                >
                    {{ t('voyages.tracking') }}
                </Link>
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="activeTab === 'cars' ? 'btn-erp' : 'btn-erp-ghost'"
                    @click="activeTab = 'cars'"
                >
                    {{ t('voyage_cars.title') }} ({{ cars.length }})
                </button>
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="activeTab === 'companies' ? 'btn-erp' : 'btn-erp-ghost'"
                    @click="activeTab = 'companies'"
                >
                    {{ t('voyage_companies.title') }} ({{ companies.length }})
                </button>
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="activeTab === 'expenses' ? 'btn-erp' : 'btn-erp-ghost'"
                    @click="activeTab = 'expenses'"
                >
                    {{ t('voyage_expenses.title') }} ({{ expenses.length }})
                </button>
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="activeTab === 'settlements' ? 'btn-erp' : 'btn-erp-ghost'"
                    @click="activeTab = 'settlements'"
                >
                    {{ t('voyage_settlements.title') }}
                </button>
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="activeTab === 'costs' ? 'btn-erp' : 'btn-erp-ghost'"
                    @click="activeTab = 'costs'"
                >
                    {{ t('voyages.aed_costs') }}
                </button>
            </div>

            <div v-if="activeTab === 'cars'" class="p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="erp-panel-title mb-1">{{ t('voyage_cars.title') }}</h3>
                        <p class="small text-secondary mb-0">{{ t('voyage_cars.help') }}</p>
                    </div>
                </div>

                <div v-if="cars.length === 0" class="mb-3">
                    <EmptyState icon="C" :title="t('voyage_cars.none')">
                        {{ t('voyage_cars.none_help') }}
                    </EmptyState>
                </div>

                <div v-else class="table-responsive mb-4">
                    <table class="table erp-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ t('voyage_cars.chassis') }}</th>
                                <th>{{ t('voyage_cars.consignee') }}</th>
                                <th>{{ t('voyage_companies.company') }}</th>
                                <th>{{ t('voyage_cars.description') }}</th>
                                <th class="text-end">{{ t('voyage_cars.weight') }}</th>
                                <th v-if="canManage && voyage.is_editable" class="text-end pe-0"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="car in cars" :key="car.id">
                                <td class="fw-semibold font-monospace">{{ car.chassis_no || '—' }}</td>
                                <td>
                                    <div>{{ car.consignee_name }}</div>
                                    <div v-if="car.code" class="small text-secondary">{{ car.code }}</div>
                                </td>
                                <td>{{ car.company_name || '—' }}</td>
                                <td>{{ car.description || '—' }}</td>
                                <td class="text-end font-monospace">{{ car.weight || '—' }}</td>
                                <td v-if="canManage && voyage.is_editable" class="text-end pe-0">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-erp-ghost" @click="startEditCar(car)">
                                            {{ t('common.edit') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeCar(car)">
                                            {{ t('common.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="canManage && voyage.is_editable"
                    class="row g-3"
                >
                    <div class="col-lg-7">
                        <form class="erp-form-panel" @submit.prevent="submitCar">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h4 class="h6 mb-0">
                                    {{ editingCarId ? t('voyage_cars.edit') : t('voyage_cars.add') }}
                                </h4>
                                <button
                                    v-if="editingCarId"
                                    type="button"
                                    class="btn btn-sm btn-erp-ghost"
                                    @click="resetCarForm"
                                >
                                    {{ t('common.cancel') }}
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('voyage_companies.company') }}</label>
                                    <select v-model="carForm.voyage_company_id" class="form-select form-erp-control" required>
                                        <option :value="null" disabled>{{ t('voyage_cars.select_company') }}</option>
                                        <option v-for="company in companies" :key="company.id" :value="company.id">
                                            {{ company.company_name }}
                                        </option>
                                    </select>
                                    <InputError :message="carForm.errors.voyage_company_id" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('voyage_cars.chassis') }}</label>
                                    <input v-model="carForm.chassis_no" class="form-control form-erp-control font-monospace" />
                                    <InputError :message="carForm.errors.chassis_no" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('voyage_cars.consignee') }}</label>
                                    <input v-model="carForm.consignee_name" class="form-control form-erp-control" required />
                                    <InputError :message="carForm.errors.consignee_name" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('voyage_cars.shipper') }}</label>
                                    <input v-model="carForm.shipper_name" class="form-control form-erp-control" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('voyage_cars.description') }}</label>
                                    <input v-model="carForm.description" class="form-control form-erp-control" />
                                </div>
                                <div class="col-md-3">
                                    <label class="form-erp-label">{{ t('voyage_cars.weight') }}</label>
                                    <input v-model.number="carForm.weight" type="number" min="0" step="0.001" class="form-control form-erp-control" />
                                </div>
                                <div class="col-md-3">
                                    <label class="form-erp-label">{{ t('voyage_cars.code') }}</label>
                                    <input v-model="carForm.code" class="form-control form-erp-control" />
                                </div>
                            </div>
                            <div class="erp-form-actions">
                                <button
                                    type="submit"
                                    class="btn btn-erp"
                                    :disabled="carForm.processing || companies.length === 0"
                                >
                                    {{ carForm.processing ? t('common.saving') : (editingCarId ? t('users.save_changes') : t('voyage_cars.add')) }}
                                </button>
                            </div>
                            <p v-if="companies.length === 0" class="small text-warning mb-0 mt-2">
                                {{ t('voyage_cars.need_company') }}
                            </p>
                        </form>
                    </div>

                    <div class="col-lg-5">
                        <form class="erp-form-panel h-100" @submit.prevent="submitImportPreview">
                            <h4 class="h6 mb-1">{{ t('voyage_cars.import') }}</h4>
                            <p class="small text-secondary mb-3">{{ t('voyage_cars.import_help') }}</p>
                            <div class="mb-2">
                                <label class="form-erp-label">{{ t('voyage_companies.company') }}</label>
                                <select v-model="importForm.voyage_company_id" class="form-select form-erp-control" required>
                                    <option :value="null" disabled>{{ t('voyage_cars.select_company') }}</option>
                                    <option v-for="company in companies" :key="company.id" :value="company.id">
                                        {{ company.company_name }}
                                        <template v-if="company.has_excel"> · {{ company.excel_original_name }}</template>
                                    </option>
                                </select>
                                <InputError :message="importForm.errors.voyage_company_id" />
                            </div>
                            <div class="mb-3">
                                <label class="form-erp-label">{{ t('voyage_cars.file') }}</label>
                                <input
                                    id="voyage-car-import"
                                    type="file"
                                    class="form-control form-erp-control"
                                    accept=".xlsx,.xls,.csv"
                                    required
                                    @change="onImportFile"
                                />
                                <InputError :message="importForm.errors.file" />
                            </div>
                            <div class="erp-form-actions">
                                <button
                                    type="submit"
                                    class="btn btn-erp"
                                    :disabled="importForm.processing || companies.length === 0"
                                >
                                    {{ importForm.processing ? t('voyage_cars.previewing') : t('voyage_cars.preview') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-if="importPreview" class="border rounded-3 p-3 mt-4">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <div>
                            <h4 class="h6 mb-1">{{ t('voyage_cars.preview_title') }}</h4>
                            <p class="small text-secondary mb-0">
                                {{ importPreview.company_name }} · {{ importPreview.original_name }}
                            </p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <StatusBadge tone="success" :label="`${t('voyage_cars.valid')}: ${importPreview.valid}`" :dot="false" />
                            <StatusBadge tone="warning" :label="`${t('voyage_cars.duplicates')}: ${importPreview.duplicates}`" :dot="false" />
                            <StatusBadge tone="neutral" :label="`${t('voyage_cars.skipped')}: ${importPreview.skipped}`" :dot="false" />
                        </div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table erp-table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>{{ t('voyage_cars.chassis') }}</th>
                                    <th>{{ t('voyage_cars.consignee') }}</th>
                                    <th>{{ t('voyage_cars.description') }}</th>
                                    <th>{{ t('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in importPreview.rows" :key="row.row_number">
                                    <td>{{ row.row_number }}</td>
                                    <td class="font-monospace">{{ row.chassis_no || '—' }}</td>
                                    <td>{{ row.consignee_name }}</td>
                                    <td>{{ row.description || '—' }}</td>
                                    <td>
                                        <StatusBadge
                                            :tone="row.status === 'duplicate' ? 'warning' : 'success'"
                                            :label="row.status === 'duplicate' ? t('voyage_cars.duplicates') : t('voyage_cars.valid')"
                                            :dot="false"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <button
                            type="button"
                            class="btn btn-erp"
                            :disabled="!canManage || !voyage.is_editable || !importPreview.valid"
                            @click="confirmImport(false)"
                        >
                            {{ t('voyage_cars.confirm_import') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-erp-ghost"
                            :disabled="!canManage || !voyage.is_editable || !importPreview.valid"
                            @click="confirmImport(true)"
                        >
                            {{ t('voyage_cars.queue_import') }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'expenses'" class="p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="erp-panel-title mb-1">{{ t('voyage_expenses.title') }}</h3>
                        <p class="small text-secondary mb-0">{{ t('voyage_expenses.help') }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <StatusBadge
                            v-for="total in expenseTotals"
                            :key="total.currency"
                            tone="info"
                            :label="`${total.total} ${total.currency}`"
                            :dot="false"
                        />
                    </div>
                </div>

                <div v-if="expenses.length === 0" class="mb-3">
                    <EmptyState icon="E" :title="t('voyage_expenses.none')">
                        {{ t('voyage_expenses.none_help') }}
                    </EmptyState>
                </div>

                <div v-else class="table-responsive mb-4">
                    <table class="table erp-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ t('common.date') }}</th>
                                <th>{{ t('voyage_expenses.type') }}</th>
                                <th>{{ t('voyage_expenses.vendor') }}</th>
                                <th class="text-end">{{ t('common.amount') }}</th>
                                <th>{{ t('common.currency') }}</th>
                                <th>{{ t('voyage_expenses.journal') }}</th>
                                <th class="text-end pe-0"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="expense in expenses" :key="expense.id">
                                <td>{{ expense.expense_date }}</td>
                                <td>
                                    <div class="fw-semibold">{{ expense.expense_type_label }}</div>
                                    <div v-if="expense.reference" class="small text-secondary">{{ expense.reference }}</div>
                                </td>
                                <td>{{ expense.vendor || '—' }}</td>
                                <td class="text-end font-monospace">{{ expense.amount }}</td>
                                <td>{{ expense.currency }}</td>
                                <td>
                                    <Link
                                        v-if="expense.is_posted && expense.journal_entry_id"
                                        :href="route('journals.show', expense.journal_entry_id)"
                                        class="fw-semibold text-decoration-none"
                                    >
                                        {{ expense.journal_voucher }}
                                    </Link>
                                    <StatusBadge
                                        v-else-if="expense.can_post"
                                        tone="warning"
                                        :label="t('voyage_expenses.unposted')"
                                        :dot="false"
                                    />
                                    <span v-else class="text-secondary">—</span>
                                </td>
                                <td class="text-end pe-0">
                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                        <template v-if="canPostAccounting && expense.can_post">
                                            <button
                                                v-if="postingExpenseId !== expense.id"
                                                type="button"
                                                class="btn btn-sm btn-erp"
                                                @click="startPostExpense(expense)"
                                            >
                                                {{ t('voyage_expenses.post') }}
                                            </button>
                                            <div v-else class="d-flex flex-wrap gap-1 align-items-center">
                                                <select
                                                    v-model="postForm.payment_account_id"
                                                    class="form-select form-select-sm form-erp-control"
                                                    style="min-width: 11rem"
                                                >
                                                    <option
                                                        v-for="account in (paymentAccounts[expense.currency] || [])"
                                                        :key="account.id"
                                                        :value="account.id"
                                                    >
                                                        {{ account.label }}
                                                    </option>
                                                </select>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-erp"
                                                    :class="{ 'is-posting': postForm.processing }"
                                                    :disabled="postForm.processing || !postForm.payment_account_id"
                                                    @click="submitPostExpense(expense)"
                                                >
                                                    {{ postForm.processing ? t('common.posting') : t('voyage_expenses.confirm_post') }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-erp-ghost" @click="cancelPostExpense">
                                                    {{ t('common.cancel') }}
                                                </button>
                                                <InputError :message="postForm.errors.payment_account_id || postForm.errors.expense" />
                                            </div>
                                        </template>
                                        <template v-if="canManage && voyage.is_editable && !expense.is_posted">
                                            <button type="button" class="btn btn-sm btn-erp-ghost" @click="startEditExpense(expense)">
                                                {{ t('common.edit') }}
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeExpense(expense)">
                                                {{ t('common.delete') }}
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    v-if="canManage && voyage.is_editable"
                    class="erp-form-panel"
                    @submit.prevent="submitExpense"
                >
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4 class="h6 mb-0">
                            {{ editingExpenseId ? t('voyage_expenses.edit') : t('voyage_expenses.add') }}
                        </h4>
                        <button
                            v-if="editingExpenseId"
                            type="button"
                            class="btn btn-sm btn-erp-ghost"
                            @click="resetExpenseForm"
                        >
                            {{ t('common.cancel') }}
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-erp-label">{{ t('voyage_expenses.type') }}</label>
                            <select v-model="expenseForm.expense_type" class="form-select form-erp-control" required>
                                <option v-for="type in expenseTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-erp-label">{{ t('common.date') }}</label>
                            <input v-model="expenseForm.expense_date" type="date" class="form-control form-erp-control" required />
                        </div>
                        <div class="col-md-3">
                            <label class="form-erp-label">{{ t('common.amount') }}</label>
                            <input v-model.number="expenseForm.amount" type="number" min="0.01" step="0.01" class="form-control form-erp-control" required />
                            <InputError :message="expenseForm.errors.amount" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-erp-label">{{ t('common.currency') }}</label>
                            <select v-model="expenseForm.currency" class="form-select form-erp-control" required>
                                <option v-for="currency in currencies" :key="currency.value" :value="currency.value">
                                    {{ currency.label }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('voyage_expenses.vendor') }}</label>
                            <input v-model="expenseForm.vendor" class="form-control form-erp-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('common.reference') }}</label>
                            <input v-model="expenseForm.reference" class="form-control form-erp-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('common.notes') }}</label>
                            <input v-model="expenseForm.notes" class="form-control form-erp-control" />
                        </div>
                    </div>
                    <p class="small text-secondary mb-0 mt-3">{{ t('voyage_expenses.accounting_note') }}</p>
                    <div class="erp-form-actions">
                        <button type="submit" class="btn btn-erp" :disabled="expenseForm.processing">
                            {{ expenseForm.processing ? t('common.saving') : (editingExpenseId ? t('users.save_changes') : t('voyage_expenses.add')) }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-else-if="activeTab === 'settlements'" class="p-4">
                <div class="mb-3">
                    <h3 class="erp-panel-title mb-1">{{ t('voyage_settlements.title') }}</h3>
                    <p class="small text-secondary mb-0">{{ t('voyage_settlements.help') }}</p>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="erp-stat">
                            <div class="erp-stat-label">{{ t('voyage_settlements.revenue_usd') }}</div>
                            <p class="erp-stat-value" style="font-size: 1.15rem">{{ settlements.summary.revenue_usd || '0.00' }}</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="erp-stat">
                            <div class="erp-stat-label">{{ t('voyage_settlements.expenses_usd') }}</div>
                            <p class="erp-stat-value" style="font-size: 1.15rem">{{ settlements.summary.expenses_usd || '0.00' }}</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="erp-stat">
                            <div class="erp-stat-label">{{ t('voyage_settlements.profit_usd') }}</div>
                            <p class="erp-stat-value" style="font-size: 1.15rem">{{ settlements.summary.profit_usd || '0.00' }}</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="erp-stat">
                            <div class="erp-stat-label">{{ t('voyage_settlements.commission_aed') }}</div>
                            <p class="erp-stat-value" style="font-size: 1.15rem">{{ settlements.summary.total_captain_commission_aed || '0.00' }}</p>
                            <div class="erp-stat-hint">{{ t('voyage_settlements.commission_hint') }}</div>
                        </div>
                    </div>
                </div>

                <div class="erp-card border rounded-3 p-3 mb-4">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-2">
                        <div>
                            <h4 class="h6 mb-1">{{ t('voyage_settlements.posting_title') }}</h4>
                            <p class="small text-secondary mb-0">{{ t('voyage_settlements.posting_help') }}</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="fw-semibold mb-1">{{ t('voyage_settlements.post_revenue') }}</div>
                                <div class="small text-secondary mb-2">{{ t('voyage_settlements.post_revenue_hint') }}</div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <Link
                                        v-if="settlementPosting.revenue_posted && settlementPosting.revenue_journal_entry_id"
                                        :href="route('journals.show', settlementPosting.revenue_journal_entry_id)"
                                        class="fw-semibold text-decoration-none"
                                    >
                                        {{ settlementPosting.revenue_voucher }}
                                    </Link>
                                    <StatusBadge
                                        v-else-if="settlementPosting.can_post_revenue"
                                        tone="warning"
                                        :label="t('voyage_expenses.unposted')"
                                        :dot="false"
                                    />
                                    <span v-else class="text-secondary small">—</span>
                                    <button
                                        v-if="canPostAccounting && settlementPosting.can_post_revenue"
                                        type="button"
                                        class="btn btn-sm btn-erp"
                                        :class="{ 'is-posting': revenuePostForm.processing }"
                                        :disabled="revenuePostForm.processing"
                                        @click="postRevenue"
                                    >
                                        {{ revenuePostForm.processing ? t('common.posting') : t('voyage_settlements.post_revenue') }}
                                    </button>
                                </div>
                                <InputError :message="revenuePostForm.errors.revenue" />
                                <div
                                    v-if="canPostAccounting && settlementPosting.revenue_posted"
                                    class="mt-3 pt-2 border-top"
                                >
                                    <div class="small text-secondary mb-2">{{ t('voyage_settlements.collect_hint') }}</div>
                                    <Link
                                        :href="route('money-vouchers.create', {
                                            type: 'receipt',
                                            voyage_id: voyage.id,
                                            amount: settlements.summary.revenue_usd,
                                            currency: 'USD',
                                        })"
                                        class="btn btn-sm btn-erp-ghost"
                                    >
                                        {{ t('voyage_settlements.collect') }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="fw-semibold mb-1">{{ t('voyage_settlements.post_commission') }}</div>
                                <div class="small text-secondary mb-2">{{ t('voyage_settlements.post_commission_hint') }}</div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <Link
                                        v-if="settlementPosting.commission_posted && settlementPosting.commission_journal_entry_id"
                                        :href="route('journals.show', settlementPosting.commission_journal_entry_id)"
                                        class="fw-semibold text-decoration-none"
                                    >
                                        {{ settlementPosting.commission_voucher }}
                                    </Link>
                                    <StatusBadge
                                        v-else-if="settlementPosting.can_post_commission"
                                        tone="warning"
                                        :label="t('voyage_expenses.unposted')"
                                        :dot="false"
                                    />
                                    <span v-else class="text-secondary small">—</span>
                                    <button
                                        v-if="canPostAccounting && settlementPosting.can_post_commission && !showCommissionPost"
                                        type="button"
                                        class="btn btn-sm btn-erp"
                                        @click="openCommissionPost"
                                    >
                                        {{ t('voyage_settlements.post_commission') }}
                                    </button>
                                </div>
                                <div v-if="showCommissionPost" class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                    <select
                                        v-model="commissionPostForm.payment_account_id"
                                        class="form-select form-select-sm form-erp-control"
                                        style="min-width: 12rem"
                                    >
                                        <option
                                            v-for="account in (paymentAccounts.AED || [])"
                                            :key="account.id"
                                            :value="account.id"
                                        >
                                            {{ account.label }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-erp"
                                        :class="{ 'is-posting': commissionPostForm.processing }"
                                        :disabled="commissionPostForm.processing || !commissionPostForm.payment_account_id"
                                        @click="postCommission"
                                    >
                                        {{ commissionPostForm.processing ? t('common.posting') : t('voyage_settlements.confirm_post') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-erp-ghost" @click="cancelCommissionPost">
                                        {{ t('common.cancel') }}
                                    </button>
                                    <InputError :message="commissionPostForm.errors.payment_account_id || commissionPostForm.errors.commission" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="border rounded-3 overflow-hidden mb-3">
                            <div class="p-3 border-bottom">
                                <h4 class="h6 mb-0">{{ t('voyage_settlements.companies') }}</h4>
                            </div>
                            <div v-if="!settlements.companies?.length" class="p-3">
                                <EmptyState icon="S">{{ t('voyage_companies.none') }}</EmptyState>
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table erp-table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">{{ t('voyage_companies.company') }}</th>
                                            <th class="text-end">{{ t('voyage_cars.title') }}</th>
                                            <th class="text-end">{{ t('voyage_settlements.shipping') }}</th>
                                            <th class="text-end">{{ t('voyage_settlements.clearance') }}</th>
                                            <th class="text-end pe-3">{{ t('voyage_settlements.due_usd') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in settlements.companies" :key="row.company_id">
                                            <td class="ps-3 fw-semibold">
                                                <Link
                                                    v-if="row.master_company_id"
                                                    :href="route('companies.show', row.master_company_id)"
                                                    class="text-decoration-none"
                                                >
                                                    {{ row.company_name }}
                                                </Link>
                                                <span v-else>{{ row.company_name }}</span>
                                            </td>
                                            <td class="text-end">{{ row.cars_count }}</td>
                                            <td class="text-end font-monospace">{{ row.shipping_total_usd }}</td>
                                            <td class="text-end font-monospace">{{ row.clearance_total_usd }}</td>
                                            <td class="text-end pe-3 font-monospace fw-semibold">{{ row.due_usd }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="border rounded-3 overflow-hidden mb-3">
                            <div class="p-3 border-bottom">
                                <h4 class="h6 mb-0">{{ t('voyage_settlements.company_movements') }}</h4>
                                <div class="small text-secondary">{{ t('voyage_settlements.company_movements_help') }}</div>
                            </div>
                            <div v-if="!companyMovements.length" class="p-3">
                                <EmptyState icon="$">{{ t('voyage_settlements.no_company_movements') }}</EmptyState>
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table erp-table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">{{ t('common.date') }}</th>
                                            <th>{{ t('voyage_companies.company') }}</th>
                                            <th>{{ t('journals.voucher') }}</th>
                                            <th class="text-end">{{ t('journals.debit') }}</th>
                                            <th class="text-end pe-3">{{ t('journals.credit') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in companyMovements" :key="row.id">
                                            <td class="ps-3">{{ row.date }}</td>
                                            <td>
                                                <Link
                                                    v-if="row.company_id"
                                                    :href="route('companies.show', row.company_id)"
                                                    class="text-decoration-none"
                                                >
                                                    {{ row.company_name }}
                                                </Link>
                                            </td>
                                            <td>
                                                <Link
                                                    v-if="row.journal_entry_id"
                                                    :href="route('journals.show', row.journal_entry_id)"
                                                    class="text-decoration-none"
                                                >
                                                    {{ row.voucher }}
                                                </Link>
                                                <div class="small text-secondary">{{ row.memo }}</div>
                                            </td>
                                            <td class="text-end">
                                                <MoneyAmount :value="row.debit" tone="debit" />
                                            </td>
                                            <td class="text-end pe-3">
                                                <MoneyAmount :value="row.credit" tone="credit" />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="border rounded-3 overflow-hidden mb-3">
                            <div class="p-3 border-bottom d-flex flex-wrap justify-content-between gap-2 align-items-center">
                                <div>
                                    <h4 class="h6 mb-0">{{ t('voyage_settlements.owners_title') }}</h4>
                                    <div class="small text-secondary">{{ t('voyage_settlements.owners_help') }}</div>
                                </div>
                                <Link
                                    v-if="settlements.summary.ship_id"
                                    :href="route('ships.show', { ship: settlements.summary.ship_id, tab: 'owners' })"
                                    class="btn btn-sm btn-erp-ghost"
                                >
                                    {{ t('ship_owners.manage') }}
                                </Link>
                            </div>
                            <div v-if="!settlements.owners?.length" class="p-3">
                                <EmptyState icon="O" :title="t('voyage_settlements.no_owners')">
                                    {{ t('voyage_settlements.no_owners_help') }}
                                </EmptyState>
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table erp-table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">{{ t('ship_owners.owner') }}</th>
                                            <th class="text-end">{{ t('ship_owners.share') }}</th>
                                            <th class="text-end pe-3">{{ t('voyage_settlements.owner_share_usd') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in settlements.owners" :key="row.ownership_id">
                                            <td class="ps-3">
                                                <div class="fw-semibold">{{ row.owner_name }}</div>
                                                <StatusBadge
                                                    v-if="row.is_managing"
                                                    tone="info"
                                                    :label="t('ship_owners.managing')"
                                                    :dot="false"
                                                />
                                            </td>
                                            <td class="text-end font-monospace">{{ row.share_percent }}%</td>
                                            <td class="text-end pe-3 font-monospace fw-semibold">{{ row.share_of_profit_usd }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                v-if="settlements.owners?.length"
                                class="p-3 border-top small text-secondary d-flex flex-wrap justify-content-between gap-2"
                            >
                                <span>
                                    {{ t('voyage_settlements.ownership_total') }}:
                                    {{ settlements.summary.ownership_total_share }}%
                                    <template v-if="!settlements.summary.ownership_is_complete">
                                        · {{ t('voyage_settlements.unallocated') }}:
                                        {{ settlements.summary.ownership_unallocated_usd }} USD
                                    </template>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="border rounded-3 overflow-hidden mb-3">
                            <div class="p-3 border-bottom">
                                <h4 class="h6 mb-0">{{ t('voyage_settlements.consignees') }}</h4>
                            </div>
                            <div v-if="!settlements.consignees?.length" class="p-3">
                                <EmptyState icon="C">{{ t('voyage_settlements.no_consignees') }}</EmptyState>
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table erp-table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">{{ t('voyage_cars.consignee') }}</th>
                                            <th class="text-end">{{ t('voyage_cars.title') }}</th>
                                            <th class="text-end pe-3">{{ t('voyage_settlements.due_usd') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, index) in settlements.consignees" :key="`${row.consignee_name}-${index}`">
                                            <td class="ps-3">
                                                <div class="fw-semibold">{{ row.consignee_name }}</div>
                                                <div class="small text-secondary">{{ (row.companies || []).join(' · ') || '—' }}</div>
                                            </td>
                                            <td class="text-end">{{ row.cars_count }}</td>
                                            <td class="text-end pe-3 font-monospace fw-semibold">{{ row.due_usd }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="erp-card-soft border rounded-3 p-3">
                            <div class="erp-meta-grid">
                                <div class="erp-meta-item">
                                    <div class="erp-meta-label">{{ t('voyage_settlements.ship_cost_aed') }}</div>
                                    <div class="erp-meta-value font-monospace">{{ settlements.summary.total_ship_cost_aed || '0.00' }}</div>
                                </div>
                                <div class="erp-meta-item">
                                    <div class="erp-meta-label">{{ t('voyage_settlements.expenses_aed') }}</div>
                                    <div class="erp-meta-value font-monospace">{{ settlements.summary.expenses_aed || '0.00' }}</div>
                                </div>
                                <div class="erp-meta-item">
                                    <div class="erp-meta-label">{{ t('voyage_settlements.net_ops_aed') }}</div>
                                    <div class="erp-meta-value font-monospace">{{ settlements.summary.net_ops_aed || '0.00' }}</div>
                                </div>
                            </div>
                            <p class="small text-secondary mb-0 mt-3">{{ t('voyage_settlements.note') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'companies'" class="p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h3 class="erp-panel-title mb-0">{{ t('voyage_companies.title') }}</h3>
                        <span class="small text-secondary">{{ t('voyage_companies.help') }}</span>
                    </div>
                    <Link
                        v-if="canManage"
                        :href="route('companies.index')"
                        class="btn btn-sm btn-erp-ghost"
                    >
                        {{ t('companies.manage') }}
                    </Link>
                </div>

                <div v-if="companies.length === 0" class="mb-3">
                    <EmptyState icon="S" :title="t('voyage_companies.none')">
                        {{ t('voyage_companies.none_help') }}
                    </EmptyState>
                </div>

                <div v-else class="table-responsive mb-3">
                    <table class="table erp-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ t('voyage_companies.company') }}</th>
                                <th class="text-end">{{ t('voyage_companies.shipping_usd') }}</th>
                                <th class="text-end">{{ t('voyage_companies.clearance') }}</th>
                                <th class="text-end">{{ t('voyage_companies.shipping_aed') }}</th>
                                <th class="text-end pe-0" v-if="canManage && voyage.is_editable"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="company in companies" :key="company.id">
                                <td>
                                    <div class="fw-semibold">{{ company.company_name }}</div>
                                    <div class="small text-secondary">
                                        {{ company.contact_name || '—' }}
                                        <span v-if="company.contact_phone"> · {{ company.contact_phone }}</span>
                                    </div>
                                </td>
                                <td class="text-end font-monospace">{{ company.shipping_price_per_car }}</td>
                                <td class="text-end font-monospace">{{ company.clearance_per_car }}</td>
                                <td class="text-end font-monospace">{{ company.shipping_price_aed }}</td>
                                <td class="text-end pe-0" v-if="canManage && voyage.is_editable">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-erp-ghost" @click="startEditCompany(company)">
                                            {{ t('common.edit') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeCompany(company)">
                                            {{ t('common.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    v-if="canManage && voyage.is_editable"
                    class="erp-form-panel mt-3"
                    @submit.prevent="submitCompany"
                >
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4 class="h6 mb-0">
                            {{ editingCompanyId ? t('voyage_companies.edit_rates') : t('voyage_companies.add') }}
                        </h4>
                        <button
                            v-if="editingCompanyId"
                            type="button"
                            class="btn btn-sm btn-erp-ghost"
                            @click="resetCompanyForm"
                        >
                            {{ t('common.cancel') }}
                        </button>
                    </div>

                    <div v-if="!editingCompanyId" class="mb-2">
                        <div class="form-check form-switch">
                            <input id="newCompanySwitch" v-model="createNewCompany" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="newCompanySwitch">{{ t('voyage_companies.create_new') }}</label>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div v-if="!editingCompanyId && !createNewCompany" class="col-md-6">
                            <label class="form-erp-label">{{ t('voyage_companies.company') }}</label>
                            <select v-model="companyForm.company_id" class="form-select form-erp-control" required>
                                <option v-for="opt in companyOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                            </select>
                            <InputError :message="companyForm.errors.company_id" />
                            <div class="small text-secondary mt-1">{{ t('voyage_companies.master_hint') }}</div>
                        </div>
                        <template v-if="!editingCompanyId && createNewCompany">
                            <div class="col-md-6">
                                <label class="form-erp-label">{{ t('voyage_companies.company') }}</label>
                                <input v-model="companyForm.company_name" class="form-control form-erp-control" required />
                                <InputError :message="companyForm.errors.company_name" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-erp-label">{{ t('voyage_companies.contact') }}</label>
                                <input v-model="companyForm.contact_name" class="form-control form-erp-control" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-erp-label">{{ t('voyage_companies.phone') }}</label>
                                <input v-model="companyForm.contact_phone" class="form-control form-erp-control" />
                            </div>
                        </template>
                        <div v-if="editingCompanyId" class="col-12">
                            <div class="small text-secondary mb-1">{{ t('voyage_companies.company') }}</div>
                            <div class="fw-semibold">{{ companyForm.company_name }}</div>
                            <div class="small text-secondary">
                                {{ t('voyage_companies.edit_identity_hint') }}
                                <Link :href="route('companies.index')" class="text-decoration-none">{{ t('companies.manage') }}</Link>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('voyage_companies.shipping_usd') }}</label>
                            <input v-model.number="companyForm.shipping_price_per_car" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('voyage_companies.clearance') }}</label>
                            <input v-model.number="companyForm.clearance_per_car" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('voyage_companies.shipping_aed') }}</label>
                            <input v-model.number="companyForm.shipping_price_aed" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                        </div>
                    </div>
                    <div class="erp-form-actions">
                        <button type="submit" class="btn btn-erp" :disabled="companyForm.processing">
                            {{ companyForm.processing ? t('common.saving') : (editingCompanyId ? t('users.save_changes') : t('voyage_companies.add')) }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-else class="p-4">
                <h3 class="erp-panel-title mb-3">{{ t('voyages.aed_costs') }}</h3>
                <div class="erp-meta-grid mb-3">
                    <div class="erp-meta-item">
                        <div class="erp-meta-label">{{ t('voyages.cost_aed') }}</div>
                        <div class="erp-meta-value font-monospace">{{ voyage.cost_per_car_aed }}</div>
                    </div>
                    <div class="erp-meta-item">
                        <div class="erp-meta-label">{{ t('voyages.commission_aed') }}</div>
                        <div class="erp-meta-value font-monospace">{{ voyage.captain_commission_aed }}</div>
                    </div>
                    <div class="erp-meta-item">
                        <div class="erp-meta-label">{{ t('voyages.purchase_aed') }}</div>
                        <div class="erp-meta-value font-monospace">{{ voyage.purchase_price_aed }}</div>
                    </div>
                    <div class="erp-meta-item">
                        <div class="erp-meta-label">{{ t('voyage_settlements.commission_aed') }}</div>
                        <div class="erp-meta-value font-monospace">{{ settlements.summary.total_captain_commission_aed || '0.00' }}</div>
                    </div>
                    <div class="erp-meta-item">
                        <div class="erp-meta-label">{{ t('voyage_settlements.ship_cost_aed') }}</div>
                        <div class="erp-meta-value font-monospace">{{ settlements.summary.total_ship_cost_aed || '0.00' }}</div>
                    </div>
                </div>
                <div class="text-secondary small mb-1">{{ t('common.notes') }}</div>
                <div class="fw-semibold">{{ voyage.notes || '—' }}</div>
                <p class="small text-secondary mb-0 mt-3">{{ t('voyage_settlements.commission_hint') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
