<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import LandDriverPaymentModal from '@/Components/LandTrips/LandDriverPaymentModal.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { useActionPin } from '@/composables/useActionPin';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    wallet: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const { t } = useI18n();
const { requireActionPin } = useActionPin();
const page = usePage();
const deletingId = ref(null);
const deletingPaymentId = ref(null);
const showDriverModal = ref(false);
const deleteError = computed(() => page.props.errors?.entry || page.props.errors?.cash_account_id);
const cashAccount = computed(() => props.wallet.cash_account || null);
const driverPayments = computed(() => props.wallet.driver_payments || []);
const driverNames = computed(() => props.wallet.driver_names || []);

const form = useForm({
    type: 'deposit',
    amount: '',
    currency: props.wallet.balances?.[0]?.currency || 'USD',
    notes: '',
    attachment: null,
});
const fileKey = ref(0);

const submit = async (type) => {
    const ok = await requireActionPin(t('action_pin.message_wallet'));
    if (!ok) {
        return;
    }

    form.type = type;
    form.post(route('land-trips.companies.wallet.store', props.company.id), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        only: ['wallet', 'errors', 'flash'],
        onSuccess: () => {
            form.reset('amount', 'notes', 'attachment');
            form.currency = form.currency || 'USD';
            fileKey.value += 1;
        },
    });
};

const typeLabel = (type) => (type === 'withdraw' ? t('land_trips.wallet_withdraw') : t('land_trips.wallet_deposit'));

const driverTypeLabel = (type) => {
    if (type === 'commission') {
        return t('land_trips.driver_type_commission');
    }
    if (type === 'other') {
        return t('land_trips.driver_type_other');
    }

    return t('land_trips.driver_type_freight');
};

const destroyEntry = (entry) => {
    if (!window.confirm(t('land_trips.wallet_delete_confirm', { voucher: entry.voucher_number }))) {
        return;
    }

    deletingId.value = entry.id;
    router.delete(route('land-trips.companies.wallet.destroy', [props.company.id, entry.id]), {
        preserveScroll: true,
        preserveState: true,
        only: ['wallet', 'errors', 'flash'],
        onFinish: () => {
            deletingId.value = null;
        },
    });
};

const destroyPayment = (payment) => {
    if (!window.confirm(t('land_trips.driver_delete_confirm', { name: payment.driver_name }))) {
        return;
    }

    deletingPaymentId.value = payment.id;
    router.delete(route('land-trips.companies.driver-payments.destroy', [props.company.id, payment.id]), {
        preserveScroll: true,
        preserveState: true,
        only: ['wallet', 'errors', 'flash'],
        onFinish: () => {
            deletingPaymentId.value = null;
        },
    });
};
</script>

