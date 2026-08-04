<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: [String, Number], default: 0 },
    tone: {
        type: String,
        default: 'plain',
        validator: (value) => ['debit', 'credit', 'balance', 'plain'].includes(value),
    },
    currency: { type: String, default: null },
    showZero: { type: Boolean, default: false },
});

const numeric = computed(() => {
    const parsed = Number(props.value);
    return Number.isFinite(parsed) ? parsed : 0;
});

const display = computed(() => {
    if ((props.tone === 'debit' || props.tone === 'credit') && !props.showZero && numeric.value <= 0) {
        return '—';
    }

    const formatted =
        typeof props.value === 'string' && props.value.trim() !== ''
            ? props.value
            : numeric.value.toFixed(2);

    return props.currency ? `${formatted} ${props.currency}` : formatted;
});

const amountClass = computed(() => {
    if (props.tone === 'debit') {
        return numeric.value > 0 ? 'erp-amount erp-amount-debit' : 'erp-amount';
    }

    if (props.tone === 'credit') {
        return numeric.value > 0 ? 'erp-amount erp-amount-credit' : 'erp-amount';
    }

    if (props.tone === 'balance') {
        if (numeric.value > 0) {
            return 'erp-amount erp-amount-balance is-owing';
        }
        if (numeric.value < 0) {
            return 'erp-amount erp-amount-balance is-owed';
        }
        return 'erp-amount erp-amount-balance is-settled';
    }

    return 'erp-amount';
});

defineExpose({ numeric });
</script>

<template>
    <span :class="amountClass">{{ display }}</span>
</template>
