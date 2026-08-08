<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    ship: { type: Object, required: true },
    ownerships: { type: Array, default: () => [] },
    ownershipSummary: { type: Object, default: () => ({}) },
    ownerOptions: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    expenseTotals: { type: Array, default: () => [] },
    contributions: { type: Array, default: () => [] },
    partnerSummaries: { type: Object, default: () => ({}) },
    expenseTypes: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    paymentAccounts: { type: Object, default: () => ({ USD: [], AED: [] }) },
    canManage: { type: Boolean, default: false },
    canPostAccounting: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const urlTab = new URL(page.url, window.location.origin).searchParams.get('tab');
const activeTab = ref(
    urlTab
    || (props.ownerships.length === 0 ? 'owners' : 'overview')
);
const editingOwnershipId = ref(null);
const editingExpenseId = ref(null);
const postingExpenseId = ref(null);
const postingContributionId = ref(null);
const editingContributionId = ref(null);
const ledgerCurrency = ref('USD');
const createNewOwner = ref(props.ownerOptions.length === 0);

const today = () => new Date().toISOString().slice(0, 10);
const defaultSpenderId = () =>
    props.ownerships.find((row) => row.is_managing)?.owner_id
    ?? props.ownerships[0]?.owner_id
    ?? null;
const defaultPayerId = () =>
    props.ownerships.find((row) => !row.is_managing)?.owner_id
    ?? props.ownerships[0]?.owner_id
    ?? null;

const emptyExpenseRow = () => ({
    expense_type: props.expenseTypes[0]?.value ?? 'other',
    amount: null,
    currency: ledgerCurrency.value,
    expense_date: today(),
    vendor: '',
    reference: '',
    paid_by_owner_id: defaultSpenderId(),
});

const emptyPaymentRow = () => ({
    owner_id: defaultPayerId(),
    contribution_date: today(),
    amount: null,
    currency: ledgerCurrency.value,
    description: '',
    reference: '',
});

const ownershipForm = useForm({
    owner_id: props.ownerOptions[0]?.id ?? null,
    owner_name: '',
    owner_phone: '',
    owner_email: '',
    share_percent: Number(props.ownershipSummary.remaining || 0) || 50,
    is_managing: false,
    effective_from: '',
    notes: '',
});

const expenseForm = useForm({
    expense_type: props.expenseTypes[0]?.value ?? 'other',
    amount: 0,
    currency: 'USD',
    expense_date: new Date().toISOString().slice(0, 10),
    vendor: '',
    reference: '',
    notes: '',
    paid_by_owner_id: defaultSpenderId(),
});

const postForm = useForm({
    mode: 'partner',
    payment_account_id: null,
});

const contributionForm = useForm({
    owner_id: defaultPayerId(),
    contribution_date: today(),
    amount: 0,
    currency: 'USD',
    description: '',
    reference: '',
});

const contributionPostForm = useForm({
    payment_account_id: null,
});

const expenseListForm = useForm({
    rows: [emptyExpenseRow(), emptyExpenseRow(), emptyExpenseRow()],
});

const paymentListForm = useForm({
    rows: [emptyPaymentRow(), emptyPaymentRow()],
});

const expenseImportForm = useForm({
    file: null,
    currency: 'USD',
    paid_by_owner_id: defaultSpenderId(),
});

const paymentImportForm = useForm({
    file: null,
    owner_id: defaultPayerId(),
    currency: 'USD',
});

const expensePreviewing = ref(false);
const paymentPreviewing = ref(false);
const expensePreviewNote = ref('');
const paymentPreviewNote = ref('');
const expenseListPanel = ref(null);
const paymentListPanel = ref(null);

const partnerSummary = computed(() => props.partnerSummaries[ledgerCurrency.value] || null);
const filteredExpenses = computed(() => props.expenses.filter((row) => row.currency === ledgerCurrency.value));
const filteredContributions = computed(() => props.contributions.filter((row) => row.currency === ledgerCurrency.value));
const differenceTone = computed(() => {
    const value = Number(partnerSummary.value?.difference_numeric || 0);
    if (Math.abs(value) < 0.005) return 'is-settled';
    return value > 0 ? 'is-owing' : 'is-owed';
});
const settlementHint = computed(() => {
    const summary = partnerSummary.value;
    if (!summary) return '';
    if (summary.hint_key === 'settled') return t('ship_expenses.hint_settled');
    if (summary.hint_key === 'other_more') {
        return t('ship_expenses.hint_other_more', { spender: summary.spender_name || '—', other: summary.other_name || '—' });
    }
    return t('ship_expenses.hint_spender_more', { spender: summary.spender_name || '—', other: summary.other_name || '—' });
});
const expensesByMonth = computed(() => {
    const groups = [];
    const index = {};
    filteredExpenses.value.forEach((expense) => {
        const key = (expense.expense_date || '').slice(0, 7) || '—';
        if (!index[key]) {
            index[key] = { month: key, rows: [], total: 0 };
            groups.push(index[key]);
        }
        index[key].rows.push(expense);
        index[key].total += Number(expense.amount) || 0;
    });
    return groups;
});

const resetOwnershipForm = () => {
    editingOwnershipId.value = null;
    createNewOwner.value = false;
    ownershipForm.reset();
    ownershipForm.clearErrors();
    ownershipForm.owner_id = props.ownerOptions[0]?.id ?? null;
    ownershipForm.share_percent = Number(props.ownershipSummary.remaining || 0) || 50;
};

