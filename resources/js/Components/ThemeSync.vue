<script setup>
import { onMounted } from 'vue';
import { useTheme } from '@/composables/useTheme';

const { sync } = useTheme();

onMounted(sync);
</script>

<template>
    <span class="d-none" aria-hidden="true" />
</template>
