<script setup>
import LocaleSync from '@/Components/LocaleSync.vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const { t } = useI18n();
const companyName = computed(() => page.props.appSettings?.companyName || t('app.name'));
</script>

<template>
    <div class="erp-print-shell">
        <LocaleSync />

        <div class="erp-print-toolbar no-print">
            <div class="erp-print-toolbar-start">
                <slot name="toolbar-start">
                    <span class="fw-semibold">{{ companyName }}</span>
                </slot>
            </div>
            <div class="erp-print-toolbar-actions">
                <slot name="toolbar-actions" />
            </div>
        </div>

        <main class="erp-print-sheet">
            <slot />
        </main>
    </div>
</template>
