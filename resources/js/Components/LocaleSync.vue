<script setup>
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { applyDocumentLocale } from '@/i18n';

const page = usePage();
const { locale } = useI18n();

watch(
    () => page.props.appSettings?.locale,
    (nextLocale) => {
        if (!nextLocale) {
            return;
        }

        locale.value = nextLocale;
        applyDocumentLocale(nextLocale);
    },
    { immediate: true },
);
</script>

<template>
    <span class="d-none" aria-hidden="true" />
</template>
