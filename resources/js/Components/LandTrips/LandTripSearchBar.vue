<script setup>
import { fbInput } from '@/flowbite';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, required: true },
    searching: { type: Boolean, default: false },
    inputId: { type: String, default: 'land-trip-search' },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const value = computed({
    get: () => props.modelValue ?? '',
    set: (next) => emit('update:modelValue', next),
});

const inputClass = `${fbInput} land-live-search-input min-h-12 text-base ps-11 pe-11`;

const clear = () => {
    emit('update:modelValue', '');
};
</script>

<template>
    <div class="land-live-search">
        <form class="land-live-search-form" @submit.prevent>
            <label class="visually-hidden" :for="inputId">{{ placeholder }}</label>
            <div class="land-live-search-field">
                <span class="land-live-search-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 104.384 8.737l3.19 3.19a.75.75 0 101.06-1.06l-3.19-3.19A5.5 5.5 0 009 3.5zM5.5 9a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z" clip-rule="evenodd" />
                    </svg>
                </span>
                <input
                    :id="inputId"
                    v-model="value"
                    type="search"
                    :class="inputClass"
                    :placeholder="placeholder"
                    autocomplete="off"
                    enterkeyhint="search"
                    :aria-busy="searching ? 'true' : 'false'"
                />
                <span v-if="searching" class="land-live-search-status" aria-hidden="true">
                    <span class="land-live-search-spinner" />
                </span>
                <button
                    v-else-if="value"
                    type="button"
                    class="land-live-search-clear"
                    :aria-label="t('common.reset')"
                    @click="clear"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
</template>
