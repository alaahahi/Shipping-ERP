<script setup>
import { nextTick, ref, watch } from 'vue';
import VOtpInput from 'vue3-otp-input';
import { fbGhostButton, fbLabel } from '@/flowbite';
import { useActionPin } from '@/composables/useActionPin';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { state, pinLength, submitPin, cancelPin, setOtpApi, onPinChange } = useActionPin();
const otpInput = ref(null);

watch(otpInput, (el) => {
    setOtpApi(el || null);
});

watch(
    () => state.open,
    (open) => {
        if (!open) {
            return;
        }

        nextTick(() => {
            document.querySelector('.action-pin-otp-wrap input')?.focus?.();
        });
    }
);
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

            <form class="p-4" @submit.prevent>
                <label :class="fbLabel" id="action-pin-label">{{ t('action_pin.label') }}</label>
                <div
                    class="action-pin-otp-wrap"
                    :class="{ 'is-error': state.error, 'is-shaking': state.shaking }"
                    role="group"
                    aria-labelledby="action-pin-label"
                    :aria-invalid="state.error ? 'true' : 'false'"
                >
                    <VOtpInput
                        ref="otpInput"
                        input-classes="action-pin-digit"
                        input-type="tel"
                        inputmode="numeric"
                        :num-inputs="pinLength"
                        :should-auto-focus="true"
                        :should-focus-order="true"
                        v-model:value="state.pin"
                        @on-change="onPinChange"
                        @on-complete="submitPin"
                    />
                </div>
                <p class="mt-2 mb-0 text-xs text-gray-500 dark:text-gray-400">
                    {{ t('action_pin.hint') }}
                </p>
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
                </div>
            </form>
        </div>
    </div>
</template>