<template>
    <div class="land-wallet">
        <p class="land-wallet-note mb-3">{{ t('land_trips.wallet_freight_help') }}</p>
        <p v-if="!cashAccount" class="land-wallet-note mb-3 text-warning">{{ t('land_trips.wallet_cash_missing') }}</p>

        <div class="land-wallet-stats mb-3">
            <div v-if="wallet.summary" class="erp-stat">
                <div class="erp-stat-label">{{ t('land_trips.cars_total') }} · {{ wallet.summary.currency }}</div>
                <div class="erp-stat-value">
                    <MoneyAmount :value="wallet.summary.cars_total" :currency="wallet.summary.currency" show-zero />
                </div>
                <div class="erp-stat-hint">{{ wallet.summary.cars_count }} {{ t('land_trips.cars') }}</div>
            </div>
            <div v-if="wallet.summary" class="erp-stat">
                <div class="erp-stat-label">{{ t('land_trips.wallet_payments') }} · {{ wallet.summary.currency }}</div>
                <div class="erp-stat-value">
                    <MoneyAmount :value="wallet.summary.paid" :currency="wallet.summary.currency" show-zero />
                </div>
            </div>
            <div v-if="wallet.summary" class="erp-stat">
                <div class="erp-stat-label">{{ t('land_trips.wallet_remaining') }} · {{ wallet.summary.currency }}</div>
                <div class="erp-stat-value">
                    <MoneyAmount :value="wallet.summary.remaining" :currency="wallet.summary.currency" tone="balance" show-zero />
                </div>
            </div>
            <div v-for="row in wallet.balances" :key="row.currency" class="erp-stat">
                <div class="erp-stat-label">{{ t('land_trips.wallet_balance') }} · {{ row.currency }}</div>
                <div class="erp-stat-value">
                    <MoneyAmount :value="row.balance" :currency="row.currency" show-zero />
                </div>
            </div>
        </div>

        <form v-if="canManage" class="erp-form-panel mb-3" @submit.prevent>
            <div class="land-wallet-form">
                <div>
                    <label class="form-erp-label" for="wallet-amount">{{ t('common.amount') }}</label>
                    <input
                        id="wallet-amount"
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="form-control form-erp-control"
                        required
                    />
                    <InputError :message="form.errors.amount || form.errors.cash_account_id || form.errors.currency" />
                </div>
                <div>
                    <label class="form-erp-label" for="wallet-currency">{{ t('common.currency') }}</label>
                    <select id="wallet-currency" v-model="form.currency" class="form-select form-erp-control">
                        <option v-for="code in wallet.currencies" :key="code" :value="code">{{ code }}</option>
                    </select>
                </div>
                <div class="land-wallet-form-notes">
                    <label class="form-erp-label" for="wallet-notes">{{ t('common.notes') }}</label>
                    <input id="wallet-notes" v-model="form.notes" type="text" class="form-control form-erp-control" maxlength="255" />
                </div>
                <div class="land-wallet-form-attachment">
                    <label class="form-erp-label" for="wallet-attachment">{{ t('land_trips.attach_file') }}</label>
                    <input
                        :key="fileKey"
                        id="wallet-attachment"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="form-control form-erp-control erp-file-input"
                        @change="form.attachment = $event.target.files?.[0] ?? null"
                    />
                    <InputError :message="form.errors.attachment" />
                </div>
                <div class="land-wallet-actions">
                    <button type="button" class="btn btn-erp land-wallet-btn" :disabled="form.processing" @click="submit('deposit')">
                        {{ t('land_trips.wallet_deposit') }}
                    </button>
                    <button type="button" class="btn btn-erp-ghost land-wallet-btn" :disabled="form.processing" @click="submit('withdraw')">
                        {{ t('land_trips.wallet_withdraw') }}
                    </button>
                    <button type="button" class="btn btn-erp-ghost land-wallet-btn" :disabled="form.processing" @click="showDriverModal = true">
                        {{ t('land_trips.driver_account') }}
                    </button>
                </div>
            </div>
            <p v-if="cashAccount" class="small text-secondary mt-2 mb-0">
                {{ t('land_trips.cash_account') }}: {{ cashAccount.name }}
            </p>
        </form>

        <div class="erp-card p-0 overflow-hidden mb-3">
            <InputError class="px-3 pt-3" :message="deleteError" />
            <EmptyState v-if="!wallet.entries?.length" icon="W">{{ t('land_trips.wallet_empty') }}</EmptyState>
            <div v-else class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">{{ t('land_trips.wallet_voucher') }}</th>
                            <th>{{ t('common.date') }}</th>
                            <th>{{ t('common.type') }}</th>
                            <th>{{ t('common.amount') }}</th>
                            <th>{{ t('common.notes') }}</th>
                            <th>{{ t('land_trips.attachment') }}</th>
                            <th>{{ t('land_trips.journal_voucher') }}</th>
                            <th>{{ t('land_trips.changed_by') }}</th>
                            <th class="text-end pe-3">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in wallet.entries" :key="entry.id">
                            <td class="ps-3 font-monospace">{{ entry.voucher_number }}</td>
                            <td class="small text-secondary text-nowrap">{{ entry.created_at }}</td>
                            <td>{{ typeLabel(entry.type) }}</td>
                            <td>
                                <MoneyAmount
                                    :value="entry.amount"
                                    :currency="entry.currency"
                                    :tone="entry.type === 'withdraw' ? 'debit' : 'credit'"
                                    show-zero
                                />
                            </td>
                            <td>{{ entry.notes || '—' }}</td>
                            <td>
                                <a
                                    v-if="entry.attachment_url"
                                    class="btn btn-sm btn-erp-ghost"
                                    :href="entry.attachment_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ t('land_trips.view_attachment') }}
                                </a>
                                <span v-else>—</span>
                            </td>
                            <td class="font-monospace small">{{ entry.journal_voucher || '—' }}</td>
                            <td>{{ entry.created_by_name || '—' }}</td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-2">
                                    <a
                                        class="btn btn-sm btn-erp-ghost"
                                        :href="route('land-trips.companies.wallet.print', [company.id, entry.id])"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        {{ t('common.print') }}
                                    </a>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        :disabled="deletingId === entry.id"
                                        @click="destroyEntry(entry)"
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

        <div class="erp-card p-0 overflow-hidden">
            <div class="d-flex justify-content-between align-items-center px-3 py-3 border-bottom">
                <h3 class="h6 mb-0">{{ t('land_trips.driver_payments') }}</h3>
                <button
                    v-if="canManage"
                    type="button"
                    class="btn btn-sm btn-erp"
                    @click="showDriverModal = true"
                >
                    {{ t('land_trips.driver_account') }}
                </button>
            </div>
            <EmptyState v-if="!driverPayments.length" icon="D">{{ t('land_trips.driver_payments_empty') }}</EmptyState>
            <div v-else class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">{{ t('land_trips.driver_name') }}</th>
                            <th>{{ t('land_trips.cmr') }}</th>
                            <th>{{ t('land_trips.cars') }}</th>
                            <th>{{ t('common.type') }}</th>
                            <th>{{ t('common.date') }}</th>
                            <th>{{ t('common.amount') }}</th>
                            <th>{{ t('land_trips.cash_account') }}</th>
                            <th>{{ t('land_trips.journal_voucher') }}</th>
                            <th>{{ t('land_trips.attachment') }}</th>
                            <th>{{ t('land_trips.changed_by') }}</th>
                            <th v-if="canManage" class="text-end pe-3">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in driverPayments" :key="payment.id">
                            <td class="ps-3">{{ payment.driver_name }}</td>
                            <td>{{ payment.cmr_number || '—' }}</td>
                            <td>{{ payment.cars_count }}</td>
                            <td>{{ driverTypeLabel(payment.type) }}</td>
                            <td class="small text-nowrap">{{ payment.payment_date }}</td>
                            <td>
                                <MoneyAmount :value="payment.amount" :currency="payment.currency" tone="debit" show-zero />
                            </td>
                            <td>{{ payment.cash_account_name || '—' }}</td>
                            <td class="font-monospace small">{{ payment.journal_voucher || '—' }}</td>
                            <td>
                                <a
                                    v-if="payment.attachment_url"
                                    class="btn btn-sm btn-erp-ghost"
                                    :href="payment.attachment_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ t('land_trips.view_attachment') }}
                                </a>
                                <span v-else>—</span>
                            </td>
                            <td>{{ payment.created_by_name || '—' }}</td>
                            <td v-if="canManage" class="text-end pe-3">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    :disabled="deletingPaymentId === payment.id"
                                    @click="destroyPayment(payment)"
                                >
                                    {{ t('common.delete') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <LandDriverPaymentModal
            :show="showDriverModal"
            :company-id="company.id"
            :driver-names="driverNames"
            :cash-account="cashAccount"
            @close="showDriverModal = false"
        />
    </div>
</template>
