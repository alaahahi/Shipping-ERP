<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePermissions } from '@/composables/usePermissions';

defineProps({
    stats: { type: Object, default: () => ({}) },
    monthOverview: { type: Object, default: null },
    companyDebts: { type: Object, default: null },
    pinnedAccounts: { type: Array, default: () => [] },
    recentVoyages: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useI18n();
const { can } = usePermissions();
const userName = computed(() => page.props.auth?.user?.name ?? '');
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout>
        <template #header>{{ t('dashboard.title') }}</template>

        <div class="erp-hero">
            <div class="erp-hero-kicker">{{ t('dashboard.welcome') }}</div>
            <h2 class="erp-hero-title">{{ t('dashboard.hello', { name: userName }) }}</h2>
            <p class="erp-hero-subtitle">{{ t('dashboard.intro') }}</p>
        </div>

        <div class="row g-3 g-lg-4 mb-4">
            <div class="col-6 col-lg-3" v-if="can('ships.view') && stats.ships !== null">
                <Link :href="route('ships.index')" class="text-decoration-none text-body">
                    <div class="erp-stat is-clickable">
                        <div class="erp-stat-label">{{ t('dashboard.active_ships') }}</div>
                        <p class="erp-stat-value">{{ stats.ships }}</p>
                        <div class="erp-stat-hint">{{ t('dashboard.ships_text') }}</div>
                    </div>
                </Link>
            </div>
            <div class="col-6 col-lg-3" v-if="can('voyages.view') && stats.voyages_active !== null">
                <Link :href="route('voyages.index', { status: 'active' })" class="text-decoration-none text-body">
                    <div class="erp-stat is-clickable">
                        <div class="erp-stat-label">{{ t('dashboard.active_voyages') }}</div>
                        <p class="erp-stat-value">{{ stats.voyages_active }}</p>
                        <div class="erp-stat-hint">{{ t('dashboard.draft_voyages') }}: {{ stats.voyages_draft }}</div>
                    </div>
                </Link>
            </div>
            <div class="col-6 col-lg-3" v-if="can('accounting.view') && stats.accounts !== null">
                <Link :href="route('accounts.index')" class="text-decoration-none text-body">
                    <div class="erp-stat is-clickable">
                        <div class="erp-stat-label">{{ t('dashboard.chart_accounts') }}</div>
                        <p class="erp-stat-value">{{ stats.accounts }}</p>
                        <div class="erp-stat-hint">{{ t('dashboard.accounting_text') }}</div>
                    </div>
                </Link>
            </div>
            <div class="col-6 col-lg-3" v-if="can('accounting.view') && stats.journals_draft !== null">
                <Link :href="route('journals.index', { status: 'draft' })" class="text-decoration-none text-body">
                    <div class="erp-stat is-clickable">
                        <div class="erp-stat-label">{{ t('dashboard.draft_journals') }}</div>
                        <p class="erp-stat-value">{{ stats.journals_draft }}</p>
                        <div class="erp-stat-hint">{{ t('nav.journals') }}</div>
                    </div>
                </Link>
            </div>
        </div>

        <div v-if="monthOverview" class="row g-3 mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h3 class="erp-panel-title mb-0">{{ t('dashboard.month_overview') }}</h3>
                    <Link v-if="can('reports.view')" :href="route('reports.index')" class="small fw-semibold text-decoration-none">
                        {{ t('nav.reports') }}
                    </Link>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('reports.voyages_in_period') }}</div>
                    <p class="erp-stat-value">{{ monthOverview.voyages_count }}</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('reports.cars_in_period') }}</div>
                    <p class="erp-stat-value">{{ monthOverview.cars_count }}</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('voyage_settlements.revenue_usd') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.15rem">{{ monthOverview.revenue_usd }}</p>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="erp-stat">
                    <div class="erp-stat-label">{{ t('voyage_settlements.profit_usd') }}</div>
                    <p class="erp-stat-value" style="font-size: 1.15rem">{{ monthOverview.profit_usd }}</p>
                </div>
            </div>
        </div>

        <div v-if="can('accounting.view') && pinnedAccounts.length" class="mb-4 dashboard-pinned-accounts">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div>
                    <h3 class="erp-panel-title mb-1">{{ t('dashboard.pinned_accounts') }}</h3>
                    <p class="small text-secondary mb-0">{{ t('dashboard.pinned_accounts_help') }}</p>
                </div>
            </div>
            <div class="row g-2 g-md-3">
                <div
                    v-for="account in pinnedAccounts"
                    :key="account.id"
                    class="col-6 col-md-4 col-lg-3 col-xl-2"
                >
                    <Link
                        :href="route('accounts.show', account.id)"
                        class="erp-debt-card is-mid text-decoration-none"
                    >
                        <div class="erp-debt-card-name">{{ account.code }} — {{ account.name }}</div>
                        <div class="erp-debt-card-amount">
                            <MoneyAmount :value="account.balance" :currency="account.currency" tone="balance" />
                        </div>
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="can('voyages.view') && companyDebts" class="mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div>
                    <h3 class="erp-panel-title mb-1">{{ t('dashboard.company_debts') }}</h3>
                    <p class="small text-secondary mb-0">{{ t('dashboard.company_debts_help') }}</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="erp-debt-legend small text-secondary">
                        <span class="erp-debt-swatch is-critical"></span>{{ t('dashboard.debt_high') }}
                        <span class="erp-debt-swatch is-high"></span>
                        <span class="erp-debt-swatch is-mid"></span>
                        <span class="erp-debt-swatch is-low"></span>{{ t('dashboard.debt_low') }}
                    </div>
                    <div class="fw-semibold">
                        {{ t('dashboard.company_debts_total') }}:
                        <MoneyAmount :value="companyDebts.total" :currency="companyDebts.currency" />
                    </div>
                </div>
            </div>
            <div v-if="!companyDebts.cards?.length">
                <EmptyState icon="$">{{ t('dashboard.company_debts_none') }}</EmptyState>
            </div>
            <div v-else class="row g-2 g-md-3">
                <div
                    v-for="card in companyDebts.cards"
                    :key="card.id"
                    class="col-6 col-md-4 col-lg-3 col-xl-2"
                >
                    <Link
                        :href="route('companies.show', card.id)"
                        class="erp-debt-card"
                        :class="`is-${card.tone}`"
                    >
                        <div class="erp-debt-card-name">{{ card.name }}</div>
                        <div class="erp-debt-card-amount">
                            $<MoneyAmount :value="card.balance" />
                        </div>
                    </Link>
                </div>
            </div>
        </div>

        <div class="row g-3 g-lg-4">
            <div class="col-lg-7">
                <div class="erp-card p-0 overflow-hidden h-100">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h3 class="erp-panel-title mb-0">{{ t('dashboard.recent_voyages') }}</h3>
                        <Link v-if="can('voyages.view')" :href="route('voyages.index')" class="small fw-semibold text-decoration-none">
                            {{ t('nav.voyages') }}
                        </Link>
                    </div>
                    <div v-if="!can('voyages.view') || recentVoyages.length === 0" class="p-3">
                        <EmptyState icon="V">{{ t('dashboard.no_recent') }}</EmptyState>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table erp-table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">{{ t('voyages.number') }}</th>
                                    <th>{{ t('ships.name') }}</th>
                                    <th>{{ t('voyages.route') }}</th>
                                    <th class="pe-4">{{ t('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="voyage in recentVoyages" :key="voyage.id">
                                    <td class="ps-4">
                                        <Link :href="route('voyages.show', voyage.id)" class="fw-semibold text-decoration-none">
                                            {{ voyage.voyage_number }}
                                        </Link>
                                        <div class="small text-secondary">{{ voyage.sailing_date }}</div>
                                    </td>
                                    <td>{{ voyage.ship_name || '—' }}</td>
                                    <td>{{ voyage.route }}</td>
                                    <td class="pe-4">
                                        <StatusBadge :tone="voyage.status_tone" :label="voyage.status_label" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="erp-card p-4 h-100">
                    <h3 class="erp-panel-title mb-3">{{ t('dashboard.quick_links') }}</h3>
                    <div class="d-grid gap-2">
                        <Link v-if="can('voyages.manage')" :href="route('voyages.create')" class="btn btn-erp">
                            {{ t('voyages.add') }}
                        </Link>
                        <Link v-if="can('reports.view')" :href="route('reports.index')" class="btn btn-erp-ghost">
                            {{ t('nav.reports') }}
                        </Link>
                        <Link v-if="can('ships.manage')" :href="route('ships.create')" class="btn btn-erp-ghost">
                            {{ t('ships.add') }}
                        </Link>
                        <Link v-if="can('accounting.manage')" :href="route('journals.create')" class="btn btn-erp-ghost">
                            {{ t('journals.new') }}
                        </Link>
                        <Link v-if="can('accounting.view')" :href="route('accounts.index')" class="btn btn-erp-ghost">
                            {{ t('nav.accounts') }}
                        </Link>
                    </div>
                    <hr class="my-4" />
                    <div class="text-secondary small mb-2">{{ t('dashboard.focus') }}</div>
                    <h4 class="h6 erp-display mb-2">{{ t('dashboard.focus_title') }}</h4>
                    <p class="text-secondary small mb-0">{{ t('dashboard.focus_text') }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
