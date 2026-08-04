<script setup>
defineProps({
    message: { type: String, default: '' },
});
</script>

<template>
    <div v-if="message" class="erp-flash erp-flash-success mb-3" role="status">
        <span class="erp-flash-dot" aria-hidden="true" />
        <span>{{ message }}</span>
    </div>
</template>