const startEditOwnership = (row) => {
    activeTab.value = 'owners';
    editingOwnershipId.value = row.id;
    createNewOwner.value = false;
    ownershipForm.share_percent = Number(row.share_percent);
    ownershipForm.is_managing = row.is_managing;
    ownershipForm.effective_from = row.effective_from ?? '';
    ownershipForm.notes = row.notes ?? '';
};

const submitOwnership = () => {
    if (editingOwnershipId.value) {
        ownershipForm.transform(() => ({
            share_percent: ownershipForm.share_percent,
            is_managing: ownershipForm.is_managing,
            effective_from: ownershipForm.effective_from || null,
            notes: ownershipForm.notes || null,
        })).put(route('ships.ownerships.update', [props.ship.id, editingOwnershipId.value]), {
            preserveScroll: true,
            onSuccess: () => resetOwnershipForm(),
        });
        return;
    }

    ownershipForm.transform(() => ({
        owner_id: createNewOwner.value ? null : ownershipForm.owner_id,
        owner_name: createNewOwner.value ? ownershipForm.owner_name : null,
        owner_phone: createNewOwner.value ? ownershipForm.owner_phone : null,
        owner_email: createNewOwner.value ? ownershipForm.owner_email : null,
        share_percent: ownershipForm.share_percent,
        is_managing: ownershipForm.is_managing,
        effective_from: ownershipForm.effective_from || null,
        notes: ownershipForm.notes || null,
    })).post(route('ships.ownerships.store', props.ship.id), {
        preserveScroll: true,
        onSuccess: () => resetOwnershipForm(),
    });
};

const removeOwnership = (row) => {
    if (!window.confirm(t('ship_owners.delete_confirm', { name: row.owner_name }))) return;
    router.delete(route('ships.ownerships.destroy', [props.ship.id, row.id]), { preserveScroll: true });
};

const resetExpenseForm = () => {
    editingExpenseId.value = null;
    expenseForm.reset();
    expenseForm.clearErrors();
    expenseForm.expense_type = props.expenseTypes[0]?.value ?? 'other';
    expenseForm.currency = 'USD';
    expenseForm.expense_date = new Date().toISOString().slice(0, 10);
    expenseForm.paid_by_owner_id = defaultSpenderId();
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
    expenseForm.paid_by_owner_id = expense.paid_by_owner_id ?? defaultSpenderId();
};

const submitExpense = () => {
    if (editingExpenseId.value) {
        expenseForm.put(route('ships.expenses.update', [props.ship.id, editingExpenseId.value]), {
            preserveScroll: true,
            onSuccess: () => resetExpenseForm(),
        });
        return;
    }
    expenseForm.post(route('ships.expenses.store', props.ship.id), {
        preserveScroll: true,
        onSuccess: () => resetExpenseForm(),
    });
};

const removeExpense = (expense) => {
    if (!window.confirm(t('ship_expenses.delete_confirm'))) return;
    router.delete(route('ships.expenses.destroy', [props.ship.id, expense.id]), { preserveScroll: true });
};

const startPostExpense = (expense) => {
    postingExpenseId.value = expense.id;
    postingContributionId.value = null;
    const options = props.paymentAccounts[expense.currency] ?? [];
    postForm.mode = 'partner';
    postForm.payment_account_id = options[0]?.id ?? null;
    postForm.clearErrors();
};

const cancelPostExpense = () => {
    postingExpenseId.value = null;
    postForm.reset();
    postForm.mode = 'partner';
    postForm.clearErrors();
};

const submitPostExpense = (expense) => {
    if (!window.confirm(t('ship_expenses.post_confirm'))) return;
    postForm.post(route('ships.expenses.post', [props.ship.id, expense.id]), {
        preserveScroll: true,
        onSuccess: () => cancelPostExpense(),
    });
};

const resetContributionForm = () => {
    editingContributionId.value = null;
    contributionForm.reset();
    contributionForm.clearErrors();
    contributionForm.owner_id = defaultPayerId();
    contributionForm.currency = ledgerCurrency.value;
    contributionForm.contribution_date = today();
};

const startEditContribution = (row) => {
    activeTab.value = 'expenses';
    editingContributionId.value = row.id;
    contributionForm.owner_id = row.owner_id;
    contributionForm.contribution_date = row.contribution_date;
    contributionForm.amount = Number(row.amount);
    contributionForm.currency = row.currency;
    contributionForm.description = row.description ?? '';
    contributionForm.reference = row.reference ?? '';
};

const submitContribution = () => {
    if (editingContributionId.value) {
        contributionForm.put(route('ships.contributions.update', [props.ship.id, editingContributionId.value]), {
            preserveScroll: true,
            onSuccess: () => resetContributionForm(),
        });
        return;
    }
    contributionForm.post(route('ships.contributions.store', props.ship.id), {
        preserveScroll: true,
        onSuccess: () => resetContributionForm(),
    });
};

const removeContribution = (row) => {
    if (!window.confirm(t('ship_partners.delete_confirm'))) return;
    router.delete(route('ships.contributions.destroy', [props.ship.id, row.id]), { preserveScroll: true });
};

