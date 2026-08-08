<script setup>
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PrintLayout from '@/Layouts/PrintLayout.vue';
import { formatMoney } from '@/utils/formatMoney';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    ship: { type: Object, required: true },
    currency: { type: String, default: 'USD' },
    currencies: { type: Array, default: () => [] },
    printedAt: { type: String, default: '' },
    ownerships: { type: Array, default: () => [] },
    expenses: { type: Array, default: () => [] },
    contributions: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
});

const page = usePage();
const { t } = useI18n();
const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));

const ownerToneClass = (ownerId) => {
    const id = Number(ownerId);
    const index = props.ownerships.findIndex((row) => Number(row.owner_id) === id);
    return `owner-tone-${(index < 0 ? 0 : index) % 6}`;
};

const expensePayerId = (expense) =>
    expense?.paid_by_owner_id
    || props.summary?.spender_owner_id
    || props.ownerships.find((row) => row.is_managing)?.owner_id
    || props.ownerships[0]?.owner_id
    || null;

const expensePayerName = (expense) =>
    expense?.paid_by_owner_name
    || props.summary?.spender_name
    || '—';

const expenseReason = (expense) => expense?.vendor || expense?.expense_type_label || '—';

const expensesByMonth = computed(() => {
    const groups = [];
    const index = {};
    let serial = 0;

    props.expenses.forEach((expense) => {
        const key = (expense.expense_date || '').slice(0, 7) || '—';
        if (!index[key]) {
            index[key] = { month: key, rows: [], total: 0 };
            groups.push(index[key]);
        }
        serial += 1;
        index[key].rows.push({ ...expense, serial });
        index[key].total += Number(expense.amount) || 0;
    });

    return groups;
});

const expenseGrandTotal = computed(() =>
    props.expenses.reduce((sum, row) => sum + (Number(row.amount) || 0), 0)
);

const paymentRows = computed(() =>
    props.contributions.map((row, i) => ({ ...row, serial: i + 1 }))
);

const paymentsTotal = computed(() =>
    props.contributions.reduce((sum, row) => sum + (Number(row.amount) || 0), 0)
);

const differenceTone = computed(() => {
    const value = Number(props.summary?.difference_numeric || 0);
    if (Math.abs(value) < 0.005) return 'is-settled';
    return value > 0 ? 'is-owing' : 'is-owed';
});

const settlementHint = computed(() => {
    const summary = props.summary;
    if (!summary) return '';
    if (summary.hint_key === 'settled') return t('ship_expenses.hint_settled');
    if (summary.hint_key === 'other_more') {
        return t('ship_expenses.hint_other_more', {
            spender: summary.spender_name || '—',
            other: summary.other_name || '—',
        });
    }
    return t('ship_expenses.hint_spender_more', {
        spender: summary.spender_name || '—',
        other: summary.other_name || '—',
    });
});

const changeCurrency = (event) => {
    router.get(route('ships.expenses.print', props.ship.id), {
        currency: event.target.value,
    }, { preserveScroll: true });
};

const printPage = () => window.print();
</script>

