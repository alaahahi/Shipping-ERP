<script setup>
import { useAttrs } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const attrs = useAttrs();

defineProps({
    text: { type: String, default: 'Button' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <button
        v-bind="attrs"
        :type="type"
        :disabled="disabled"
        class="login-submit inline-flex h-12 w-full items-center justify-center gap-2 rounded-full px-6 text-base font-semibold transition duration-200 ease-out hover:-translate-y-0.5 disabled:pointer-events-none disabled:translate-y-0 disabled:opacity-50"
    >
        <span>{{ text }}</span>
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="h-4 w-4 rtl:rotate-180"
            aria-hidden="true"
        >
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
        </svg>
    </button>
</template>
