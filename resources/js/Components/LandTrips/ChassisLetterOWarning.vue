<script setup>
import { chassisHasLetterO } from '@/composables/useChassisLetterO';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    value: { type: [String, Number], default: '' },
});

const { t } = useI18n();
const hasLetterO = computed(() => chassisHasLetterO(props.value));
</script>

<template>
    <span class="land-chassis-cell">
        <span class="font-monospace fw-semibold">{{ value || '—' }}</span>
        <span
            v-if="hasLetterO"
            class="land-chassis-o-flag"
            :title="t('land_trips.chassis_letter_o_hint')"
        >
            {{ t('land_trips.chassis_letter_o') }}
        </span>
    </span>
</template>
