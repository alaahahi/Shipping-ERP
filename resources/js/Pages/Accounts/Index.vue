<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import MoneyAmount from '@/Components/MoneyAmount.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { fbButton, fbGhostButton, fbInput } from '@/flowbite';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    accounts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const filterForm = useForm({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    currency: props.filters.currency ?? '',
});

const loadedAccounts = ref([]);
const currentPage = ref(1);
const lastPage = ref(1);
const loadingMore = ref(false);
const sentinel = ref(null);
let observer = null;

const hasMore = computed(() => currentPage.value < lastPage.value);

const compactInput = `${fbInput} !py-2`;
const compactGhost = `${fbGhostButton} !w-auto whitespace-nowrap`;
const compactPrimary = `${fbButton} !w-auto !px-3 !py-2 whitespace-nowrap`;
const compactDanger =
    'text-red-700 bg-white border border-red-200 hover:bg-red-50 focus:ring-4 focus:outline-none focus:ring-red-200 font-medium rounded-lg text-sm px-3 py-2 inline-flex items-center dark:bg-gray-800 dark:text-red-400 dark:border-red-800 dark:hover:bg-gray-700';

const replaceFromPaginator = (paginator) => {
    loadedAccounts.value = [...(paginator.data ?? [])];
    currentPage.value = paginator.current_page ?? 1;
    lastPage.value = paginator.last_page ?? 1;
};

const appendFromPaginator = (paginator) => {
    const rows = paginator.data ?? [];
    const seen = new Set(loadedAccounts.value.map((account) => account.id));
    loadedAccounts.value = [
        ...loadedAccounts.value,
        ...rows.filter((account) => !seen.has(account.id)),
    ];
    currentPage.value = paginator.current_page ?? currentPage.value;
    lastPage.value = paginator.last_page ?? lastPage.value;
};

watch(
    () => props.accounts,
    (paginator) => {
        replaceFromPaginator(paginator);
    },
    { immediate: true },
);

const stripPageFromUrl = () => {
    const url = new URL(window.location.href);
    if (!url.searchParams.has('page')) {
        return;
    }

    url.searchParams.delete('page');
    const query = url.searchParams.toString();
    window.history.replaceState(
        window.history.state,
        '',
        `${url.pathname}${query ? `?${query}` : ''}${url.hash}`,
    );
};

const filterQuery = () => ({
    search: filterForm.search || undefined,
    type: filterForm.type || undefined,
    currency: filterForm.currency || undefined,
});

const applyFilters = () => {
    router.get(route('accounts.index'), filterQuery(), {
        preserveState: true,
        replace: true,
        onSuccess: stripPageFromUrl,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.type = '';
    filterForm.currency = '';
    router.get(route('accounts.index'), {}, {
        replace: true,
        onSuccess: stripPageFromUrl,
    });
};

const loadMore = async () => {
    if (loadingMore.value || !hasMore.value) {
        return;
    }

    loadingMore.value = true;
    try {
        const { data } = await axios.get(route('accounts.feed'), {
            params: {
                page: currentPage.value + 1,
                search: filterForm.search || undefined,
                type: filterForm.type || undefined,
                currency: filterForm.currency || undefined,
            },
        });
        appendFromPaginator(data);
    } finally {
        loadingMore.value = false;
    }
};

const ensureObserver = () => {
    if (observer || typeof IntersectionObserver === 'undefined') {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                loadMore();
            }
        },
        { root: null, rootMargin: '240px 0px', threshold: 0 },
    );
};

const bindSentinel = (el, previous) => {
    ensureObserver();
    if (!observer) {
        return;
    }
    if (previous) {
        observer.unobserve(previous);
    }
    if (el) {
        observer.observe(el);
    }
};

watch(sentinel, bindSentinel, { flush: 'post' });

onMounted(() => {
    stripPageFromUrl();
    bindSentinel(sentinel.value, null);
});

onBeforeUnmount(() => {
    observer?.disconnect();
});

const destroy = (account) => {
    if (!window.confirm(t('accounts.delete_confirm', { code: account.code }))) return;
    router.delete(route('accounts.destroy', account.id));
};

const typeTone = (type) => {
    if (type === 'asset') return 'info';
    if (type === 'liability') return 'warning';
    if (type === 'equity') return 'neutral';
    if (type === 'revenue') return 'success';
    if (type === 'expense') return 'danger';
    return 'neutral';
};

const typeBadgeClass = (type) => {
    const map = {
        info: 'bg-sky-50 text-sky-800 border-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800',
        warning: 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
        success: 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
        danger: 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
        neutral: 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600',
    };

    return map[typeTone(type)] ?? map.neutral;
};
</script>

