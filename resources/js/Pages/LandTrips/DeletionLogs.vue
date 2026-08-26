<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import { fbButton, fbGhostButton, fbInput, fbSuccessButton } from '@/flowbite';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    deletions: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({ search: '' }) },
    deletionLog: { type: Object, default: () => ({ unrestored_count: 0 }) },
});

const page = usePage();
const { t } = useI18n();
const flash = computed(() => page.props.flash ?? {});
const restoreError = computed(() => page.props.errors?.restore || page.props.errors?.deletion_id || page.props.errors?.item_ids || '');
const restoringId = ref(null);

const compactInput = `${fbInput} !py-2`;
const compactGhost = `${fbGhostButton} !w-auto whitespace-nowrap`;
const compactPrimary = `${fbButton} !w-auto !px-3 !py-2 whitespace-nowrap`;
const compactSuccess = `${fbSuccessButton} !w-auto !px-3 !py-2 whitespace-nowrap`;

const filterForm = useForm({
    search: props.filters.search ?? '',
});

const sourceLabel = (source) => {
    const key = `land_trips.deletion_source_${source}`;
    return t(key);
};

const restoreCars = (deletion, itemIds = []) => {
    if (!props.canManage || restoringId.value) {
        return;
    }

    const count = itemIds.length || deletion.pending_count || deletion.cars_count;
    if (!window.confirm(t('land_trips.restore_cars_confirm', { count }))) {
        return;
    }

    restoringId.value = itemIds[0] || deletion.id;
    router.post(route('land-trips.companies.deletion-logs.restore', props.company.id), {
        deletion_id: deletion.id,
        item_ids: itemIds,
    }, {
        preserveScroll: true,
        onFinish: () => {
            restoringId.value = null;
        },
    });
};

const applySearch = () => {
    router.get(route('land-trips.companies.deletion-logs', props.company.id), {
        search: filterForm.search || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetSearch = () => {
    filterForm.search = '';
    router.get(route('land-trips.companies.deletion-logs', props.company.id), {}, {
        replace: true,
    });
};

const statusClass = (row) => {
    if (row.restored) {
        return 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300';
    }

    return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
};
</script>

<template>
    <Head :title="`${t('land_trips.deletion_log')} — ${company.name}`" />
    <AppLayout>
        <template #header>{{ t('land_trips.deletion_log') }}</template>
        <FlashMessage :message="flash.success" />
        <FlashMessage :message="flash.error" />
        <div
            v-if="restoreError"
            class="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300"
            role="alert"
        >
            {{ restoreError }}
        </div>

        <Link :href="route('land-trips.companies.show', company.id)" :class="[fbGhostButton, '!w-auto mb-3']">
            {{ t('land_trips.back_company_cars') }}
        </Link>

        <PageHeader
            :kicker="company.name"
            :title="t('land_trips.deletion_log')"
            :subtitle="t('land_trips.deletion_log_help')"
        >
            <template #actions>
                <span
                    v-if="deletionLog.unrestored_count"
                    class="inline-flex min-h-11 items-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    {{ t('land_trips.pending_restore_count', { count: deletionLog.unrestored_count }) }}
                </span>
            </template>
        </PageHeader>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <form
                class="flex flex-col gap-2 border-b border-gray-200 p-3 dark:border-gray-700 sm:flex-row sm:flex-wrap sm:items-center"
                @submit.prevent="applySearch"
            >
                <input
                    v-model="filterForm.search"
                    type="search"
                    :class="[compactInput, 'sm:min-w-[14rem] sm:flex-1']"
                    :placeholder="t('land_trips.deletion_log_search')"
                    :aria-label="t('land_trips.deletion_log_search')"
                />
                <div class="flex flex-wrap gap-2">
                    <button type="submit" :class="compactPrimary">{{ t('common.filter') }}</button>
                    <button type="button" :class="compactGhost" @click="resetSearch">{{ t('common.reset') }}</button>
                </div>
            </form>

            <EmptyState v-if="!deletions.data?.length" icon="D">
                {{ t('land_trips.deletion_log_empty') }}
            </EmptyState>

            <div v-else class="relative overflow-x-auto">
                <table class="w-full text-start text-sm text-gray-700 dark:text-gray-200">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-2.5 ps-4 font-semibold">{{ t('common.date') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ t('land_trips.changed_by') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ t('land_trips.deletion_reason') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ t('land_trips.cars') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ t('common.status') }}</th>
                            <th class="min-w-[16rem] px-3 py-2.5 font-semibold">{{ t('land_trips.chassis') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 pe-4 text-end font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in deletions.data"
                            :key="row.id"
                            class="border-t border-gray-100 align-top dark:border-gray-700"
                        >
                            <td class="whitespace-nowrap px-3 py-2.5 ps-4 text-xs text-gray-500 dark:text-gray-400">
                                {{ row.created_at }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ row.user_name || '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ sourceLabel(row.source) }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5">
                                {{ row.cars_count }}
                                <span v-if="row.pending_count && row.pending_count !== row.cars_count" class="text-xs text-gray-500 dark:text-gray-400">
                                    · {{ t('land_trips.pending_restore_count', { count: row.pending_count }) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5">
                                <span
                                    class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[11px] font-semibold"
                                    :class="statusClass(row)"
                                >
                                    {{ row.restored
                                        ? (row.restored_at ? `${t('land_trips.restored')} · ${row.restored_at}` : t('land_trips.restored'))
                                        : t('land_trips.deleted') }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5">
                                <details v-if="row.items?.length" class="text-sm">
                                    <summary class="cursor-pointer text-teal-700 dark:text-teal-400">
                                        {{ row.chassis_preview || t('land_trips.cars') }}
                                    </summary>
                                    <ul class="mt-2 space-y-2 ps-1">
                                        <li
                                            v-for="item in row.items"
                                            :key="item.id"
                                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            <div class="min-w-0">
                                                <ChassisLetterOWarning :value="item.chassis_no" />
                                                <span v-if="item.model" class="ms-1 text-xs text-gray-500 dark:text-gray-400">{{ item.model }}</span>
                                                <span v-if="item.cmr_waybill" class="ms-1 text-xs text-gray-500 dark:text-gray-400">· {{ item.cmr_waybill }}</span>
                                                <div v-if="item.restored" class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                                    {{ t('land_trips.restored') }}
                                                    <span v-if="item.restored_by_name"> · {{ item.restored_by_name }}</span>
                                                    <span v-if="item.restored_at"> · {{ item.restored_at }}</span>
                                                </div>
                                                <div v-else-if="item.missing" class="mt-0.5 text-[11px] text-red-700 dark:text-red-400">
                                                    {{ t('land_trips.restore_unavailable') }}
                                                </div>
                                            </div>
                                            <button
                                                v-if="canManage && item.can_restore"
                                                type="button"
                                                :class="compactSuccess"
                                                class="min-h-11"
                                                :disabled="restoringId !== null"
                                                @click="restoreCars(row, [item.id])"
                                            >
                                                {{ t('common.restore') }}
                                            </button>
                                        </li>
                                    </ul>
                                </details>
                                <span v-else>—</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 pe-4 text-end">
                                <button
                                    v-if="canManage && row.can_restore"
                                    type="button"
                                    :class="compactSuccess"
                                    class="min-h-11"
                                    :disabled="restoringId !== null"
                                    @click="restoreCars(row)"
                                >
                                    {{ t('land_trips.restore_all') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="deletions.links?.length > 3" class="mt-3 flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, index) in deletions.links"
                :key="index"
                :href="link.url || undefined"
                :class="link.active ? compactPrimary : compactGhost"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
