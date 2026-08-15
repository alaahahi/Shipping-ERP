<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    entry: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const posting = ref(false);

const postEntry = (id) => {
    if (!window.confirm(t('journals.post_confirm'))) return;
    router.post(route('journals.post', id), {}, {
        preserveScroll: true,
        onStart: () => {
            posting.value = true;
        },
        onFinish: () => {
            posting.value = false;
        },
    });
};

const voidEntry = (id) => {
    const reason = window.prompt(t('journals.void_reason')) ?? '';
    router.post(route('journals.void', id), { void_reason: reason });
};
</script>

<template>
    <Head :title="entry.voucher_number" />
    <AppLayout>
        <template #header>{{ entry.voucher_number }}</template>

        <div v-if="success" class="alert alert-success border-0 shadow-sm mb-3">{{ success }}</div>

        <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <Link :href="route('journals.index')" class="text-decoration-none small fw-semibold">← {{ t('journals.back') }}</Link>
            <div class="d-flex gap-2">
                <Link :href="route('journals.print', entry.id)" class="btn btn-erp-ghost">
                    {{ t('common.print') }}
                </Link>
                <Link
                    v-if="canManage && entry.status === 'draft'"
                    :href="route('journals.edit', entry.id)"
                    class="btn btn-erp-ghost"
                >
                    {{ t('journals.edit_draft') }}
                </Link>
                <button
                    v-if="canManage && entry.status === 'draft'"
                    type="button"
                    class="btn btn-erp"
                    :class="{ 'is-posting': posting }"
                    :disabled="posting"
                    @click="postEntry(entry.id)"
                >
                    {{ posting ? t('common.posting') : t('journals.post') }}
                </button>
                <button
                    v-if="canManage && entry.status === 'posted'"
                    type="button"
                    class="btn btn-outline-danger"
                    @click="voidEntry(entry.id)"
                >
                    {{ t('journals.void') }}
                </button>
            </div>
        </div>

        <div class="erp-card p-4 mb-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-secondary small">{{ t('common.status') }}</div>
                    <div class="fw-semibold">{{ entry.status_label }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-secondary small">{{ t('common.date') }}</div>
                    <div class="fw-semibold">{{ entry.entry_date }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-secondary small">{{ t('common.currency') }}</div>
                    <div class="fw-semibold">{{ entry.currency }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-secondary small">{{ t('common.reference') }}</div>
                    <div class="fw-semibold">{{ entry.reference || '—' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-secondary small">{{ t('common.description') }}</div>
                    <div class="fw-semibold">{{ entry.description }}</div>
                </div>
            </div>
        </div>

        <div class="erp-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('journals.account') }}</th>
                            <th class="text-end">{{ t('journals.debit') }}</th>
                            <th class="text-end">{{ t('journals.credit') }}</th>
                            <th class="pe-4">{{ t('journals.memo') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in entry.lines" :key="line.id">
                            <td class="ps-4">
                                <div class="fw-semibold">{{ line.account?.code }} — {{ line.account?.name }}</div>
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="line.debit" tone="debit" />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="line.credit" tone="credit" />
                            </td>
                            <td class="pe-4 text-secondary">{{ line.memo || '—' }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td class="ps-4">{{ t('journals.totals') }}</td>
                            <td class="text-end">
                                <MoneyAmount :value="entry.total_debit" tone="debit" show-zero />
                            </td>
                            <td class="text-end">
                                <MoneyAmount :value="entry.total_credit" tone="credit" show-zero />
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
