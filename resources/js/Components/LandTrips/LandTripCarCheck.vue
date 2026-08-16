<script setup>
import axios from 'axios';
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import { sanitizeChassisNumber } from '@/composables/useChassisLetterO';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companyId: { type: [Number, String], required: true },
    active: { type: Boolean, default: false },
});

const { t } = useI18n();

const vinInput = ref('');
const loading = ref(false);
const indexLoading = ref(false);
const loaded = ref(false);
const ownCars = ref([]);
const elsewhere = ref({});
const results = ref([]);
const toast = ref('');

const normalize = (value) => sanitizeChassisNumber(value);

const lines = computed(() => vinInput.value
    .split(/[\s,;]+/)
    .map((line) => line.trim())
    .filter(Boolean));

const totalSearched = computed(() => lines.value.length);
const matchedCount = computed(() => results.value.filter((item) => item.status === 'matched').length);
const elsewhereCount = computed(() => results.value.filter((item) => item.status === 'elsewhere').length);
const missingCount = computed(() => results.value.filter((item) => item.status === 'missing').length);
const missingVins = computed(() => results.value.filter((item) => item.status === 'missing').map((item) => item.vin));
const busy = computed(() => loading.value || indexLoading.value);

const loadIndex = async () => {
    if (loaded.value || indexLoading.value) {
        return;
    }
    indexLoading.value = true;
    try {
        const { data } = await axios.get(route('land-trips.companies.car-check', props.companyId));
        ownCars.value = data.own ?? [];
        elsewhere.value = data.elsewhere ?? {};
        loaded.value = true;
    } catch {
        toast.value = t('land_trips.check_load_fail');
    } finally {
        indexLoading.value = false;
    }
};

watch(() => props.active, (on) => {
    if (on) {
        loadIndex();
    }
}, { immediate: true });

const matchOwn = (needle) => {
    const exact = ownCars.value.filter((car) => normalize(car.chassis_no) === needle);
    if (exact.length) {
        return exact;
    }

    let digits = (needle.match(/[A-Z]*(\d+)$/i) || [])[1] || needle.replace(/\D+/g, '');
    while (digits.length >= 5) {
        const hits = ownCars.value.filter((car) => normalize(car.chassis_no).endsWith(digits));
        if (hits.length) {
            return hits;
        }
        digits = digits.slice(0, -1);
    }

    return [];
};

const matchElsewhere = (needle) => {
    if (elsewhere.value[needle]) {
        return elsewhere.value[needle];
    }

    const keys = Object.keys(elsewhere.value);
    let digits = (needle.match(/[A-Z]*(\d+)$/i) || [])[1] || needle.replace(/\D+/g, '');
    while (digits.length >= 5) {
        const hit = keys.find((key) => key.endsWith(digits));
        if (hit) {
            return elsewhere.value[hit];
        }
        digits = digits.slice(0, -1);
    }

    return null;
};

const search = async () => {
    if (!lines.value.length) {
        toast.value = t('land_trips.check_need_vins');
        return;
    }

    loading.value = true;
    toast.value = '';
    await loadIndex();

    results.value = lines.value.map((raw) => {
        const vin = normalize(raw) || raw;
        const cars = matchOwn(vin);
        if (cars.length) {
            return { vin: raw, status: 'matched', cars, company_name: null };
        }

        const companyName = matchElsewhere(vin);
        if (companyName) {
            return { vin: raw, status: 'elsewhere', cars: [], company_name: companyName };
        }

        return { vin: raw, status: 'missing', cars: [], company_name: null };
    });
    loading.value = false;
};

const copyMissing = async () => {
    if (!missingVins.value.length) {
        return;
    }
    try {
        await navigator.clipboard.writeText(missingVins.value.join('\n'));
        toast.value = t('land_trips.check_copied_missing');
    } catch {
        toast.value = t('land_trips.check_copy_fail');
    }
};

const statusLabel = (item) => {
    if (item.status === 'elsewhere') {
        return t('land_trips.check_in_other_company', { company: item.company_name });
    }
    if (item.cars.length > 1) {
        return t('land_trips.check_ambiguous', { count: item.cars.length });
    }
    if (item.status === 'matched') {
        return t('land_trips.check_one_match');
    }

    return t('land_trips.check_missing');
};
</script>

