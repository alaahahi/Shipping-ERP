<script setup>
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
    emptyLabel: { type: String, default: '' },
});

const chassis = computed(() => (props.items ?? []).filter((item) => item?.chassis_no));
</script>

<template>
    <div v-if="chassis.length" class="pay-chassis-badges" :class="{ 'is-compact': compact }">
        <span
            v-for="item in chassis"
            :key="item.id || item.land_trip_car_id || item.chassis_no"
            class="pay-chassis-pill"
            dir="ltr"
        >
            <ChassisLetterOWarning :value="item.chassis_no" />
        </span>
    </div>
    <p v-else-if="emptyLabel" class="pay-chassis-empty mb-0">{{ emptyLabel }}</p>
</template>
