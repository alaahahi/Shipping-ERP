<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    current: { type: String, required: true },
});

const { t } = useI18n();

const tabClass = (active) => [
    'inline-flex items-center px-4 py-2 text-sm font-medium border-b-2',
    active
        ? 'text-teal-700 border-teal-700 dark:text-teal-400 dark:border-teal-400'
        : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600',
].join(' ');
</script>

<template>
    <nav class="mb-4 flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700" aria-label="Reports">
        <Link :href="route('reports.index')" :class="tabClass(current === 'overview')">
            {{ t('reports.overview_tab') }}
        </Link>
        <Link :href="route('reports.voyages')" :class="tabClass(current === 'voyages')">
            {{ t('reports.voyages') }}
        </Link>
        <Link :href="route('reports.land-trips')" :class="tabClass(current === 'land-trips')">
            {{ t('reports.land_trips') }}
        </Link>
    </nav>
</template>
