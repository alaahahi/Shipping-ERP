<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    voucher: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const posting = ref(false);

const postVoucher = (id) => {
    if (!window.confirm(t('money_vouchers.post_confirm'))) return;
    router.post(route('money-vouchers.post', id), {}, {
        preserveScroll: true,
        onStart: () => {
            posting.value = true;
        },
        onFinish: () => {
            posting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="voucher.voucher_number" />
    <AppLayout>
        <template #header>{{ voucher.voucher_number }}</template>
        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link :href="route('money-vouchers.index')" class="text-decoration-none small fw-semibold">
                ← {{ t('money_vouchers.back') }}
            </Link>
        </div>

        <PageHeader :kicker="voucher.type_label" :title="voucher.voucher_number" :subtitle="t('money_vouchers.show_help')">
            <template #actions>
                <StatusBadge :tone="voucher.status_tone" :label="voucher.status_label" />
                <Link
                    v-if="canManage && voucher.is_draft"
                    :href="route('money-vouchers.edit', voucher.id)"
                    class="btn btn-erp-ghost"
                >
                    {{ t('common.edit') }}
                </Link>
                <button
                    v-if="canManage && voucher.is_draft"
                    type="button"
                    class="btn btn-erp"
                    :class="{ 'is-posting': posting }"
                    :disabled="posting"
                    @click="postVoucher(voucher.id)"
                >
                    {{ posting ? t('common.posting') : t('money_vouchers.post') }}
                </button>
            </template>
        </PageHeader>

        <div class="row g-3">
            <div class="col-md-8">
                <div class="erp-card p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-secondary small">{{ t('common.date') }}</div>
                            <div class="fw-semibold">{{ voucher.voucher_date }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-secondary small">{{ t('common.amount') }}</div>
                            <div class="fw-semibold font-monospace">{{ voucher.amount }} {{ voucher.currency }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-secondary small">{{ t('money_vouchers.payment_account') }}</div>
                            <div class="fw-semibold">{{ voucher.payment_account || '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-secondary small">{{ t('money_vouchers.company') }}</div>
                            <div class="fw-semibold">
                                <Link
                                    v-if="voucher.company_id"
                                    :href="route('companies.show', voucher.company_id)"
                                    class="text-decoration-none"
                                >
                                    {{ voucher.company_name }}
                                </Link>
                                <span v-else>{{ voucher.counterparty || '—' }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-secondary small">{{ t('common.reference') }}</div>
                            <div class="fw-semibold">{{ voucher.reference || '—' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-secondary small">{{ t('common.notes') }}</div>
                            <div class="fw-semibold">{{ voucher.description || '—' }}</div>
                        </div>
                        <div v-if="voucher.allocations?.length" class="col-12">
                            <div class="text-secondary small mb-2">{{ t('money_vouchers.allocations') }}</div>
                            <div class="table-responsive">
                                <table class="table table-sm erp-table mb-0">
                                    <tbody>
                                        <tr v-for="(row, index) in voucher.allocations" :key="index">
                                            <td>
                                                <Link
                                                    v-if="row.voyage_id"
                                                    :href="route('voyages.show', row.voyage_id)"
                                                    class="text-decoration-none"
                                                >
                                                    {{ row.voyage_number }}
                                                </Link>
                                            </td>
                                            <td class="text-end font-monospace">{{ row.amount }} {{ voucher.currency }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="erp-card p-4">
                    <h3 class="erp-panel-title mb-3">{{ t('money_vouchers.journal') }}</h3>
                    <p class="small text-secondary mb-3">{{ t('money_vouchers.posting_hint') }}</p>
                    <Link
                        v-if="voucher.journal_entry_id"
                        :href="route('journals.show', voucher.journal_entry_id)"
                        class="btn btn-erp w-100"
                    >
                        {{ voucher.journal_voucher }}
                    </Link>
                    <div v-else class="text-secondary small">{{ t('money_vouchers.not_posted') }}</div>
                    <hr class="my-3" />
                    <div class="small text-secondary">{{ t('money_vouchers.created_by') }}: {{ voucher.created_by_name || '—' }}</div>
                    <div class="small text-secondary">{{ t('money_vouchers.posted_by') }}: {{ voucher.posted_by_name || '—' }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
