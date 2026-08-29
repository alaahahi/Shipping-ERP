<script setup>
import AccountMovementModal from '@/Components/AccountMovementModal.vue';
import AccountNotesPanel from '@/Components/AccountNotesPanel.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { useActionPin } from '@/composables/useActionPin';
import { fbButton, fbDangerButton, fbGhostButton, fbInput, fbLabel, fbLink, fbSuccessButton } from '@/flowbite';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    account: { type: Object, required: true },
    tab: { type: String, default: 'ledger' },
    filters: { type: Object, default: () => ({}) },
    period_debit: { type: String, required: true },
    period_credit: { type: String, required: true },
    period_net: { type: String, required: true },
    lines: { type: Object, required: true },
    notes: { type: Object, default: () => ({ data: [] }) },
    counterpartAccounts: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();
const { requireActionPin } = useActionPin();
const movementOpen = ref(false);
const movementType = ref('receipt');
const previewUrl = ref(null);
let filterTimer = null;

const tabClass = (active) => [
    'inline-flex items-center px-4 py-2 text-sm font-medium border-b-2',
    active
        ? 'text-teal-700 border-teal-700 dark:text-teal-400 dark:border-teal-400'
        : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600',
].join(' ');

const filterForm = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    voucher: props.filters.voucher ?? '',
    description: props.filters.description ?? '',
    amount: props.filters.amount ?? '',
});

const editingLine = ref(null);
const editForm = useForm({
    description: '',
    attachment: null,
    remove_attachment: false,
});

const toggleDashboard = () => {
    router.post(route('accounts.dashboard.toggle', props.account.id), {}, {
        preserveScroll: true,
    });
};

const applyFilters = () => {
    filterForm.get(route('accounts.show', props.account.id), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const scheduleFilters = (delay = 350) => {
    if (filterTimer) {
        clearTimeout(filterTimer);
    }

    filterTimer = setTimeout(() => {
        applyFilters();
    }, delay);
};

watch(
    () => [filterForm.date_from, filterForm.date_to],
    () => scheduleFilters(0)
);

watch(
    () => [filterForm.voucher, filterForm.description, filterForm.amount],
    () => scheduleFilters(350)
);

onBeforeUnmount(() => {
    if (filterTimer) {
        clearTimeout(filterTimer);
    }
});

const exportQuery = () => ({
    date_from: filterForm.date_from || undefined,
    date_to: filterForm.date_to || undefined,
    voucher: filterForm.voucher || undefined,
    description: filterForm.description || undefined,
    amount: filterForm.amount || undefined,
});

const exportHref = (name) => route(name, {
    account: props.account.id,
    ...exportQuery(),
});

const openMovement = (type) => {
    movementType.value = type;
    movementOpen.value = true;
};

const openEdit = (line) => {
    editingLine.value = line;
    editForm.clearErrors();
    editForm.description = line.description || '';
    editForm.attachment = null;
    editForm.remove_attachment = false;
};

const onEditFile = (event) => {
    editForm.attachment = event.target.files?.[0] ?? null;
    editForm.remove_attachment = false;
};

const saveEdit = () => {
    if (!editingLine.value) {
        return;
    }

    editForm.post(route('accounts.journals.update', [props.account.id, editingLine.value.journal_entry_id]), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            editingLine.value = null;
        },
    });
};

const voidLine = async (line) => {
    const ok = await requireActionPin(
        t('action_pin.message_void', { voucher: line.voucher_number })
    );
    if (!ok) {
        return;
    }

    router.post(route('accounts.journals.void', [props.account.id, line.journal_entry_id]), {}, {
        preserveScroll: true,
    });
};

