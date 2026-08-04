<script setup>
import LocaleSync from '@/Components/LocaleSync.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();
</script>

<template>
    <div class="auth-shell">
        <LocaleSync />

        <section class="auth-visual">
            <div class="auth-brand">{{ t('app.name') }}</div>

            <div class="auth-headline">
                <h1>{{ t('auth.visual_title') }}</h1>
                <p>{{ t('auth.visual_text') }}</p>
            </div>

            <div class="small position-relative" style="z-index: 1; opacity: 0.75">
                {{ t('auth.visual_footer') }}
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <h2 class="erp-display">{{ title || t('auth.sign_in') }}</h2>
                <p class="auth-subtitle">{{ subtitle || t('auth.sign_in_subtitle') }}</p>
                <slot />
            </div>
        </section>
    </div>
</template>
