<script setup>
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import InputError from '@/Components/InputError.vue';
import { sanitizeChassisNumber } from '@/composables/useChassisLetterO';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    companyId: { type: [Number, String], required: true },
    submitRoute: { type: String, required: true },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    assigned: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);
const { t } = useI18n();

const paste = ref('');
const skipped = ref([]);
const matched = ref([]);
const indexLoading = ref(false);
const indexError = ref('');
const ownCars = ref([]);
const loaded = ref(false);

const form = useForm({
    car_ids: [],
    chassis_text: '',
});

const assignedCount = computed(() => matched.value.length);
const skippedCount = computed(() => skipped.value.length);
const titleText = computed(() => props.title || t('land_trips.assign_chassis'));

const normalize = (value) => sanitizeChassisNumber(value);

const lastSix = (value) => {
    const needle = normalize(value);
    if (!needle) {
        return null;
    }
    const tail = needle.slice(-6);
    if (/^\d{6}$/.test(tail)) {
        return tail;
    }
    const digits = (needle.match(/(\d{6,})$/) || [])[1];
    return digits ? digits.slice(-6) : null;
};

const matchOwn = (needle) => {
    const exact = ownCars.value.filter((car) => normalize(car.chassis_no) === needle);
    if (exact.length) {
        return exact;
    }
    const suffix = lastSix(needle);
    if (!suffix) {
        return [];
    }

    return ownCars.value.filter((car) => lastSix(car.chassis_no) === suffix);
};

const loadIndex = async () => {
    if (loaded.value || indexLoading.value) {
        return;
    }
    indexLoading.value = true;
    indexError.value = '';
    try {
        const { data } = await axios.get(route('land-trips.companies.car-check', props.companyId));
        ownCars.value = data.own ?? [];
        loaded.value = true;
    } catch {
        indexError.value = t('land_trips.check_load_fail');
    } finally {
        indexLoading.value = false;
    }
};

const seedAssigned = () => {
    matched.value = (props.assigned ?? [])
        .filter((item) => item?.chassis_no)
        .map((item) => ({
            id: item.land_trip_car_id || item.id,
            chassis_no: item.chassis_no,
        }));
    skipped.value = [];
    paste.value = '';
};

const parsePaste = async () => {
    await loadIndex();
    const lines = String(paste.value ?? '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    if (!lines.length) {
        return;
    }

    const nextSkipped = [];
    const byId = new Map(matched.value.map((car) => [Number(car.id), car]));

    lines.forEach((raw) => {
        const needle = normalize(raw) || raw;
        const hits = matchOwn(needle);
        if (hits.length === 1) {
            const car = hits[0];
            byId.set(Number(car.id), { id: car.id, chassis_no: car.chassis_no });
            return;
        }
        nextSkipped.push({
            line: raw,
            reason: hits.length > 1 ? 'ambiguous' : 'missing',
        });
    });

    matched.value = [...byId.values()];
    skipped.value = nextSkipped;
    paste.value = '';
};

const onPasteKeydown = async (event) => {
    if (event.key !== 'Enter' || event.shiftKey) {
        return;
    }
    event.preventDefault();
    await parsePaste();
};

const onPaste = async () => {
    await nextTick();
    await parsePaste();
};

const removeMatched = (carId) => {
    matched.value = matched.value.filter((car) => Number(car.id) !== Number(carId));
};

const submit = () => {
    if (form.processing) {
        return;
    }

    form.car_ids = matched.value.map((car) => car.id);
    form.chassis_text = '';
    form.post(props.submitRoute, {
        preserveScroll: true,
        preserveState: true,
        only: ['wallet', 'errors', 'flash'],
        onSuccess: () => emit('close'),
    });
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && props.show && !form.processing) {
        emit('close');
    }
};

