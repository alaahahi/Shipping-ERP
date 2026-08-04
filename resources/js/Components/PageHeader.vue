<script setup>
defineProps({
    kicker: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="erp-page-header">
        <div>
            <div v-if="kicker" class="erp-page-kicker">{{ kicker }}</div>
            <h2 class="erp-page-title">{{ title }}</h2>
            <p v-if="subtitle" class="erp-page-subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="d-flex flex-wrap gap-2 align-items-center">
            <slot name="actions" />
        </div>
    </div>
</template>