<template>
    <div class="land-car-check">
        <div class="land-car-check-head">
            <h3 class="land-car-check-title">{{ t('land_trips.check_title') }}</h3>
            <p class="land-car-check-help">{{ t('land_trips.check_help') }}</p>
        </div>

        <p v-if="toast" class="land-car-check-toast">{{ toast }}</p>

        <div class="land-car-check-stats">
            <div class="land-car-check-stat">
                <span>{{ t('land_trips.check_input_count') }}</span>
                <strong>{{ totalSearched }}</strong>
            </div>
            <div class="land-car-check-stat is-ok">
                <span>{{ t('land_trips.check_matched') }}</span>
                <strong>{{ matchedCount }}</strong>
            </div>
            <div class="land-car-check-stat is-warn">
                <span>{{ t('land_trips.check_elsewhere') }}</span>
                <strong>{{ elsewhereCount }}</strong>
            </div>
            <div class="land-car-check-stat is-danger">
                <span>{{ t('land_trips.check_not_found') }}</span>
                <strong>{{ missingCount }}</strong>
            </div>
        </div>

        <div class="land-car-check-grid">
            <section class="land-car-check-panel">
                <div class="land-car-check-panel-bar">
                    <div>
                        <h4>{{ t('land_trips.check_list_title') }}</h4>
                        <p>{{ t('land_trips.check_list_help') }}</p>
                    </div>
                    <button type="button" class="btn btn-erp" :disabled="busy" @click="search">
                        {{ busy ? t('land_trips.check_searching') : t('land_trips.check_run') }}
                    </button>
                </div>
                <textarea
                    v-model="vinInput"
                    class="land-car-check-textarea"
                    :placeholder="t('land_trips.check_placeholder')"
                    spellcheck="false"
                    dir="ltr"
                />
            </section>

            <section class="land-car-check-results">
                <div v-if="missingVins.length" class="land-car-check-missing">
                    <div class="land-car-check-panel-bar">
                        <h4>{{ t('land_trips.check_missing_title') }}</h4>
                        <button type="button" class="btn btn-erp-ghost" @click="copyMissing">
                            {{ t('land_trips.check_copy_missing') }}
                        </button>
                    </div>
                    <div class="land-car-check-missing-grid">
                        <div v-for="(vin, index) in missingVins" :key="`${vin}-${index}`" class="land-car-check-chip">
                            <span>{{ index + 1 }}.</span>
                            <code>{{ vin }}</code>
                        </div>
                    </div>
                </div>

                <div v-if="results.length" class="land-car-check-cards">
                    <article
                        v-for="(item, index) in results"
                        :key="`${item.vin}-${index}`"
                        class="land-car-check-card"
                        :class="`is-${item.status}`"
                    >
                        <div class="land-car-check-card-head">
                            <div>
                                <div class="land-car-check-kicker">{{ t('land_trips.check_search_n', { n: index + 1 }) }}</div>
                                <code>{{ item.vin }}</code>
                            </div>
                            <span class="land-car-check-badge">{{ statusLabel(item) }}</span>
                        </div>
                        <p v-if="item.status === 'elsewhere'" class="land-car-check-other">
                            {{ t('land_trips.check_in_other_company', { company: item.company_name }) }}
                        </p>
                        <div v-if="item.cars.length" class="overflow-x-auto">
                            <table class="land-car-check-table">
                                <thead>
                                    <tr>
                                        <th>{{ t('land_trips.chassis') }}</th>
                                        <th>{{ t('land_trips.model') }}</th>
                                        <th>{{ t('land_trips.color') }}</th>
                                        <th>{{ t('land_trips.location_status') }}</th>
                                        <th>{{ t('land_trips.consignee') }}</th>
                                        <th>{{ t('common.date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="car in item.cars" :key="car.id">
                                        <td>
                                            <ChassisLetterOWarning :value="car.chassis_no" />
                                        </td>
                                        <td>{{ car.model || car.description || '—' }}</td>
                                        <td>{{ car.color || '—' }}</td>
                                        <td>{{ car.location_label || t('land_trips.unspecified_location') }}</td>
                                        <td>{{ car.consignee_name || '—' }}</td>
                                        <td>{{ car.created_at || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>

                <div v-else-if="!busy" class="land-car-check-empty">
                    <h4>{{ t('land_trips.check_empty') }}</h4>
                    <p>{{ t('land_trips.check_empty_help') }}</p>
                </div>
            </section>
        </div>
    </div>
</template>
