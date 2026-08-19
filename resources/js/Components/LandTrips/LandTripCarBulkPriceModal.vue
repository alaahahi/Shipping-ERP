<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    companyId: { type: [Number, String], required: true },
    carIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const { t } = useI18n();

const form = useForm({
    price: '',
    car_ids: [],
});

const count = computed(() => props.carIds.length);
const canSubmit = computed(() => count.value > 0 && form.price !== '' && !form.processing);

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    form.car_ids = [...props.carIds];
    form.patch(route('land-trips.companies.cars.bulk-price', props.companyId), {
        preserveScroll: true,
        preserveState: true,
        only: ['cars', 'wallet', 'flash', 'errors'],
        onSuccess: () => {
            emit('saved', { price: Number(form.price), carIds: [...props.carIds] });
            emit('close');
        },
    });
};

const reset = () => {
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
        document.getElementById('land-bulk-price-input')?.focus();
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
                        aria-labelledby="land-bulk-price-title"
                    >
                        <header class="pay-chassis-head">
                            <div>
                                <h3 id="land-bulk-price-title" class="pay-chassis-title">{{ t('land_trips.bulk_price_action') }}</h3>
                                <p class="pay-chassis-help mb-0">
                                    {{ t('land_trips.bulk_price_help', { count }) }}
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

                        <div class="land-transfer-confirm">
                            <label class="form-erp-label" for="land-bulk-price-input">{{ t('land_trips.bulk_price_label') }}</label>
                            <input
                                id="land-bulk-price-input"
                                v-model="form.price"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                class="form-control form-erp-control"
                                :disabled="form.processing"
                            />
                            <p class="small text-secondary mt-2 mb-0">{{ t('land_trips.bulk_price_selected_count', { count }) }}</p>
                            <InputError :message="form.errors.price || form.errors.car_ids" />
                            <div class="pay-chassis-actions">
                                <button type="button" class="btn btn-erp-ghost" :disabled="form.processing" @click="emit('close')">
                                    {{ t('common.cancel') }}
                                </button>
                                <button type="button" class="btn btn-erp" :disabled="!canSubmit" @click="submit">
                                    {{ form.processing ? t('common.saving') : t('common.save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
