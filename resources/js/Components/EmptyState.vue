<script setup>
defineProps({
    icon: { type: String, default: '·' },
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="erp-empty">
        <div class="erp-empty-icon">{{ icon }}</div>
        <div class="fw-semibold mb-1" v-if="title">{{ title }}</div>
        <div><slot /></div>
    </div>
</template>