<template>
    <Head :title="`${t('ship_expenses.print_title')} — ${ship.name}`" />

    <PrintLayout>
        <template #toolbar-start>
            <a
                class="btn btn-erp-ghost btn-sm"
                :href="route('ships.show', { ship: ship.id, tab: 'expenses' })"
            >
                {{ t('common.back') }}
            </a>
            <select
                class="form-select form-select-sm form-erp-control"
                style="width: auto"
                :value="currency"
                @change="changeCurrency"
            >
                <option v-for="item in currencies" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
        </template>
        <template #toolbar-actions>
            <button type="button" class="btn btn-erp btn-sm" @click="printPage">
                {{ t('common.print') }}
            </button>
        </template>

        <header class="erp-print-header">
            <div class="erp-print-kicker">{{ companyName }}</div>
            <h1 class="erp-print-title">{{ t('ship_expenses.print_title') }}</h1>
            <p class="erp-print-meta mb-0">
                <strong>{{ ship.name }}</strong>
                <span v-if="ship.flag"> · {{ ship.flag }}</span>
                <span v-if="ship.imo_number"> · IMO {{ ship.imo_number }}</span>
                <span> · {{ currency }}</span>
                <span> · {{ t('ship_expenses.printed_at') }}: {{ printedAt }}</span>
            </p>
        </header>

        <section v-if="summary" class="erp-print-summary">
            <div class="erp-print-summary-box">
                <div class="erp-print-summary-label">{{ t('ship_expenses.total_expenses') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="summary.total_expenses" :currency="currency" />
                </div>
            </div>
            <div
                v-for="partner in summary.partners"
                :key="partner.ownership_id"
                class="erp-print-summary-box"
                :class="ownerToneClass(partner.owner_id)"
            >
                <div class="erp-print-summary-label">
                    {{ partner.is_spender
                        ? t('ship_expenses.spender_paid', { name: partner.owner_name || '—' })
                        : t('ship_expenses.payer_paid', { name: partner.owner_name || '—' }) }}
                </div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="partner.paid_formatted" :currency="currency" />
                </div>
            </div>
            <div class="erp-print-summary-box" :class="differenceTone">
                <div class="erp-print-summary-label">{{ t('ship_expenses.difference') }}</div>
                <div class="erp-print-summary-value">
                    <MoneyAmount :value="summary.difference" :currency="currency" />
                </div>
                <div v-if="settlementHint" class="erp-print-summary-hint">{{ settlementHint }}</div>
            </div>
        </section>

        <div class="erp-print-columns">
            <section class="erp-print-col-expenses">
                <h2 class="erp-print-section-title">{{ t('ship_expenses.title') }}</h2>
                <table class="erp-print-table">
                    <thead>
                        <tr>
                            <th class="erp-print-col-serial">{{ t('ship_expenses.serial') }}</th>
                            <th>{{ t('common.date') }}</th>
                            <th>{{ t('ship_expenses.reason') }}</th>
                            <th>{{ t('ship_expenses.paid_by') }}</th>
                            <th class="text-end">{{ t('common.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody v-if="expensesByMonth.length === 0">
                        <tr>
                            <td colspan="5" class="text-secondary">{{ t('ship_expenses.none') }}</td>
                        </tr>
                    </tbody>
                    <template v-for="group in expensesByMonth" :key="group.month">
                        <tbody class="erp-print-month-group">
                            <tr class="erp-print-month-row">
                                <td colspan="4">{{ t('ship_expenses.month') }} {{ group.month }}</td>
                                <td class="text-end">{{ formatMoney(group.total) }}</td>
                            </tr>
                            <tr
                                v-for="expense in group.rows"
                                :key="expense.id"
                                class="owner-stripe"
                                :class="ownerToneClass(expensePayerId(expense))"
                            >
                                <td class="erp-print-col-serial">{{ expense.serial }}</td>
                                <td>{{ expense.expense_date }}</td>
                                <td>
                                    {{ expenseReason(expense) }}
                                    <span v-if="expense.reference" class="erp-print-ref"> · {{ expense.reference }}</span>
                                </td>
                                <td>{{ expensePayerName(expense) }}</td>
                                <td class="text-end"><MoneyAmount :value="expense.amount" /></td>
                            </tr>
                        </tbody>
                    </template>
                    <tfoot v-if="expenses.length">
                        <tr class="erp-print-total-row">
                            <td colspan="4">{{ t('ship_expenses.grand_total') }}</td>
                            <td class="text-end"><MoneyAmount :value="expenseGrandTotal" :currency="currency" /></td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <section class="erp-print-col-payments">
                <h2 class="erp-print-section-title">{{ t('ship_partners.title') }}</h2>
                <table class="erp-print-table">
                    <thead>
                        <tr>
                            <th class="erp-print-col-serial">{{ t('ship_expenses.serial') }}</th>
                            <th>{{ t('common.date') }}</th>
                            <th>{{ t('ship_partners.partner') }}</th>
                            <th>{{ t('ship_expenses.reason') }}</th>
                            <th class="text-end">{{ t('common.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="paymentRows.length === 0">
                            <td colspan="5" class="text-secondary">{{ t('ship_partners.none') }}</td>
                        </tr>
                        <tr
                            v-for="row in paymentRows"
                            :key="row.id"
                            class="owner-stripe"
                            :class="ownerToneClass(row.owner_id)"
                        >
                            <td class="erp-print-col-serial">{{ row.serial }}</td>
                            <td>{{ row.contribution_date }}</td>
                            <td>{{ row.owner_name || '—' }}</td>
                            <td>
                                {{ row.description || '—' }}
                                <span v-if="row.reference" class="erp-print-ref"> · {{ row.reference }}</span>
                            </td>
                            <td class="text-end"><MoneyAmount :value="row.amount" /></td>
                        </tr>
                    </tbody>
                    <tfoot v-if="paymentRows.length">
                        <tr class="erp-print-total-row">
                            <td colspan="4">{{ t('ship_expenses.payments_total') }}</td>
                            <td class="text-end"><MoneyAmount :value="paymentsTotal" :currency="currency" /></td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        </div>

        <footer class="erp-print-footer">
            {{ t('ship_expenses.print_footer') }}
        </footer>
    </PrintLayout>
</template>
