<script setup>
import { computed } from 'vue';

const props = defineProps({
    steps: { type: Array, default: () => [] },
    current: { type: String, required: true },
});

const currentIndex = computed(() =>
    props.steps.findIndex((step) => step.value === props.current),
);
</script>

<template>
    <ol class="erp-stepper list-unstyled mb-0" aria-label="Status">
        <li
            v-for="(step, index) in steps"
            :key="step.value"
            class="erp-step"
            :class="{
                'is-done': index < currentIndex,
                'is-current': index === currentIndex,
            }"
        >
            <span class="erp-step-dot" aria-hidden="true" />
            <span class="erp-step-label">{{ step.label }}</span>
        </li>
    </ol>
</template>