<template>
    <Head :title="t('accounts.title')" />
    <AppLayout>
        <template #header>{{ t('accounts.title') }}</template>

        <FlashMessage :message="success" />

        <PageHeader :kicker="t('nav.finance')" :title="t('accounts.title')">
            <template #actions>
                <Link v-if="canManage" :href="route('accounts.create')" :class="compactPrimary">
                    {{ t('accounts.add') }}
                </Link>
            </template>
        </PageHeader>

        <div class="accounts-chart-card overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <form
                class="flex flex-col gap-2 border-b border-gray-200 p-3 dark:border-gray-700 sm:flex-row sm:flex-wrap sm:items-center"
                @submit.prevent="applyFilters"
            >
                <input
                    v-model="filterForm.search"
                    type="search"
                    :class="[compactInput, 'sm:min-w-[12rem] sm:flex-1']"
                    :placeholder="t('accounts.search_placeholder')"
                    :aria-label="t('accounts.search_placeholder')"
                />
                <select
                    v-model="filterForm.type"
                    :class="[compactInput, 'sm:w-40']"
                    :aria-label="t('accounts.type')"
                >
                    <option value="">{{ t('accounts.all_types') }}</option>
                    <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
                <select
                    v-model="filterForm.currency"
                    :class="[compactInput, 'sm:w-36']"
                    :aria-label="t('common.currency')"
                >
                    <option value="">{{ t('accounts.all_currencies') }}</option>
                    <option v-for="currency in currencies" :key="currency.value" :value="currency.value">
                        {{ currency.value }}
                    </option>
                </select>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" :class="compactPrimary">{{ t('common.filter') }}</button>
                    <button type="button" :class="compactGhost" @click="resetFilters">{{ t('common.reset') }}</button>
                </div>
            </form>

            <div class="relative overflow-x-auto">
                <table class="accounts-chart-table w-full text-start text-sm text-gray-700 dark:text-gray-200">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-2.5 ps-4 font-semibold">{{ t('accounts.code') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ t('common.name') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ t('accounts.type') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ t('common.currency') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 text-end font-semibold">{{ t('accounts.balance') }}</th>
                            <th class="whitespace-nowrap px-3 py-2.5 pe-4 text-end font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loadedAccounts.length === 0">
                            <td colspan="6">
                                <EmptyState icon="A">{{ t('accounts.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr
                            v-for="account in loadedAccounts"
                            :key="account.id"
                            class="border-t border-gray-100 dark:border-gray-700"
                            :class="account.is_active ? 'hover:bg-teal-50/40 dark:hover:bg-gray-700/50' : 'opacity-70 hover:bg-gray-50 dark:hover:bg-gray-700/40'"
                        >
                            <td class="whitespace-nowrap px-3 py-2.5 ps-4 font-semibold" :class="account.parent ? 'ps-8' : ''">
                                <Link :href="route('accounts.show', account.id)" class="accounts-chart-link">
                                    {{ account.code }}
                                </Link>
                            </td>
                            <td class="px-3 py-2.5" :class="account.parent ? 'ps-5' : ''">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <Link :href="route('accounts.show', account.id)" class="accounts-chart-link">
                                        {{ account.name }}
                                    </Link>
                                    <span
                                        v-if="account.is_system"
                                        class="inline-flex items-center rounded-md border border-gray-200 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600 dark:border-gray-600 dark:text-gray-300"
                                    >
                                        {{ t('accounts.system') }}
                                    </span>
                                    <span
                                        v-if="account.show_on_dashboard"
                                        class="inline-flex items-center rounded-md border border-teal-200 bg-teal-50 px-1.5 py-0.5 text-[11px] font-semibold text-teal-800 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-300"
                                    >
                                        {{ t('accounts.pinned') }}
                                    </span>
                                    <span
                                        v-if="!account.is_active"
                                        class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                                    >
                                        {{ t('common.inactive') }}
                                    </span>
                                </div>
                                <div v-if="account.parent" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ account.parent.code }} — {{ account.parent.name }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5">
                                <span
                                    class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[11px] font-semibold"
                                    :class="typeBadgeClass(account.type)"
                                >
                                    {{ account.type_label }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5">
                                <span
                                    class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[11px] font-semibold"
                                    :class="account.currency === 'AED'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                                >
                                    {{ account.currency }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-end font-mono">
                                <Link :href="route('accounts.show', account.id)" class="accounts-chart-link">
                                    <MoneyAmount :value="account.balance" tone="balance" />
                                </Link>
                            </td>
                            <td class="px-3 py-2.5 pe-4 text-end">
                                <div class="inline-flex flex-wrap justify-end gap-1.5">
                                    <Link
                                        :href="route('accounts.show', account.id)"
                                        :class="compactPrimary"
                                        class="!px-2.5 !py-1.5 text-xs"
                                    >
                                        {{ t('accounts.ledger') }}
                                    </Link>
                                    <Link
                                        v-if="canManage"
                                        :href="route('accounts.edit', account.id)"
                                        :class="compactGhost"
                                        class="!px-2.5 !py-1.5 text-xs"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        v-if="canManage && !account.is_system"
                                        type="button"
                                        :class="compactDanger"
                                        class="!px-2.5 !py-1.5 text-xs"
                                        @click="destroy(account)"
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
                v-if="loadedAccounts.length && (hasMore || loadingMore)"
                ref="sentinel"
                class="accounts-chart-sentinel border-t border-gray-200 px-3 py-2 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
            >
                <span v-if="loadingMore">{{ t('common.loading_more') }}</span>
            </div>
        </div>
    </AppLayout>
</template>
