<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message" class="form-erp-error" role="alert">
        {{ message }}
    </div>
</template>
