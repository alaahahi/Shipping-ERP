<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    url: {
        type: String,
        default: 'https://intellij-app.com/',
    },
    product: {
        type: String,
        default: '',
    },
    brand: {
        type: String,
        default: 'IntelliJ App',
    },
    printHidden: {
        type: Boolean,
        default: true,
    },
    tone: {
        type: String,
        default: 'default',
    },
});

const { t } = useI18n();
const year = computed(() => new Date().getFullYear());
const productName = computed(() => props.product || t('app.name'));
</script>

<template>
    <p
        class="intellij-credit"
        :class="{
            'print:hidden': printHidden,
            'intellij-credit--light': tone === 'light',
        }"
        dir="rtl"
    >
        © {{ year }} {{ productName }} · {{ t('app.powered_by') }}
        <a
            :href="url"
            target="_blank"
            rel="noopener noreferrer"
            class="intellij-credit__link"
        >
            {{ brand }}
        </a>
    </p>
</template>
