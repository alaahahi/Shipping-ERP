<script setup>
import { fbGhostButton } from '@/flowbite';
import { useI18n } from 'vue-i18n';

defineProps({
    show: { type: Boolean, default: false },
    url: { type: String, default: '' },
    name: { type: String, default: '' },
    isImage: { type: Boolean, default: false },
    isPdf: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);
const { t } = useI18n();
</script>

<template>
    <div
        v-if="show && url"
        class="erp-modal-backdrop"
        @click.self="emit('close')"
        @keydown.escape.prevent="emit('close')"
    >
        <div
            class="erp-modal-dialog erp-card overflow-hidden p-0"
            style="width: min(920px, 100%)"
            role="dialog"
            aria-modal="true"
            :aria-label="t('common.preview')"
        >
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 p-3 dark:border-gray-700">
                <div>
                    <h3 class="erp-display mb-1 text-lg font-semibold">{{ t('common.preview') }}</h3>
                    <p v-if="name" class="mb-0 text-sm text-gray-500 dark:text-gray-400">{{ name }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="url"
                        target="_blank"
                        rel="noopener noreferrer"
                        :class="fbGhostButton"
                        class="!w-auto cursor-pointer"
                    >
                        {{ t('common.view_attachment') }}
                    </a>
                    <button type="button" :class="fbGhostButton" class="cursor-pointer" @click="emit('close')">
                        {{ t('common.cancel') }}
                    </button>
                </div>
            </div>
            <div class="bg-gray-100 p-3 dark:bg-gray-900">
                <img
                    v-if="isImage"
                    :src="url"
                    :alt="name || t('common.preview')"
                    class="mx-auto block max-h-[75vh] w-auto max-w-full rounded-lg"
                />
                <iframe
                    v-else-if="isPdf"
                    :src="url"
                    class="h-[75vh] w-full rounded-lg bg-white"
                    style="border: 0"
                    :title="name || t('common.preview')"
                />
                <p v-else class="mb-0">
                    <a :href="url" target="_blank" rel="noopener noreferrer" class="text-teal-700 underline dark:text-teal-400">
                        {{ name || t('common.view_attachment') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</template>
