<script setup>
import { useI18n } from 'vue-i18n';

defineProps({
    url: { type: String, default: '' },
    name: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
    replacing: { type: Boolean, default: false },
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
    <div class="d-flex flex-column gap-1 align-items-start">
        <button
            v-if="url"
            type="button"
            class="btn btn-sm btn-erp-ghost"
            @click="emit('preview')"
        >
            {{ t('land_trips.preview') }}
        </button>
        <label
            v-if="canManage"
            class="btn btn-sm btn-erp-ghost mb-0"
            :class="{ disabled: replacing }"
        >
            {{ replacing
                ? t('common.saving')
                : (url ? t('land_trips.replace_attachment') : t('land_trips.attach_file')) }}
            <input
                type="file"
                class="d-none"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                :disabled="replacing"
                @change="onFile"
            />
        </label>
        <span v-else-if="!url">—</span>
        <span v-if="name" class="small text-secondary text-break">{{ name }}</span>
    </div>
</template>
