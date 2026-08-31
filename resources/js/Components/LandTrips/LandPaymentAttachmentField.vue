<script setup>
import AttachmentField from '@/Components/AttachmentField.vue';

defineProps({
    url: { type: String, default: '' },
    name: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
    replacing: { type: Boolean, default: false },
});

defineEmits(['preview', 'replace']);
</script>

<template>
    <AttachmentField
        :url="url"
        :name="name"
        :can-manage="canManage"
        :replacing="replacing"
        @preview="$emit('preview')"
        @replace="$emit('replace', $event)"
    />
</template>
