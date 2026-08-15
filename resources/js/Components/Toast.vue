<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    message: { type: String, default: '' },
});

const emit = defineEmits(['dismiss']);
let timer = null;

watch(
    () => props.message,
    (message) => {
        clearTimeout(timer);
        if (!message) {
            return;
        }
        timer = setTimeout(() => emit('dismiss'), 3500);
    },
    { immediate: true },
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="erp-toast-enter-active"
            enter-from-class="erp-toast-enter-from"
            enter-to-class="erp-toast-enter-to"
            leave-active-class="erp-toast-leave-active"
            leave-from-class="erp-toast-leave-from"
            leave-to-class="erp-toast-leave-to"
        >
            <div
                v-if="message"
                class="erp-toast erp-toast-success"
                role="status"
                aria-live="polite"
            >
                <span class="erp-flash-dot" aria-hidden="true" />
                <span>{{ message }}</span>
            </div>
        </Transition>
    </Teleport>
</template>
