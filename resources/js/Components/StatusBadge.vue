<script setup>
import { computed } from 'vue';

const props = defineProps({
    tone: {
        type: String,
        default: 'neutral',
        validator: (value) => ['neutral', 'success', 'warning', 'info', 'danger'].includes(value),
    },
    label: { type: String, required: true },
    dot: { type: Boolean, default: true },
});

const toneClass = computed(() => `erp-badge-${props.tone}`);
</script>

<template>
    <span class="erp-badge" :class="toneClass">
        <span v-if="dot" class="erp-badge-dot" aria-hidden="true" />
        {{ label }}
    </span>
</template>