const startPostContribution = (row) => {
    postingContributionId.value = row.id;
    postingExpenseId.value = null;
    const options = props.paymentAccounts[row.currency] ?? [];
    contributionPostForm.payment_account_id = options[0]?.id ?? null;
    contributionPostForm.clearErrors();
};

const cancelPostContribution = () => {
    postingContributionId.value = null;
    contributionPostForm.reset();
    contributionPostForm.clearErrors();
};

const submitPostContribution = (row) => {
    if (!window.confirm(t('ship_partners.post_confirm'))) return;
    contributionPostForm.post(route('ships.contributions.post', [props.ship.id, row.id]), {
        preserveScroll: true,
        onSuccess: () => cancelPostContribution(),
    });
};

const addExpenseListRow = () => expenseListForm.rows.push(emptyExpenseRow());
const addPaymentListRow = () => paymentListForm.rows.push(emptyPaymentRow());

const submitExpenseList = () => {
    expenseListForm
        .transform((data) => ({ rows: data.rows.filter((row) => Number(row.amount) > 0) }))
        .post(route('ships.expenses.bulk', props.ship.id), {
            preserveScroll: true,
            onSuccess: () => {
                expenseListForm.reset();
                expenseListForm.rows = [emptyExpenseRow(), emptyExpenseRow(), emptyExpenseRow()];
                expensePreviewNote.value = '';
            },
        });
};

const submitPaymentList = () => {
    paymentListForm
        .transform((data) => ({ rows: data.rows.filter((row) => Number(row.amount) > 0) }))
        .post(route('ships.contributions.bulk', props.ship.id), {
            preserveScroll: true,
            onSuccess: () => {
                paymentListForm.reset();
                paymentListForm.rows = [emptyPaymentRow(), emptyPaymentRow()];
                paymentPreviewNote.value = '';
            },
        });
};

const onExpenseFile = (event) => {
    expenseImportForm.file = event.target.files?.[0] ?? null;
    expensePreviewNote.value = '';
};

const onPaymentFile = (event) => {
    paymentImportForm.file = event.target.files?.[0] ?? null;
    paymentPreviewNote.value = '';
};

const applyAxiosErrors = (form, error) => {
    const errors = error.response?.data?.errors || {};
    form.clearErrors();
    Object.entries(errors).forEach(([key, messages]) => {
        form.setError(key, Array.isArray(messages) ? messages[0] : String(messages));
    });
    if (Object.keys(errors).length === 0) {
        form.setError('file', error.response?.data?.message || t('ship_expenses.import_failed'));
    }
};

const submitExpenseImport = async () => {
    if (!expenseImportForm.file) return;
    expensePreviewing.value = true;
    expensePreviewNote.value = '';
    expenseImportForm.clearErrors();
    try {
        const body = new FormData();
        body.append('file', expenseImportForm.file);
        body.append('currency', expenseImportForm.currency || 'USD');
        if (expenseImportForm.paid_by_owner_id) {
            body.append('paid_by_owner_id', String(expenseImportForm.paid_by_owner_id));
        }
        const { data } = await axios.post(route('ships.expenses.import', props.ship.id), body);
        const rows = (data.rows || []).map((row) => ({
            ...emptyExpenseRow(),
            ...row,
            amount: Number(row.amount) || null,
            paid_by_owner_id: row.paid_by_owner_id ?? defaultSpenderId(),
        }));
        expenseListForm.rows = rows.length ? rows : [emptyExpenseRow()];
        expenseListForm.clearErrors();
        expensePreviewNote.value = t('ship_expenses.import_filled', {
            count: rows.length,
            skipped: Number(data.skipped || 0),
        });
        expenseListPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        applyAxiosErrors(expenseImportForm, error);
    } finally {
        expensePreviewing.value = false;
    }
};

const submitPaymentImport = async () => {
    if (!paymentImportForm.file) return;
    paymentPreviewing.value = true;
    paymentPreviewNote.value = '';
    paymentImportForm.clearErrors();
    try {
        const body = new FormData();
        body.append('file', paymentImportForm.file);
        body.append('owner_id', String(paymentImportForm.owner_id || ''));
        body.append('currency', paymentImportForm.currency || 'USD');
        const { data } = await axios.post(route('ships.contributions.import', props.ship.id), body);
        const rows = (data.rows || []).map((row) => ({
            ...emptyPaymentRow(),
            ...row,
            amount: Number(row.amount) || null,
            owner_id: row.owner_id ?? paymentImportForm.owner_id ?? defaultPayerId(),
        }));
        paymentListForm.rows = rows.length ? rows : [emptyPaymentRow()];
        paymentListForm.clearErrors();
        paymentPreviewNote.value = t('ship_partners.import_filled', {
            count: rows.length,
            skipped: Number(data.skipped || 0),
        });
        paymentListPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        applyAxiosErrors(paymentImportForm, error);
    } finally {
        paymentPreviewing.value = false;
    }
};
</script>