watch(
    () => props.show,
    async (open) => {
        if (!open) {
            window.removeEventListener('keydown', onKeydown);
            form.clearErrors();
            loaded.value = false;
            ownCars.value = [];
            return;
        }
        seedAssigned();
        window.addEventListener('keydown', onKeydown);
        await loadIndex();
        await nextTick();
        document.getElementById('pay-chassis-paste')?.focus();
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="pay-modal-enter"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="pay-modal-leave"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="pay-chassis-overlay"
                role="presentation"
                @click.self="!form.processing && emit('close')"
            >
                <Transition
                    enter-active-class="pay-modal-enter"
                    enter-from-class="opacity-0 translate-y-1 scale-[0.98]"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="pay-modal-leave"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-1 scale-[0.98]"
                >
                    <div
                        v-if="show"
                        class="pay-chassis-panel"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="pay-chassis-title"
                    >
                        <header class="pay-chassis-head">
                            <div>
                                <h3 id="pay-chassis-title" class="pay-chassis-title">{{ titleText }}</h3>
                                <p class="pay-chassis-help mb-0">{{ subtitle || t('land_trips.assign_chassis_help') }}</p>
                            </div>
                            <button
                                type="button"
                                class="btn btn-erp-ghost btn-sm"
                                :disabled="form.processing"
                                :aria-label="t('common.close')"
                                @click="emit('close')"
                            >
                                {{ t('common.close') }}
                            </button>
                        </header>

                        <div class="pay-chassis-stats" aria-live="polite">
                            <div class="pay-chassis-stat is-ok">
                                <span>{{ t('land_trips.check_matched') }}</span>
                                <strong>{{ assignedCount }}</strong>
                            </div>
                            <div class="pay-chassis-stat is-danger">
                                <span>{{ t('land_trips.check_not_found') }}</span>
                                <strong>{{ skippedCount }}</strong>
                            </div>
                        </div>

                        <label class="form-erp-label" for="pay-chassis-paste">{{ t('land_trips.assign_chassis_paste') }}</label>
                        <p class="pay-chassis-hint">{{ t('land_trips.assign_chassis_paste_help') }}</p>
                        <textarea
                            id="pay-chassis-paste"
                            v-model="paste"
                            class="form-control form-erp-control pay-chassis-textarea"
                            rows="5"
                            dir="ltr"
                            spellcheck="false"
                            :disabled="form.processing || indexLoading"
                            :placeholder="t('land_trips.assign_chassis_placeholder')"
                            @keydown="onPasteKeydown"
                            @paste="onPaste"
                        />
                        <p v-if="indexLoading" class="small text-secondary mt-2 mb-0">{{ t('land_trips.check_searching') }}</p>
                        <p v-if="indexError" class="small text-danger mt-2 mb-0" role="alert">{{ indexError }}</p>
                        <InputError :message="form.errors.car_ids || form.errors.chassis_text" />

                        <section class="pay-chassis-block">
                            <h4 class="pay-chassis-block-title">{{ t('land_trips.assigned_chassis') }}</h4>
                            <div v-if="matched.length" class="pay-chassis-selected">
                                <button
                                    v-for="car in matched"
                                    :key="car.id"
                                    type="button"
                                    class="pay-chassis-pill is-selected"
                                    dir="ltr"
                                    :aria-label="t('land_trips.remove_chassis')"
                                    @click="removeMatched(car.id)"
                                >
                                    <ChassisLetterOWarning :value="car.chassis_no" />
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                            <p v-else class="pay-chassis-empty mb-0">{{ t('land_trips.assigned_chassis_empty') }}</p>
                        </section>

                        <section v-if="skipped.length" class="pay-chassis-block">
                            <h4 class="pay-chassis-block-title">{{ t('land_trips.skipped_chassis') }}</h4>
                            <ul class="pay-chassis-skipped mb-0">
                                <li v-for="(item, index) in skipped" :key="`${item.line}-${index}`">
                                    <code dir="ltr">{{ item.line }}</code>
                                    <span>{{ item.reason === 'ambiguous' ? t('land_trips.check_ambiguous', { count: 2 }) : t('land_trips.check_missing') }}</span>
                                </li>
                            </ul>
                        </section>

                        <footer class="pay-chassis-actions">
                            <button
                                type="button"
                                class="btn btn-erp-ghost"
                                :disabled="form.processing"
                                @click="emit('close')"
                            >
                                {{ t('common.cancel') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-erp"
                                :disabled="form.processing || indexLoading"
                                @click="submit"
                            >
                                {{ form.processing ? t('common.saving') : t('land_trips.save_chassis') }}
                            </button>
                        </footer>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
