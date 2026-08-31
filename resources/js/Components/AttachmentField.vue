<script setup>
import { fbGhostButton } from '@/flowbite';
import { useI18n } from 'vue-i18n';

defineProps({
    url: { type: String, default: '' },
    name: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
    replacing: { type: Boolean, default: false },
    accept: { type: String, default: 'image/jpeg,image/png,image/webp,application/pdf' },
});

const emit = defineEmits(['preview', 'replace']);
const { t } = useI18n();

const onFile = (event) => {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';
    if (file) {
        emit('replace', file);
    }
};
</script>

<template>
    <div class="flex flex-col items-start gap-1">
        <button
            v-if="url"
            type="button"
            :class="fbGhostButton"
            class="!w-auto cursor-pointer !px-2 !py-1 text-xs"
            :title="name || t('common.preview')"
            @click="emit('preview')"
        >
            {{ t('common.preview') }}
        </button>
        <label
            v-if="canManage"
            :class="[fbGhostButton, replacing ? 'pointer-events-none opacity-60' : 'cursor-pointer']"
            class="mb-0 !w-auto !px-2 !py-1 text-xs"
        >
            {{ replacing
                ? t('common.saving')
                : (url ? t('common.replace_attachment') : t('common.attach_file')) }}
            <input
                type="file"
                class="sr-only"
                :accept="accept"
                :disabled="replacing"
                @change="onFile"
            />
        </label>
        <span v-else-if="!url">—</span>
    </div>
</template>
