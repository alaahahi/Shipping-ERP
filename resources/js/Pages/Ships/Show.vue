<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    ship: { type: Object, required: true },
    ownerships: { type: Array, default: () => [] },
    ownershipSummary: { type: Object, default: () => ({}) },
    ownerOptions: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    expenseTotals: { type: Array, default: () => [] },
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
const createNewOwner = ref(props.ownerOptions.length === 0);

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
});

const postForm = useForm({
    payment_account_id: null,
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
    if (!window.confirm(t('ship_expenses.post_confirm'))) return;
    postForm.post(route('ships.expenses.post', [props.ship.id, expense.id]), {
        preserveScroll: true,
        onSuccess: () => cancelPostExpense(),
    });
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
                    {{ t('ship_expenses.title') }} ({{ expenses.length }})
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
                    <EmptyState icon="E" :title="t('ship_expenses.none')">
                        {{ t('ship_expenses.none_help') }}
                    </EmptyState>
                </div>

                <div v-else class="table-responsive mb-4">
                    <table class="table erp-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ t('common.date') }}</th>
                                <th>{{ t('ship_expenses.type') }}</th>
                                <th>{{ t('ship_expenses.vendor') }}</th>
                                <th class="text-end">{{ t('common.amount') }}</th>
                                <th>{{ t('common.currency') }}</th>
                                <th>{{ t('voyage_expenses.journal') }}</th>
                                <th class="text-end"></th>
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

                <form v-if="canManage" class="erp-form-panel" @submit.prevent="submitExpense">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h4 class="h6 mb-0">
                            {{ editingExpenseId ? t('ship_expenses.edit') : t('ship_expenses.add') }}
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
                            <label class="form-erp-label">{{ t('ship_expenses.type') }}</label>
                            <select v-model="expenseForm.expense_type" class="form-select form-erp-control" required>
                                <option v-for="type in expenseTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-erp-label">{{ t('common.date') }}</label>
                            <input v-model="expenseForm.expense_date" type="date" class="form-control form-erp-control" required />
                        </div>
                        <div class="col-md-3">
                            <label class="form-erp-label">{{ t('common.amount') }}</label>
                            <input v-model.number="expenseForm.amount" type="number" min="0.01" step="0.01" class="form-control form-erp-control" required />
                        </div>
                        <div class="col-md-3">
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
                            <label class="form-erp-label">{{ t('common.notes') }}</label>
                            <input v-model="expenseForm.notes" class="form-control form-erp-control" />
                        </div>
                    </div>
                    <p class="small text-secondary mb-0 mt-3">{{ t('ship_expenses.accounting_note') }}</p>
                    <div class="erp-form-actions">
                        <button type="submit" class="btn btn-erp" :disabled="expenseForm.processing">
                            {{ expenseForm.processing ? t('common.saving') : (editingExpenseId ? t('users.save_changes') : t('ship_expenses.add')) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
