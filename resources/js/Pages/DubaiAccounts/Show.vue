<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    partner: { type: Object, required: true },
    ledger: { type: Object, required: true },
    entryKinds: { type: Array, default: () => [] },
    selectedEntry: { type: Object, default: null },
    cars: { type: Array, default: () => [] },
    importPreview: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const openBalance = computed(() => Number(props.ledger.open_balance) || 0);
const balanceToneClass = computed(() => {
    if (openBalance.value > 0) return 'is-owing';
    if (openBalance.value < 0) return 'is-owed';
    return 'is-settled';
});
const balanceHint = computed(() => {
    if (openBalance.value > 0) return t('dubai_accounts.balance_they_owe');
    if (openBalance.value < 0) return t('dubai_accounts.balance_we_owe');
    return t('dubai_accounts.balance_settled');
});

const entryForm = useForm({
    entry_date: new Date().toISOString().slice(0, 10),
    doc_no: '',
    entry_kind: 'shipment',
    transport_qty: null,
    transport_rate: null,
    forklift_qty: null,
    forklift_rate: 50,
    debit: null,
    credit: null,
    usd_amount: null,
    notes: '',
});

const soaForm = useForm({
    file: null,
    replace: false,
});

const carForm = useForm({
    file: null,
});

watch(
    () => entryForm.entry_kind,
    (kind) => {
        if (kind === 'shipment') {
            entryForm.credit = null;
        }
    }
);

const submitEntry = () => {
    entryForm.post(route('dubai-accounts.entries.store', props.partner.id), {
        preserveScroll: true,
        onSuccess: () => {
            entryForm.reset('doc_no', 'transport_qty', 'transport_rate', 'forklift_qty', 'debit', 'credit', 'usd_amount', 'notes');
            entryForm.entry_kind = 'shipment';
            entryForm.forklift_rate = 50;
            entryForm.entry_date = new Date().toISOString().slice(0, 10);
        },
    });
};

const onSoaFile = (event) => {
    soaForm.file = event.target.files?.[0] ?? null;
};

const importSoa = () => {
    soaForm.post(route('dubai-accounts.import-soa', props.partner.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            soaForm.reset();
            soaForm.replace = false;
        },
    });
};

const removeEntry = (row) => {
    if (!window.confirm(t('dubai_accounts.delete_entry_confirm'))) return;
    router.delete(route('dubai-accounts.entries.destroy', [props.partner.id, row.id]), {
        preserveScroll: true,
    });
};

const selectEntry = (row) => {
    router.get(route('dubai-accounts.show', props.partner.id), { entry: row.id }, { preserveState: true, preserveScroll: true });
};

const onCarFile = (event) => {
    carForm.file = event.target.files?.[0] ?? null;
};