<template>
    <Head :title="ship.name" />
    <AppLayout>
        <template #header>{{ ship.name }}</template>

        <div v-if="success" class="alert alert-success border-0 shadow-sm mb-3">{{ success }}</div>

        <div class="mb-3">
            <Link :href="route('ships.index')" class="text-decoration-none small fw-semibold">← {{ t('ships.back') }}</Link>
        </div>

        <PageHeader :kicker="t('ships.title')" :title="ship.name" :subtitle="t('ships.show_help')">
            <template #actions>
                <StatusBadge
                    :tone="ship.is_active ? 'success' : 'neutral'"
                    :label="ship.is_active ? t('common.active') : t('common.inactive')"
                />
                <button
                    v-if="canManage"
                    type="button"
                    class="btn btn-erp"
                    @click="activeTab = 'owners'"
                >
                    {{ t('ship_owners.manage') }}
                </button>
                <Link v-if="canManage" :href="route('ships.edit', ship.id)" class="btn btn-erp-ghost">
                    {{ t('common.edit') }}
                </Link>
                <Link v-if="canManage" :href="route('voyages.create')" class="btn btn-erp-ghost">
                    {{ t('voyages.add') }}
                </Link>
            </template>
        </PageHeader>

        <div class="alert alert-light border mb-3 d-md-none">
            <div class="fw-semibold">{{ t('ship_owners.where') }}</div>
            <button type="button" class="btn btn-sm btn-erp mt-2" @click="activeTab = 'owners'">
                {{ t('ship_owners.manage') }}
            </button>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('ships.voyages') }}</div>
                    <p class="erp-stat-value">{{ ship.voyages_count }}</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <button type="button" class="erp-stat is-clickable w-100 text-start border-0" @click="activeTab = 'owners'">
                    <div class="erp-stat-label">{{ t('ship_owners.title') }}</div>
                    <p class="erp-stat-value">{{ ownershipSummary.owners_count || 0 }}</p>
                    <div class="erp-stat-hint">{{ t('ship_owners.click_manage') }} · {{ ownershipSummary.total_share || '0.00' }}%</div>
                </button>
            </div>
            <div class="col-6 col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('ships.flag') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">{{ ship.flag || '—' }}</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('ships.captain') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">{{ ship.default_captain || '—' }}</p>
                </div>
            </div>
        </div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="p-3 border-bottom d-flex flex-wrap gap-2">
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="activeTab === 'overview' ? 'btn-erp' : 'btn-erp-ghost'"
                    @click="activeTab = 'overview'"
                >
                    {{ t('ships.overview') }}
                </button>
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="activeTab === 'owners' ? 'btn-erp' : 'btn-erp-ghost'"
                    @click="activeTab = 'owners'"
                >
                    {{ t('ship_owners.title') }} ({{ ownerships.length }})
                </button>
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="activeTab === 'expenses' ? 'btn-erp' : 'btn-erp-ghost'"
                    @click="activeTab = 'expenses'"
                >
                    {{ t('ship_expenses.title') }} ({{ expenses.length + contributions.length }})
                </button>
            </div>

            <div v-if="activeTab === 'overview'" class="p-4">
                <div class="alert alert-light border mb-4">
                    <div class="fw-semibold mb-1">{{ t('ships.cost_model_title') }}</div>
                    <p class="small text-secondary mb-3">{{ t('ships.cost_model_help') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-erp" @click="activeTab = 'owners'">
                            {{ t('ship_owners.manage') }}
                        </button>
                        <button type="button" class="btn btn-erp-ghost" @click="activeTab = 'expenses'">
                            {{ t('ship_expenses.title') }}
                        </button>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-secondary small">{{ t('ships.imo') }}</div>
                        <div class="fw-semibold">{{ ship.imo_number || '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-secondary small">{{ t('ships.call_sign') }}</div>
                        <div class="fw-semibold">{{ ship.call_sign || '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-secondary small">{{ t('common.notes') }}</div>
                        <div class="fw-semibold">{{ ship.notes || '—' }}</div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'owners'" class="p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="erp-panel-title mb-1">{{ t('ship_owners.title') }}</h3>
                        <p class="small text-secondary mb-0">{{ t('ship_owners.help') }}</p>
                    </div>
                    <StatusBadge
                        :tone="ownershipSummary.is_complete ? 'success' : 'warning'"
                        :label="ownershipSummary.is_complete
                            ? t('ship_owners.complete')
                            : t('ship_owners.remaining', { percent: ownershipSummary.remaining })"
                        :dot="false"
                    />
                </div>

                <div v-if="ownerships.length === 0" class="mb-3">
                    <EmptyState icon="O" :title="t('ship_owners.none')">
                        {{ t('ship_owners.none_help') }}
                    </EmptyState>
                </div>

                <div v-else class="table-responsive mb-4">
                    <table class="table erp-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ t('ship_owners.owner') }}</th>
                                <th class="text-end">{{ t('ship_owners.share') }}</th>
                                <th>{{ t('ship_owners.managing') }}</th>
                                <th>{{ t('common.date') }}</th>
                                <th v-if="canManage" class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in ownerships" :key="row.id">
                                <td>
                                    <div class="fw-semibold">{{ row.owner_name }}</div>
                                    <div class="small text-secondary">{{ row.owner_phone || row.owner_email || '—' }}</div>
                                </td>
                                <td class="text-end font-monospace fw-semibold">{{ row.share_percent }}%</td>
                                <td>
                                    <StatusBadge
                                        v-if="row.is_managing"
                                        tone="info"
                                        :label="t('ship_owners.managing')"
                                        :dot="false"
                                    />
                                    <span v-else class="text-secondary">—</span>
                                </td>
                                <td>{{ row.effective_from || '—' }}</td>
                                <td v-if="canManage" class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-erp-ghost" @click="startEditOwnership(row)">
                                            {{ t('common.edit') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeOwnership(row)">
                                            {{ t('common.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form v-if="canManage" class="erp-form-panel" @submit.prevent="submitOwnership">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4 class="h6 mb-0">
                            {{ editingOwnershipId ? t('ship_owners.edit') : t('ship_owners.add') }}
                        </h4>
                        <button
                            v-if="editingOwnershipId"
                            type="button"
                            class="btn btn-sm btn-erp-ghost"
                            @click="resetOwnershipForm"
                        >
                            {{ t('common.cancel') }}
                        </button>
                    </div>

                    <div v-if="!editingOwnershipId" class="mb-2">
                        <div class="form-check form-switch">
                            <input id="newOwnerSwitch" v-model="createNewOwner" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="newOwnerSwitch">{{ t('ship_owners.create_new') }}</label>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div v-if="!editingOwnershipId && !createNewOwner" class="col-md-4">
                            <label class="form-erp-label">{{ t('ship_owners.owner') }}</label>
                            <select v-model="ownershipForm.owner_id" class="form-select form-erp-control" required>
                                <option v-for="opt in ownerOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                            </select>
                            <InputError :message="ownershipForm.errors.owner_id" />
                        </div>
                        <template v-if="!editingOwnershipId && createNewOwner">
                            <div class="col-md-4">
                                <label class="form-erp-label">{{ t('ship_owners.owner_name') }}</label>
                                <input v-model="ownershipForm.owner_name" class="form-control form-erp-control" required />
                                <InputError :message="ownershipForm.errors.owner_name" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-erp-label">{{ t('ship_owners.phone') }}</label>
                                <input v-model="ownershipForm.owner_phone" class="form-control form-erp-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-erp-label">{{ t('common.email') }}</label>
                                <input v-model="ownershipForm.owner_email" type="email" class="form-control form-erp-control" />
                            </div>
                        </template>
                        <div class="col-md-3">
                            <label class="form-erp-label">{{ t('ship_owners.share') }} %</label>
                            <input v-model.number="ownershipForm.share_percent" type="number" min="0.01" max="100" step="0.01" class="form-control form-erp-control" required />
                            <InputError :message="ownershipForm.errors.share_percent" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-erp-label">{{ t('ship_owners.effective_from') }}</label>
                            <input v-model="ownershipForm.effective_from" type="date" class="form-control form-erp-control" />
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input id="managingOwner" v-model="ownershipForm.is_managing" class="form-check-input" type="checkbox" />
                                <label class="form-check-label" for="managingOwner">{{ t('ship_owners.managing') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-erp-label">{{ t('common.notes') }}</label>
                            <input v-model="ownershipForm.notes" class="form-control form-erp-control" />
                        </div>
                    </div>
                    <div class="erp-form-actions">
                        <button type="submit" class="btn btn-erp" :disabled="ownershipForm.processing">
                            {{ ownershipForm.processing ? t('common.saving') : (editingOwnershipId ? t('users.save_changes') : t('ship_owners.add')) }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-else-if="activeTab === 'expenses'" class="p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="erp-panel-title mb-1">{{ t('ship_expenses.title') }}</h3>
                        <p class="small text-secondary mb-0">{{ t('ship_expenses.help') }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <select v-model="ledgerCurrency" class="form-select form-select-sm form-erp-control" style="width: auto">
                            <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                        </select>
                        <StatusBadge
                            v-for="total in expenseTotals"
                            :key="total.currency"
                            tone="info"
                            :label="`${total.total} ${total.currency}`"
                            :dot="false"
                        />
                    </div>
                </div>

                <div v-if="partnerSummary" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="erp-stat h-100">
                            <div class="erp-stat-label">{{ t('ship_expenses.total_expenses') }}</div>
                            <p class="erp-stat-value"><MoneyAmount :value="partnerSummary.total_expenses" :currency="ledgerCurrency" /></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="erp-stat h-100">
                            <div class="erp-stat-label">{{ t('ship_expenses.spender_paid', { name: partnerSummary.spender_name || '—' }) }}</div>
                            <p class="erp-stat-value"><MoneyAmount :value="partnerSummary.spender_paid" :currency="ledgerCurrency" /></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="erp-stat h-100">
                            <div class="erp-stat-label">{{ t('ship_expenses.payer_paid', { name: partnerSummary.other_name || '—' }) }}</div>
                            <p class="erp-stat-value"><MoneyAmount :value="partnerSummary.others_paid" :currency="ledgerCurrency" /></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="erp-stat h-100" :class="differenceTone">
                            <div class="erp-stat-label">{{ t('ship_expenses.difference') }}</div>
                            <p class="erp-stat-value"><MoneyAmount :value="partnerSummary.difference" :currency="ledgerCurrency" /></p>
                            <p class="erp-stat-hint mb-0">{{ settlementHint }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="partnerSummary?.partners?.length" class="table-responsive mb-4">
                    <table class="table erp-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ t('ship_partners.partner') }}</th>
                                <th class="text-end">%</th>
                                <th class="text-end">{{ t('ship_expenses.fair_share') }}</th>
                                <th class="text-end">{{ t('common.amount') }}</th>
                                <th class="text-end">{{ t('ship_expenses.variance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="partner in partnerSummary.partners" :key="partner.ownership_id">
                                <td>
                                    {{ partner.owner_name }}
                                    <StatusBadge v-if="partner.is_spender" tone="info" :label="t('ship_owners.managing')" :dot="false" />
                                </td>
                                <td class="text-end font-monospace">{{ partner.share_percent }}</td>
                                <td class="text-end"><MoneyAmount :value="partner.fair_share" /></td>
                                <td class="text-end"><MoneyAmount :value="partner.paid_formatted" /></td>
                                <td class="text-end"><MoneyAmount :value="partner.variance" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-7">
                        <h4 class="h6 mb-2">{{ t('ship_expenses.title') }}</h4>
                        <div v-if="filteredExpenses.length === 0">
                            <EmptyState icon="E" :title="t('ship_expenses.none')">
                                {{ t('ship_expenses.none_help') }}
                            </EmptyState>
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table erp-table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ t('common.date') }}</th>
                                        <th>{{ t('ship_expenses.reason') }}</th>
                                        <th>{{ t('ship_expenses.paid_by') }}</th>
                                        <th class="text-end">{{ t('common.amount') }}</th>
                                        <th>{{ t('voyage_expenses.journal') }}</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody v-for="group in expensesByMonth" :key="group.month">
                                    <tr class="table-light">
                                        <td colspan="3" class="fw-semibold">{{ t('ship_expenses.month') }} {{ group.month }}</td>
                                        <td class="text-end font-monospace fw-semibold">{{ group.total.toFixed(2) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr v-for="expense in group.rows" :key="expense.id">
                                        <td>{{ expense.expense_date }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ expense.vendor || expense.expense_type_label }}</div>
                                            <div class="small text-secondary">{{ expense.expense_type_label }}<span v-if="expense.reference"> · {{ expense.reference }}</span></div>
                                        </td>
                                        <td>{{ expense.paid_by_owner_name || partnerSummary?.spender_name || '—' }}</td>
                                        <td class="text-end"><MoneyAmount :value="expense.amount" /></td>
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
                                        <td class="text-end">
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
                                                    <div v-else class="d-flex flex-column gap-1 align-items-end">
                                                        <select v-model="postForm.mode" class="form-select form-select-sm form-erp-control" style="min-width: 12rem">
                                                            <option value="partner">{{ t('ship_expenses.post_partner') }}</option>
                                                            <option value="cash">{{ t('ship_expenses.post_cash') }}</option>
                                                        </select>
                                                        <select
                                                            v-if="postForm.mode === 'cash'"
                                                            v-model="postForm.payment_account_id"
                                                            class="form-select form-select-sm form-erp-control"
                                                            style="min-width: 12rem"
                                                        >
                                                            <option
                                                                v-for="account in (paymentAccounts[expense.currency] || [])"
                                                                :key="account.id"
                                                                :value="account.id"
                                                            >
                                                                {{ account.label }}
                                                            </option>
                                                        </select>
                                                        <div class="d-flex gap-1">
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-erp"
                                                                :class="{ 'is-posting': postForm.processing }"
                                                                :disabled="postForm.processing || (postForm.mode === 'cash' && !postForm.payment_account_id)"
                                                                @click="submitPostExpense(expense)"
                                                            >
                                                                {{ postForm.processing ? t('common.posting') : t('voyage_expenses.confirm_post') }}
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-erp-ghost" @click="cancelPostExpense">
                                                                {{ t('common.cancel') }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template v-if="canManage && !expense.is_posted">
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
                    </div>
                    <div class="col-lg-5">
                        <h4 class="h6 mb-2">{{ t('ship_partners.title') }}</h4>
                        <p class="small text-secondary">{{ t('ship_partners.help') }}</p>
                        <div v-if="filteredContributions.length === 0" class="mb-3">
                            <EmptyState icon="P" :title="t('ship_partners.none')">
                                {{ t('ship_partners.none_help') }}
                            </EmptyState>
                        </div>
                        <div v-else class="table-responsive mb-3">
                            <table class="table erp-table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ t('common.date') }}</th>
                                        <th>{{ t('ship_expenses.reason') }}</th>
                                        <th class="text-end">{{ t('common.amount') }}</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in filteredContributions" :key="row.id">
                                        <td>{{ row.contribution_date }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ row.description || row.owner_name }}</div>
                                            <div class="small text-secondary">
                                                {{ row.owner_name }}
                                                <Link
                                                    v-if="row.is_posted && row.journal_entry_id"
                                                    :href="route('journals.show', row.journal_entry_id)"
                                                    class="ms-1"
                                                >
                                                    {{ row.journal_voucher }}
                                                </Link>
                                            </div>
                                        </td>
                                        <td class="text-end"><MoneyAmount :value="row.amount" /></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                <template v-if="canPostAccounting && row.can_post">
                                                    <button
                                                        v-if="postingContributionId !== row.id"
                                                        type="button"
                                                        class="btn btn-sm btn-erp"
                                                        @click="startPostContribution(row)"
                                                    >
                                                        {{ t('voyage_expenses.post') }}
                                                    </button>
                                                    <div v-else class="d-flex flex-wrap gap-1 align-items-center">
                                                        <select
                                                            v-model="contributionPostForm.payment_account_id"
                                                            class="form-select form-select-sm form-erp-control"
                                                            style="min-width: 10rem"
                                                        >
                                                            <option
                                                                v-for="account in (paymentAccounts[row.currency] || [])"
                                                                :key="account.id"
                                                                :value="account.id"
                                                            >
                                                                {{ account.label }}
                                                            </option>
                                                        </select>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-erp"
                                                            :class="{ 'is-posting': contributionPostForm.processing }"
                                                            :disabled="contributionPostForm.processing || !contributionPostForm.payment_account_id"
                                                            @click="submitPostContribution(row)"
                                                        >
                                                            {{ contributionPostForm.processing ? t('common.posting') : t('voyage_expenses.confirm_post') }}
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-erp-ghost" @click="cancelPostContribution">
                                                            {{ t('common.cancel') }}
                                                        </button>
                                                    </div>
                                                </template>
                                                <template v-if="canManage && !row.is_posted">
                                                    <button type="button" class="btn btn-sm btn-erp-ghost" @click="startEditContribution(row)">
                                                        {{ t('common.edit') }}
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="removeContribution(row)">
                                                        {{ t('common.delete') }}
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-if="canManage" class="row g-3">
                    <div class="col-lg-7">
                        <form class="erp-form-panel mb-3" @submit.prevent="submitExpense">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h4 class="h6 mb-0">{{ editingExpenseId ? t('ship_expenses.edit') : t('ship_expenses.add') }}</h4>
                                <button v-if="editingExpenseId" type="button" class="btn btn-sm btn-erp-ghost" @click="resetExpenseForm">
                                    {{ t('common.cancel') }}
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-erp-label">{{ t('ship_expenses.type') }}</label>
                                    <select v-model="expenseForm.expense_type" class="form-select form-erp-control" required>
                                        <option v-for="type in expenseTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-erp-label">{{ t('common.date') }}</label>
                                    <input v-model="expenseForm.expense_date" type="date" class="form-control form-erp-control" required />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-erp-label">{{ t('common.amount') }}</label>
                                    <input v-model.number="expenseForm.amount" type="number" min="0.01" step="0.01" class="form-control form-erp-control" required />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-erp-label">{{ t('common.currency') }}</label>
                                    <select v-model="expenseForm.currency" class="form-select form-erp-control" required>
                                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-erp-label">{{ t('ship_expenses.vendor') }}</label>
                                    <input v-model="expenseForm.vendor" class="form-control form-erp-control" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-erp-label">{{ t('common.reference') }}</label>
                                    <input v-model="expenseForm.reference" class="form-control form-erp-control" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-erp-label">{{ t('ship_expenses.paid_by') }}</label>
                                    <select v-model="expenseForm.paid_by_owner_id" class="form-select form-erp-control">
                                        <option :value="null">{{ t('ship_expenses.paid_by_default') }}</option>
                                        <option v-for="owner in ownerships" :key="owner.owner_id" :value="owner.owner_id">
                                            {{ owner.owner_name }}
                                        </option>
                                    </select>
                                    <InputError :message="expenseForm.errors.paid_by_owner_id" />
                                </div>
                            </div>
                            <p class="small text-secondary mb-0 mt-3">{{ t('ship_expenses.paid_by_help') }}</p>
                            <p class="small text-secondary mb-0 mt-1">{{ t('ship_expenses.accounting_note') }}</p>
                            <div class="erp-form-actions">
                                <button type="submit" class="btn btn-erp" :disabled="expenseForm.processing">
                                    {{ expenseForm.processing ? t('common.saving') : (editingExpenseId ? t('users.save_changes') : t('ship_expenses.add')) }}
                                </button>
                            </div>
                        </form>

                        <form ref="expenseListPanel" class="erp-form-panel mb-3" @submit.prevent="submitExpenseList">
                            <h4 class="h6 mb-2">{{ t('ship_expenses.list_add') }}</h4>
                            <p v-if="expensePreviewNote" class="small text-success mb-2">{{ expensePreviewNote }}</p>
                            <div v-for="(row, index) in expenseListForm.rows" :key="index" class="row g-2 mb-2">
                                <div class="col-md-2">
                                    <input v-model="row.expense_date" type="date" class="form-control form-erp-control" />
                                </div>
                                <div class="col-md-3">
                                    <input v-model="row.vendor" class="form-control form-erp-control" :placeholder="t('ship_expenses.reason')" />
                                </div>
                                <div class="col-md-2">
                                    <input v-model.number="row.amount" type="number" min="0" step="0.01" class="form-control form-erp-control" :placeholder="t('common.amount')" />
                                </div>
                                <div class="col-md-2">
                                    <select v-model="row.expense_type" class="form-select form-erp-control">
                                        <option v-for="type in expenseTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select v-model="row.paid_by_owner_id" class="form-select form-erp-control">
                                        <option :value="null">{{ t('ship_expenses.paid_by_default') }}</option>
                                        <option v-for="owner in ownerships" :key="owner.owner_id" :value="owner.owner_id">
                                            {{ owner.owner_name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <InputError :message="expenseListForm.errors.rows" />
                            <div class="erp-form-actions">
                                <button type="button" class="btn btn-erp-ghost" @click="addExpenseListRow">{{ t('ship_expenses.add_row') }}</button>
                                <button type="submit" class="btn btn-erp" :disabled="expenseListForm.processing">
                                    {{ expenseListForm.processing ? t('common.saving') : t('ship_expenses.save_list') }}
                                </button>
                            </div>
                        </form>

                        <form class="erp-form-panel" @submit.prevent="submitExpenseImport">
                            <h4 class="h6 mb-1">{{ t('ship_expenses.import_excel') }}</h4>
                            <p class="small text-secondary mb-2">{{ t('ship_expenses.import_help') }}</p>
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <input type="file" class="form-control form-erp-control" accept=".xlsx,.xls,.csv" @change="onExpenseFile" />
                                    <InputError :message="expenseImportForm.errors.file" />
                                </div>
                                <div class="col-md-3">
                                    <select v-model="expenseImportForm.currency" class="form-select form-erp-control">
                                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select v-model="expenseImportForm.paid_by_owner_id" class="form-select form-erp-control">
                                        <option :value="null">{{ t('ship_expenses.paid_by_default') }}</option>
                                        <option v-for="owner in ownerships" :key="owner.owner_id" :value="owner.owner_id">
                                            {{ owner.owner_name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="erp-form-actions">
                                <button type="submit" class="btn btn-erp" :disabled="expensePreviewing || !expenseImportForm.file">
                                    {{ expensePreviewing ? t('ship_expenses.import_reading') : t('ship_expenses.import_excel') }}
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        <form class="erp-form-panel mb-3" @submit.prevent="submitContribution">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h4 class="h6 mb-0">{{ editingContributionId ? t('ship_partners.edit') : t('ship_partners.add') }}</h4>
                                <button v-if="editingContributionId" type="button" class="btn btn-sm btn-erp-ghost" @click="resetContributionForm">
                                    {{ t('common.cancel') }}
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-erp-label">{{ t('ship_partners.partner') }}</label>
                                    <select v-model="contributionForm.owner_id" class="form-select form-erp-control" required>
                                        <option v-for="owner in ownerships" :key="owner.owner_id" :value="owner.owner_id">{{ owner.owner_name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('common.date') }}</label>
                                    <input v-model="contributionForm.contribution_date" type="date" class="form-control form-erp-control" required />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('common.amount') }}</label>
                                    <input v-model.number="contributionForm.amount" type="number" min="0.01" step="0.01" class="form-control form-erp-control" required />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('common.currency') }}</label>
                                    <select v-model="contributionForm.currency" class="form-select form-erp-control" required>
                                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-erp-label">{{ t('ship_expenses.reason') }}</label>
                                    <input v-model="contributionForm.description" class="form-control form-erp-control" />
                                </div>
                            </div>
                            <p class="small text-secondary mb-0 mt-3">{{ t('ship_partners.accounting_note') }}</p>
                            <div class="erp-form-actions">
                                <button type="submit" class="btn btn-erp" :disabled="contributionForm.processing">
                                    {{ contributionForm.processing ? t('common.saving') : (editingContributionId ? t('users.save_changes') : t('ship_partners.add')) }}
                                </button>
                            </div>
                        </form>

                        <form ref="paymentListPanel" class="erp-form-panel mb-3" @submit.prevent="submitPaymentList">
                            <h4 class="h6 mb-2">{{ t('ship_partners.list_add') }}</h4>
                            <p v-if="paymentPreviewNote" class="small text-success mb-2">{{ paymentPreviewNote }}</p>
                            <div v-for="(row, index) in paymentListForm.rows" :key="index" class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <input v-model="row.contribution_date" type="date" class="form-control form-erp-control" />
                                </div>
                                <div class="col-md-5">
                                    <input v-model="row.description" class="form-control form-erp-control" :placeholder="t('ship_expenses.reason')" />
                                </div>
                                <div class="col-md-3">
                                    <input v-model.number="row.amount" type="number" min="0" step="0.01" class="form-control form-erp-control" :placeholder="t('common.amount')" />
                                </div>
                            </div>
                            <InputError :message="paymentListForm.errors.rows" />
                            <div class="erp-form-actions">
                                <button type="button" class="btn btn-erp-ghost" @click="addPaymentListRow">{{ t('ship_expenses.add_row') }}</button>
                                <button type="submit" class="btn btn-erp" :disabled="paymentListForm.processing">
                                    {{ paymentListForm.processing ? t('common.saving') : t('ship_expenses.save_list') }}
                                </button>
                            </div>
                        </form>

                        <form class="erp-form-panel" @submit.prevent="submitPaymentImport">
                            <h4 class="h6 mb-1">{{ t('ship_partners.import_excel') }}</h4>
                            <p class="small text-secondary mb-2">{{ t('ship_partners.import_help') }}</p>
                            <div class="row g-2">
                                <div class="col-12">
                                    <select v-model="paymentImportForm.owner_id" class="form-select form-erp-control">
                                        <option v-for="owner in ownerships" :key="owner.owner_id" :value="owner.owner_id">{{ owner.owner_name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <input type="file" class="form-control form-erp-control" accept=".xlsx,.xls,.csv" @change="onPaymentFile" />
                                    <InputError :message="paymentImportForm.errors.file" />
                                </div>
                                <div class="col-md-4">
                                    <select v-model="paymentImportForm.currency" class="form-select form-erp-control">
                                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="erp-form-actions">
                                <button type="submit" class="btn btn-erp" :disabled="paymentPreviewing || !paymentImportForm.file">
                                    {{ paymentPreviewing ? t('ship_expenses.import_reading') : t('ship_partners.import_excel') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
