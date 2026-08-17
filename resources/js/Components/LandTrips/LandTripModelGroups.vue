<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companyId: { type: [Number, String], required: true },
    search: { type: String, default: '' },
    locationStatusId: { type: [Number, String], default: '' },
});

const { t } = useI18n();

const groups = ref([]);
const loading = ref(false);
const error = ref('');

const matchesGroup = (group, query) => {
    if (!query) {
        return true;
    }

    const hay = [
        group.model_label,
        group.model_key,
        ...(group.chassis_nos ?? []),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return hay.includes(query);
};

const visibleGroups = computed(() => {
    const query = String(props.search ?? '').trim().toLowerCase();
    if (!query) {
        return groups.value;
    }

    return groups.value.filter((group) => matchesGroup(group, query));
});

const groupCount = computed(() => visibleGroups.value.length);
const carCount = computed(() => visibleGroups.value.reduce((sum, g) => sum + (g.cars_count || 0), 0));

const loadGroups = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(route('land-trips.companies.model-groups', props.companyId), {
            params: {
                location_status_id: props.locationStatusId || undefined,
            },
        });
        groups.value = data.groups ?? [];
    } catch {
        error.value = t('land_trips.model_groups_load_error');
        groups.value = [];
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.companyId, props.locationStatusId],
    () => {
        loadGroups();
    },
    { immediate: true },
);

const groupTitle = (group) => (
    group.is_unspecified
        ? t('land_trips.model_unspecified')
        : (group.model_label || group.model_key)
);

defineExpose({ reload: loadGroups });
</script>

<template>
    <div class="land-cmr-groups" role="region" :aria-label="t('land_trips.view_by_model')">
        <div class="land-cmr-groups-meta">
            <p class="land-cmr-groups-help mb-0">
                {{ loading
                    ? t('land_trips.loading_more')
                    : t('land_trips.model_groups_summary', { groups: groupCount, cars: carCount }) }}
            </p>
        </div>

        <p v-if="error" class="land-cmr-groups-error mb-0" role="alert">{{ error }}</p>

        <div v-if="loading && !groups.length" class="land-cmr-groups-skeleton" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="land-cmr-group is-skeleton" />
        </div>

        <div v-else-if="!loading && !visibleGroups.length" class="land-cmr-groups-empty">
            {{ t('land_trips.model_groups_empty') }}
        </div>

        <TransitionGroup
            v-else
            name="land-cmr-stagger"
            tag="div"
            class="land-cmr-groups-list"
            appear
        >
            <article
                v-for="(group, index) in visibleGroups"
                :key="group.model_key === '' ? '__unspecified__' : group.model_key"
                class="land-cmr-group"
                :class="{ 'is-unspecified': group.is_unspecified }"
                :style="{ '--cmr-delay': `${Math.min(index, 12) * 30}ms` }"
            >
                <header class="land-cmr-group-head">
                    <div class="land-cmr-group-title-wrap">
                        <span
                            class="land-cmr-badge"
                            :class="{ 'is-muted': group.is_unspecified }"
                        >
                            {{ t('land_trips.model') }}
                        </span>
                        <h3 class="land-cmr-group-title">{{ groupTitle(group) }}</h3>
                        <span class="land-cmr-count">
                            {{ t('land_trips.cmr_cars_count', { count: group.cars_count }) }}
                        </span>
                    </div>
                </header>

                <div class="land-cmr-chassis">
                    <span
                        v-for="(chassis, cIdx) in group.chassis_nos"
                        :key="`${group.model_key}-${chassis}-${cIdx}`"
                        class="land-cmr-chip"
                    >
                        {{ chassis }}
                    </span>
                    <span v-if="!group.chassis_nos?.length" class="land-cmr-chassis-empty">
                        {{ t('land_trips.cmr_no_chassis') }}
                    </span>
                </div>
            </article>
        </TransitionGroup>
    </div>
</template>