const reverseLine = async (line) => {
    const ok = await requireActionPin(
        t('action_pin.message_reverse', { voucher: line.voucher_number })
    );
    if (!ok) {
        return;
    }

    router.post(route('accounts.journals.reverse', [props.account.id, line.journal_entry_id]), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${tab === 'notes' ? t('common.notes') : t('accounts.ledger')} · ${account.code}`" />
    <AppLayout>
        <template #header>{{ tab === 'notes' ? t('common.notes') : t('accounts.ledger') }} · {{ account.code }}</template>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <Link :href="route('accounts.index')" class="text-sm font-semibold text-teal-700 no-underline dark:text-teal-400">
                ← {{ t('accounts.back') }}
            </Link>
            <div class="flex flex-wrap gap-2">
                <a :href="exportHref('accounts.export.pdf')" :class="fbGhostButton">
                    {{ t('accounts.print_pdf') }}
                </a>
                <a :href="exportHref('accounts.export.excel')" :class="fbGhostButton">
                    {{ t('accounts.export_excel') }}
                </a>
                <button
                    v-if="canManage"
                    type="button"
                    :class="fbGhostButton"
                    @click="toggleDashboard"
                >
                    {{ account.show_on_dashboard ? t('accounts.unpin_dashboard') : t('accounts.pin_dashboard') }}
                </button>
                <Link
                    v-if="canManage"
                    :href="route('accounts.edit', account.id)"
                    :class="fbGhostButton"
                >
                    {{ t('common.edit') }}
                </Link>
            </div>
        </div>

        <div v-if="canManage" class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <button
                type="button"
                :class="fbSuccessButton"
                class="min-h-12 text-base"
                @click="openMovement('receipt')"
            >
                {{ t('accounts.receipt') }}
            </button>
            <button
                type="button"
                :class="fbDangerButton"
                class="min-h-12 text-base"
                @click="openMovement('payment')"
            >
                {{ t('accounts.payment') }}
            </button>
        </div>

        <div class="erp-hero mb-3">
            <div class="erp-hero-kicker">{{ tab === 'notes' ? t('common.notes') : t('accounts.ledger') }}</div>
            <h2 class="erp-hero-title">{{ account.code }} — {{ account.name }}</h2>
            <p class="erp-hero-subtitle">
                {{ account.type_label }} · {{ account.currency }} · {{ t('accounts.balance') }}:
                <span class="account-ledger-hero-balance">
                    <MoneyAmount :value="account.balance" tone="balance" />
                </span>
            </p>
        </div>

        <nav
            class="mb-4 flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700"
            :aria-label="t('accounts.ledger')"
        >
            <Link :href="route('accounts.show', account.id)" :class="tabClass(tab !== 'notes')">
                {{ t('accounts.ledger') }}
            </Link>
            <Link
                :href="route('accounts.show', { account: account.id, tab: 'notes' })"
                :class="tabClass(tab === 'notes')"
            >
                {{ t('common.notes') }}
                <span
                    v-if="notes.total"
                    class="ms-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    {{ notes.total }}
                </span>
            </Link>
        </nav>

        <template v-if="tab === 'notes'">
            <AccountNotesPanel
                :account-id="account.id"
                :notes="notes"
                :can-manage="canManage"
            />
        </template>
        <template v-else>
        <div class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="erp-stat account-ledger-inflow">
                <div class="erp-stat-label">{{ t('journals.debit') }}</div>
                <p class="erp-stat-hint mb-1">{{ t('accounts.debit_meaning') }}</p>
                <p class="erp-stat-value" style="font-size: 1.2rem">
                    <MoneyAmount :value="period_debit" tone="debit" show-zero />
                </p>
            </div>
            <div class="erp-stat account-ledger-outflow">
                <div class="erp-stat-label">{{ t('journals.credit') }}</div>
                <p class="erp-stat-hint mb-1">{{ t('accounts.credit_meaning') }}</p>
                <p class="erp-stat-value" style="font-size: 1.2rem">
                    <MoneyAmount :value="period_credit" tone="credit" show-zero />
                </p>
            </div>
            <div class="erp-stat account-ledger-net">
                <div class="erp-stat-label">{{ t('accounts.period_net') }}</div>
                <p class="erp-stat-value" style="font-size: 1.2rem">
                    <MoneyAmount :value="period_net" tone="balance" show-zero />
                </p>
            </div>
        </div>

        <div class="account-ledger-filters mb-3">
            <form class="flex flex-col gap-2 p-3 sm:flex-row sm:flex-wrap sm:items-center" @submit.prevent>
                <input
                    v-model="filterForm.date_from"
                    type="date"
                    :class="[fbInput, '!py-2 sm:w-40']"
                    :aria-label="t('accounts.date_from')"
                    :title="t('accounts.date_from')"
                />
                <input
                    v-model="filterForm.date_to"
                    type="date"
                    :class="[fbInput, '!py-2 sm:w-40']"
                    :aria-label="t('accounts.date_to')"
                    :title="t('accounts.date_to')"
                />
                <input
                    v-model="filterForm.voucher"
                    type="search"
                    :class="[fbInput, '!py-2 sm:min-w-[8rem] sm:flex-1']"
                    :placeholder="t('accounts.search_voucher')"
                />
                <input
                    v-model="filterForm.description"
                    type="search"
                    :class="[fbInput, '!py-2 sm:min-w-[8rem] sm:flex-1']"
                    :placeholder="t('accounts.search_description')"
                />
                <input
                    v-model="filterForm.amount"
                    type="number"
                    min="0"
                    step="0.01"
                    :class="[fbInput, '!py-2 sm:w-32']"
                    :placeholder="t('accounts.search_amount')"
                />
                <Link
                    v-if="filters.date_from || filters.date_to || filters.voucher || filters.description || filters.amount"
                    :href="route('accounts.show', account.id)"
                    :class="[fbGhostButton, '!w-auto']"
                >
                    {{ t('common.reset') }}
                </Link>
            </form>
        </div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table erp-table account-ledger-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('common.date') }}</th>
                            <th>{{ t('journals.voucher') }}</th>
                            <th>{{ t('common.description') }}</th>
                            <th>{{ t('accounts.source_account') }}</th>
                            <th class="text-end">
                                {{ t('journals.debit') }}
                                <div class="normal-case font-normal text-xs opacity-80">{{ t('accounts.debit_meaning') }}</div>
                            </th>
                            <th class="text-end">
                                {{ t('journals.credit') }}
                                <div class="normal-case font-normal text-xs opacity-80">{{ t('accounts.credit_meaning') }}</div>
                            </th>
                            <th class="text-end">{{ t('accounts.balance') }}</th>
                            <th class="pe-4 text-end">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="lines.data.length === 0">
                            <td colspan="8" class="text-center text-secondary py-4">{{ t('accounts.ledger_none') }}</td>
                        </tr>
                        <tr v-for="line in lines.data" :key="line.id">
                            <td class="ps-4">{{ line.entry_date }}</td>
                            <td>
                                <Link
                                    :href="route('journals.show', line.journal_entry_id)"
                                    class="accounts-chart-link"
                                >
                                    {{ line.voucher_number }}
                                </Link>
                            </td>
                            <td>
                                <div>{{ line.description }}</div>
                                <div v-if="line.memo" class="small text-secondary">{{ line.memo }}</div>
                                <button
                                    v-if="line.attachment_url"
                                    type="button"
                                    :class="fbGhostButton"
                                    class="mt-1 !px-2 !py-0.5 text-xs"
                                    @click="previewUrl = line.attachment_url"
                                >
                                    {{ t('accounts.view_image') }}
                                </button>
                            </td>
                            <td>
                                <span v-if="line.counterpart">{{ line.counterpart.label }}</span>
                                <span v-else class="text-secondary">—</span>
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="line.debit" tone="debit" />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="line.credit" tone="credit" />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="line.balance" tone="balance" />
                            </td>
                            <td class="pe-2 text-end">
                                <div class="inline-flex flex-wrap justify-end gap-1">
                                    <Link
                                        :href="route('journals.print', line.journal_entry_id)"
                                        :class="fbGhostButton"
                                        class="!px-2 !py-1 text-xs"
                                    >
                                        {{ t('common.print') }}
                                    </Link>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        :class="fbGhostButton"
                                        class="!px-2 !py-1 text-xs"
                                        @click="openEdit(line)"
                                    >
                                        {{ t('common.edit') }}
                                    </button>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        :class="fbGhostButton"
                                        class="!px-2 !py-1 text-xs"
                                        @click="reverseLine(line)"
                                    >
                                        {{ t('accounts.reverse') }}
                                    </button>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        :class="fbDangerButton"
                                        class="!w-auto !px-2 !py-1 text-xs"
                                        @click="voidLine(line)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="lines.prev_page_url || lines.next_page_url"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 p-3 dark:border-gray-700"
            >
                <Link
                    v-if="lines.prev_page_url"
                    :href="lines.prev_page_url"
                    :class="fbGhostButton"
                    preserve-scroll
                >
                    {{ t('common.prev') }}
                </Link>
                <span v-else></span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ lines.from }}–{{ lines.to }} / {{ lines.total }}
                </span>
                <Link
                    v-if="lines.next_page_url"
                    :href="lines.next_page_url"
                    :class="fbGhostButton"
                    preserve-scroll
                >
                    {{ t('common.next') }}
                </Link>
                <span v-else></span>
            </div>
        </div>
        </template>

        <AccountMovementModal
            :show="movementOpen"
            :type="movementType"
            :account-id="account.id"
            :counterpart-accounts="counterpartAccounts"
            @close="movementOpen = false"
        />

        <div v-if="previewUrl" class="erp-modal-backdrop" @click.self="previewUrl = null">
            <div
                class="erp-modal-dialog erp-card p-0 overflow-hidden"
                style="width: min(900px, 100%)"
                role="dialog"
                aria-modal="true"
                :aria-label="t('accounts.view_image')"
            >
                <div class="d-flex justify-content-between align-items-start gap-3 p-3 border-bottom">
                    <h3 class="h5 erp-display mb-0">{{ t('accounts.view_image') }}</h3>
                    <div class="d-flex flex-wrap gap-2">
                        <a
                            :href="previewUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            :class="fbGhostButton"
                        >
                            {{ t('accounts.open_original') }}
                        </a>
                        <button type="button" :class="fbGhostButton" @click="previewUrl = null">
                            {{ t('common.cancel') }}
                        </button>
                    </div>
                </div>
                <div class="p-3">
                    <img :src="previewUrl" :alt="t('accounts.view_image')" class="mx-auto max-h-[75vh] w-auto max-w-full rounded-lg" />
                </div>
            </div>
        </div>

        <div v-if="editingLine" class="erp-modal-backdrop" @click.self="editingLine = null">
            <div
                class="erp-modal-dialog erp-card p-0 overflow-hidden"
                style="width: min(560px, 100%)"
                role="dialog"
                aria-modal="true"
                :aria-label="t('accounts.edit_movement')"
            >
                <div class="d-flex justify-content-between align-items-start gap-3 p-3 border-bottom">
                    <div>
                        <h3 class="h5 erp-display mb-1">{{ t('accounts.edit_movement') }}</h3>
                        <p class="small text-secondary mb-0">{{ t('accounts.edit_movement_help') }}</p>
                    </div>
                    <button type="button" :class="fbGhostButton" @click="editingLine = null">
                        {{ t('common.cancel') }}
                    </button>
                </div>
                <form class="p-3" @submit.prevent="saveEdit">
                    <div class="mb-3">
                        <label :class="fbLabel" for="edit-description">{{ t('common.description') }}</label>
                        <textarea id="edit-description" v-model="editForm.description" rows="3" maxlength="255" :class="fbInput" required />
                        <InputError :message="editForm.errors.description" />
                    </div>
                    <div class="mb-4">
                        <label :class="fbLabel" for="edit-file">{{ t('accounts.attach_image') }}</label>
                        <input id="edit-file" type="file" accept="image/*" :class="fbInput" @change="onEditFile" />
                        <InputError :message="editForm.errors.attachment" />
                        <div v-if="editingLine.attachment_url && !editForm.remove_attachment" class="mt-2 flex items-center gap-3">
                            <button type="button" :class="fbLink" @click="previewUrl = editingLine.attachment_url">
                                {{ t('accounts.view_image') }}
                            </button>
                            <button type="button" class="text-sm text-red-600 dark:text-red-400" @click="editForm.remove_attachment = true">
                                {{ t('accounts.remove_image') }}
                            </button>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-end gap-2">
                        <Link
                            :href="route('journals.print', editingLine.journal_entry_id)"
                            :class="fbGhostButton"
                        >
                            {{ t('common.print') }}
                        </Link>
                        <button type="button" :class="fbGhostButton" @click="editingLine = null">{{ t('common.cancel') }}</button>
                        <button type="submit" :class="fbButton" class="!w-auto" :disabled="editForm.processing">
                            {{ editForm.processing ? t('common.saving') : t('common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
