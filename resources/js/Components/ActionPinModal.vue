<script setup>
import { fbButton, fbGhostButton, fbInput, fbLabel } from '@/flowbite';
import { useActionPin } from '@/composables/useActionPin';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { state, submitPin, cancelPin, setInputEl } = useActionPin();

const bindInput = (el) => {
    setInputEl(el || null);
};
</script>

<template>
    <div
        v-if="state.open"
        class="erp-modal-backdrop"
        style="z-index: 1200"
        role="presentation"
        @click.self="cancelPin"
    >
        <div
            class="erp-modal-dialog is-narrow erp-card overflow-hidden p-0"
            role="dialog"
            aria-modal="true"
            :aria-label="t('action_pin.title')"
            @keydown.escape.prevent="cancelPin"
        >
            <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                <h3 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ t('action_pin.title') }}
                </h3>
                <p v-if="state.message" class="mb-0 text-sm text-gray-600 dark:text-gray-300">
                    {{ state.message }}
                </p>
            </div>

            <form class="p-4" @submit.prevent="submitPin">
                <label :class="fbLabel" for="action-pin-input">{{ t('action_pin.label') }}</label>
                <input
                    id="action-pin-input"
                    :ref="bindInput"
                    v-model="state.pin"
                    type="password"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    :class="fbInput"
                    :aria-invalid="state.error ? 'true' : 'false'"
                    @keydown.escape.prevent="cancelPin"
                />
                <p
                    v-if="state.error"
                    class="mt-2 mb-0 text-sm text-red-700 dark:text-red-400"
                    role="alert"
                >
                    {{ t('action_pin.wrong') }}
                </p>

                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <button type="button" :class="[fbGhostButton, '!w-auto']" @click="cancelPin">
                        {{ t('action_pin.cancel') }}
                    </button>
                    <button type="submit" :class="[fbButton, '!w-auto min-w-28']">
                        {{ t('action_pin.confirm') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
