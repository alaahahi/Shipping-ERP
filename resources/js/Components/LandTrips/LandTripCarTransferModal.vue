<script setup>
import InputError from '@/Components/InputError.vue';
import LandTripSearchBar from '@/Components/LandTrips/LandTripSearchBar.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    company: { type: Object, required: true },
    carIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'transferred']);
const { t } = useI18n();

const search = ref('');
const results = ref([]);
const searching = ref(false);
const selected = ref(null);
const step = ref('pick');
let searchTimer = null;
let searchRequest = 0;

const form = useForm({
    to_company_id: '',
    car_ids: [],
    notes: '',
});

const count = computed(() => props.carIds.length);
const canConfirm = computed(() => selected.value && Number(selected.value.id) !== Number(props.company.id));

const runSearch = async () => {
    const query = String(search.value ?? '').trim();
    if (query === '') {
        results.value = [];
        searching.value = false;
        return;
    }

    const requestId = ++searchRequest;
    searching.value = true;
    try {
        const { data } = await axios.get(route('land-trips.search-companies'), {
            params: { search: query },
        });
        if (requestId !== searchRequest) {
            return;
        }
        results.value = (data.companies ?? []).filter((item) => Number(item.id) !== Number(props.company.id));
    } catch {
        if (requestId === searchRequest) {
            results.value = [];
        }
    } finally {
        if (requestId === searchRequest) {
            searching.value = false;
        }
    }
};

watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(runSearch, 220);
});

const choose = (company) => {
    selected.value = company;
    form.to_company_id = company.id;
    form.clearErrors('to_company_id');
    step.value = 'confirm';
};

const backToPick = () => {
    step.value = 'pick';
};

const submit = () => {
    if (!canConfirm.value || form.processing) {
        return;
    }

    form.car_ids = [...props.carIds];
    form.post(route('land-trips.companies.cars.transfer', props.company.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['cars', 'statusSummary', 'flash', 'errors'],
        onSuccess: () => {
            emit('transferred');
            emit('close');
        },
    });
};

const reset = () => {
    search.value = '';
    results.value = [];
    selected.value = null;
    step.value = 'pick';
    form.reset();
    form.clearErrors();
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
            reset();
            return;
        }
        window.addEventListener('keydown', onKeydown);
        await nextTick();
        document.getElementById('land-transfer-company-search')?.focus();
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
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
                        aria-labelledby="land-transfer-title"
                    >
                        <header class="pay-chassis-head">
                            <div>
                                <h3 id="land-transfer-title" class="pay-chassis-title">{{ t('land_trips.move_to_company') }}</h3>
                                <p class="pay-chassis-help mb-0">
                                    {{ t('land_trips.transfer_cars_help', { count }) }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="btn btn-erp-ghost btn-sm"
                                :disabled="form.processing"
                                @click="emit('close')"
                            >
                                {{ t('common.close') }}
                            </button>
                        </header>

                        <div v-if="step === 'pick'">
                            <div id="land-transfer-search">
                                <LandTripSearchBar
                                    v-model="search"
                                    input-id="land-transfer-company-search"
                                    :placeholder="t('land_trips.search_companies')"
                                />
                            </div>
                            <p v-if="searching" class="small text-secondary mt-2 mb-0">{{ t('land_trips.duplicates_checking') }}</p>
                            <InputError :message="form.errors.to_company_id || form.errors.car_ids" />
                            <ul v-if="results.length" class="land-transfer-results">
                                <li v-for="item in results" :key="item.id">
                                    <button type="button" class="land-transfer-company" @click="choose(item)">
                                        <strong>{{ item.name }}</strong>
                                        <span>{{ t('land_trips.cars') }}: {{ item.cars_count ?? 0 }}</span>
                                    </button>
                                </li>
                            </ul>
                            <p v-else-if="String(search).trim() && !searching" class="small text-secondary mt-3 mb-0">
                                {{ t('common.no_results') }}
                            </p>
                        </div>

                        <div v-else class="land-transfer-confirm">
                            <p class="mb-3">
                                {{ t('land_trips.transfer_confirm', { count, company: selected?.name }) }}
                            </p>
                            <label class="form-erp-label" for="land-transfer-notes">{{ t('common.notes') }}</label>
                            <input
                                id="land-transfer-notes"
                                v-model="form.notes"
                                type="text"
                                maxlength="500"
                                class="form-control form-erp-control"
                            />
                            <InputError :message="form.errors.to_company_id || form.errors.car_ids || form.errors.notes" />
                            <div class="pay-chassis-actions">
                                <button type="button" class="btn btn-erp-ghost" :disabled="form.processing" @click="backToPick">
                                    {{ t('common.back') }}
                                </button>
                                <button type="button" class="btn btn-erp" :disabled="form.processing || !canConfirm" @click="submit">
                                    {{ form.processing ? t('common.saving') : t('land_trips.confirm_transfer') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