const previewCars = () => {
    if (!props.selectedEntry) return;
    carForm.post(route('dubai-accounts.cars.preview', [props.partner.id, props.selectedEntry.id]), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const confirmCars = () => {
    if (!props.selectedEntry) return;
    router.post(route('dubai-accounts.cars.confirm', [props.partner.id, props.selectedEntry.id]), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="partner.name" />
    <AppLayout>
        <template #header>{{ partner.name }}</template>
        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link :href="route('dubai-accounts.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('dubai_accounts.back') }}
            </Link>
        </div>

        <PageHeader :kicker="t('dubai_accounts.title')" :title="partner.name" :subtitle="t('dubai_accounts.ledger_help')">
            <template #actions>
                <StatusBadge
                    :tone="partner.is_active ? 'success' : 'neutral'"
                    :label="partner.is_active ? t('common.active') : t('common.inactive')"
                />
                <Link v-if="canManage" :href="route('dubai-accounts.edit', partner.id)" class="btn btn-erp-ghost">
                    {{ t('common.edit') }}
                </Link>
            </template>
        </PageHeader>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="erp-stat" :class="balanceToneClass">
                    <div class="erp-stat-label">{{ t('dubai_accounts.open_balance') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.2rem">
                        <MoneyAmount :value="ledger.open_balance" tone="balance" :currency="ledger.currency" />
                    </p>
                    <div class="erp-balance-hint" :class="balanceToneClass">{{ balanceHint }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('journals.debit') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">
                        <MoneyAmount :value="ledger.total_debit" tone="debit" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('journals.credit') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.1rem">
                        <MoneyAmount :value="ledger.total_credit" tone="credit" show-zero />
                    </p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('dubai_accounts.contact') }}</div>
                    <p class="erp-stat-value" style="font-size: 1rem">
                        {{ partner.contact_name || '—' }}
                        <span v-if="partner.contact_phone" class="d-block small text-secondary fw-normal">
                            {{ partner.contact_phone }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <div v-if="canManage" class="row g-3 mb-3">
            <div class="col-lg-5">
                <form class="erp-form-panel h-100" @submit.prevent="importSoa">
                    <h4 class="h6 mb-1">{{ t('dubai_accounts.import_soa') }}</h4>
                    <p class="small text-secondary mb-3">{{ t('dubai_accounts.import_soa_help') }}</p>
                    <input type="file" class="form-control form-erp-control mb-2" accept=".xlsx,.xls,.csv" @change="onSoaFile" />
                    <InputError :message="soaForm.errors.file" />
                    <div class="form-check mb-2">
                        <input id="replaceSoa" v-model="soaForm.replace" class="form-check-input" type="checkbox" />
                        <label class="form-check-label" for="replaceSoa">{{ t('dubai_accounts.replace_entries') }}</label>
                    </div>
                    <div class="erp-form-actions">
                        <button type="submit" class="btn btn-erp" :disabled="soaForm.processing || !soaForm.file">
                            {{ soaForm.processing ? t('common.saving') : t('dubai_accounts.import_soa') }}
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-lg-7">
                <form class="erp-form-panel h-100" @submit.prevent="submitEntry">
                    <h4 class="h6 mb-2">{{ t('dubai_accounts.add_entry') }}</h4>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('common.date') }}</label>
                            <input v-model="entryForm.entry_date" type="date" class="form-control form-erp-control" required />
                        </div>
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('dubai_accounts.doc_no') }}</label>
                            <input v-model="entryForm.doc_no" class="form-control form-erp-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-erp-label">{{ t('dubai_accounts.kind') }}</label>
                            <select v-model="entryForm.entry_kind" class="form-select form-erp-control" required>
                                <option v-for="kind in entryKinds" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                            </select>
                        </div>
                        <template v-if="entryForm.entry_kind === 'shipment'">
                            <div class="col-md-3">
                                <label class="form-erp-label">{{ t('dubai_accounts.transport_qty') }}</label>
                                <input v-model.number="entryForm.transport_qty" type="number" min="0" step="1" class="form-control form-erp-control" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-erp-label">{{ t('dubai_accounts.transport_rate') }}</label>
                                <input v-model.number="entryForm.transport_rate" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-erp-label">{{ t('dubai_accounts.forklift_qty') }}</label>
                                <input v-model.number="entryForm.forklift_qty" type="number" min="0" step="1" class="form-control form-erp-control" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-erp-label">{{ t('journals.debit') }} AED</label>
                                <input v-model.number="entryForm.debit" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                            </div>
                        </template>
                        <template v-else>
                            <div class="col-md-4">
                                <label class="form-erp-label">{{ t('journals.debit') }} AED</label>
                                <input v-model.number="entryForm.debit" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-erp-label">{{ t('journals.credit') }} AED</label>
                                <input v-model.number="entryForm.credit" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-erp-label">{{ t('dubai_accounts.usd_amount') }}</label>
                                <input v-model.number="entryForm.usd_amount" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                            </div>
                        </template>
                        <div class="col-12">
                            <label class="form-erp-label">{{ t('common.notes') }}</label>
                            <input v-model="entryForm.notes" class="form-control form-erp-control" />
                            <InputError :message="entryForm.errors.debit || entryForm.errors.credit" />
                        </div>
                    </div>
                    <div class="erp-form-actions">
                        <button type="submit" class="btn btn-erp" :disabled="entryForm.processing">
                            {{ entryForm.processing ? t('common.saving') : t('dubai_accounts.add_entry') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="erp-card p-0 overflow-hidden mb-3">
            <div class="p-3 border-bottom">
                <h3 class="erp-panel-title mb-0">{{ t('dubai_accounts.statement') }}</h3>
                <p class="small text-secondary mb-0">{{ t('dubai_accounts.statement_help') }}</p>
            </div>
            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">{{ t('common.date') }}</th>
                            <th>{{ t('dubai_accounts.doc_no') }}</th>
                            <th>{{ t('dubai_accounts.kind') }}</th>
                            <th class="text-end">{{ t('dubai_accounts.transport_qty') }}</th>
                            <th class="text-end">{{ t('dubai_accounts.forklift_qty') }}</th>
                            <th class="text-end">{{ t('journals.debit') }}</th>
                            <th class="text-end">{{ t('journals.credit') }}</th>
                            <th class="text-end">{{ t('dubai_accounts.balance') }}</th>
                            <th class="text-end pe-3">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="ledger.movements.length === 0">
                            <td colspan="9">
                                <EmptyState icon="$">{{ t('dubai_accounts.no_movements') }}</EmptyState>
                            </td>
                        </tr>
                        <tr
                            v-for="row in ledger.movements"
                            :key="row.id"
                            :class="{ 'table-active': selectedEntry?.id === row.id }"
                        >
                            <td class="ps-3">{{ row.date }}</td>
                            <td>
                                <div class="fw-semibold">{{ row.doc_no || '—' }}</div>
                                <div v-if="row.usd_amount" class="small text-secondary">${{ row.usd_amount }}</div>
                            </td>
                            <td>
                                <StatusBadge tone="neutral" :label="row.entry_kind_label" :dot="false" />
                                <div v-if="row.cars_count" class="small text-secondary mt-1">
                                    {{ t('dubai_accounts.cars_count', { count: row.cars_count }) }}
                                </div>
                            </td>
                            <td class="text-end font-monospace">{{ row.transport_qty || '—' }}</td>
                            <td class="text-end font-monospace">{{ row.forklift_qty || '—' }}</td>
                            <td class="text-end"><MoneyAmount :value="row.debit" tone="debit" /></td>
                            <td class="text-end"><MoneyAmount :value="row.credit" tone="credit" /></td>
                            <td class="text-end"><MoneyAmount :value="row.balance" tone="balance" /></td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    <button
                                        v-if="row.can_import_cars"
                                        type="button"
                                        class="btn btn-sm btn-erp-ghost"
                                        @click="selectEntry(row)"
                                    >
                                        {{ t('dubai_accounts.cars') }}
                                    </button>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="removeEntry(row)"
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

        <div v-if="selectedEntry" class="erp-card p-4">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div>
                    <h3 class="erp-panel-title mb-1">{{ t('dubai_accounts.cars_for') }} · {{ selectedEntry.doc_no || selectedEntry.id }}</h3>
                    <p class="small text-secondary mb-0">{{ t('dubai_accounts.cars_help') }}</p>
                </div>
                <Link :href="route('dubai-accounts.show', partner.id)" class="btn btn-sm btn-erp-ghost">
                    {{ t('common.cancel') }}
                </Link>
            </div>

            <form v-if="canManage && selectedEntry.can_import_cars" class="erp-form-panel mb-3" @submit.prevent="previewCars">
                <h4 class="h6 mb-2">{{ t('dubai_accounts.import_cars') }}</h4>
                <input type="file" class="form-control form-erp-control mb-2" accept=".xlsx,.xls,.csv" @change="onCarFile" />
                <InputError :message="carForm.errors.file" />
                <div class="erp-form-actions">
                    <button type="submit" class="btn btn-erp" :disabled="carForm.processing || !carForm.file">
                        {{ carForm.processing ? t('voyage_cars.previewing') : t('voyage_cars.preview') }}
                    </button>
                    <button
                        v-if="importPreview"
                        type="button"
                        class="btn btn-erp-ghost"
                        @click="confirmCars"
                    >
                        {{ t('voyage_cars.confirm_import') }}
                    </button>
                </div>
            </form>

            <div v-if="importPreview" class="mb-3">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <StatusBadge tone="success" :label="`${t('voyage_cars.valid')}: ${importPreview.valid}`" :dot="false" />
                    <StatusBadge tone="warning" :label="`${t('voyage_cars.duplicates')}: ${importPreview.duplicates}`" :dot="false" />
                    <StatusBadge tone="neutral" :label="`${t('voyage_cars.skipped')}: ${importPreview.skipped}`" :dot="false" />
                </div>
            </div>

            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ t('voyage_cars.chassis') }}</th>
                            <th>{{ t('voyage_cars.consignee') }}</th>
                            <th>{{ t('voyage_cars.shipper') }}</th>
                            <th>{{ t('voyage_cars.description') }}</th>
                            <th class="text-end">{{ t('voyage_cars.weight') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="cars.length === 0">
                            <td colspan="5" class="text-center text-secondary py-4">{{ t('dubai_accounts.no_cars') }}</td>
                        </tr>
                        <tr v-for="car in cars" :key="car.id">
                            <td class="font-monospace fw-semibold">{{ car.chassis_no || '—' }}</td>
                            <td>{{ car.consignee_name }}</td>
                            <td>{{ car.shipper_name || '—' }}</td>
                            <td>{{ car.description || '—' }}</td>
                            <td class="text-end font-monospace">{{ car.weight || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
