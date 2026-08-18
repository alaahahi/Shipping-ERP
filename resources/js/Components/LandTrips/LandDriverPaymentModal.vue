<script setup>
import InputError from '@/Components/InputError.vue';
import { useActionPin } from '@/composables/useActionPin';
import { fbButton, fbGhostButton, fbInput, fbLabel } from '@/flowbite';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    companyId: { type: [Number, String], required: true },
    driverNames: { type: Array, default: () => [] },
    cashAccount: { type: Object, default: null },
});

const emit = defineEmits(['close']);
const { t } = useI18n();
const { requireActionPin } = useActionPin();
const driverMenuOpen = ref(false);
const driverQuery = ref('');

const form = useForm({
    driver_name: '',
    cmr_number: '',
    cars_count: 1,
    type: 'freight',
    payment_date: new Date().toISOString().slice(0, 10),
    amount: '',
    attachment: null,
});
const fileKey = ref(0);

const typeOptions = computed(() => [
    { value: 'freight', label: t('land_trips.driver_type_freight') },
    { value: 'commission', label: t('land_trips.driver_type_commission') },
    { value: 'other', label: t('land_trips.driver_type_other') },
]);

const filteredDriverNames = computed(() => {
    const q = driverQuery.value.trim().toLocaleLowerCase();
    const names = (props.driverNames || []).filter(Boolean);
    if (!q) {
        return names.slice(0, 12);
    }

    return names.filter((name) => String(name).toLocaleLowerCase().includes(q)).slice(0, 12);
});

watch(
    () => props.show,
    async (open) => {
        if (!open) {
            driverMenuOpen.value = false;
            return;
        }

        form.clearErrors();
        form.reset();
        form.cars_count = 1;
        form.type = 'freight';
        form.payment_date = new Date().toISOString().slice(0, 10);
        driverQuery.value = '';
        fileKey.value += 1;
        await nextTick();
        document.getElementById('land-driver-name')?.focus();
    },
);

const pickDriver = (name) => {
    form.driver_name = name;
    driverQuery.value = name;
    driverMenuOpen.value = false;
};

const submit = async () => {
    const ok = await requireActionPin(t('action_pin.message_driver_payment'));
    if (!ok) {
        return;
    }

    form.post(route('land-trips.companies.driver-payments.store', props.companyId), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        only: ['wallet', 'flash', 'errors'],
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <div
        v-if="show"
        class="erp-modal-backdrop"
        @click.self="emit('close')"
        @keydown.escape.prevent="emit('close')"
    >
        <div
            class="erp-modal-dialog erp-card p-0 overflow-hidden"
            style="width: min(560px, 100%)"
            role="dialog"
            aria-modal="true"
            :aria-label="t('land_trips.driver_account')"
        >
            <div class="flex items-start justify-between gap-3 p-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h3 class="h5 erp-display mb-1">{{ t('land_trips.driver_account') }}</h3>
                    <p class="small text-secondary mb-0">{{ t('land_trips.driver_account_help') }}</p>
                </div>
                <button type="button" :class="fbGhostButton" class="cursor-pointer shrink-0" @click="emit('close')">
                    {{ t('common.cancel') }}
                </button>
            </div>

            <form class="p-4" @submit.prevent="submit">
                <p v-if="!cashAccount" class="mb-3 text-sm text-amber-800 rounded-lg bg-amber-50 p-3 dark:bg-gray-800 dark:text-amber-300">
                    {{ t('land_trips.wallet_cash_missing') }}
                </p>

                <div class="mb-3 relative">
                    <label :class="fbLabel" for="land-driver-name">{{ t('land_trips.driver_name') }}</label>
                    <input
                        id="land-driver-name"
                        v-model="form.driver_name"
                        type="text"
                        list="land-driver-name-list"
                        maxlength="180"
                        :class="fbInput"
                        autocomplete="off"
                        required
                        @focus="driverMenuOpen = true"
                        @input="driverQuery = form.driver_name; driverMenuOpen = true"
                    />
                    <datalist id="land-driver-name-list">
                        <option v-for="name in driverNames" :key="name" :value="name" />
                    </datalist>
                    <ul
                        v-if="driverMenuOpen && filteredDriverNames.length"
                        class="absolute z-20 mt-1 max-h-44 w-full overflow-auto rounded-lg border border-gray-200 bg-white text-sm shadow-lg dark:border-gray-600 dark:bg-gray-700"
                    >
                        <li
                            v-for="name in filteredDriverNames"
                            :key="name"
                            class="cursor-pointer px-3 py-2 text-gray-900 hover:bg-teal-50 dark:text-white dark:hover:bg-gray-600"
                            @mousedown.prevent="pickDriver(name)"
                        >
                            {{ name }}
                        </li>
                    </ul>
                    <InputError :message="form.errors.driver_name" />
                </div>

                <div class="mb-3">
                    <label :class="fbLabel" for="land-driver-cmr">{{ t('land_trips.cmr') }}</label>
                    <input id="land-driver-cmr" v-model="form.cmr_number" type="text" maxlength="80" :class="fbInput" autocomplete="off" />
                    <InputError :message="form.errors.cmr_number" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label :class="fbLabel" for="land-driver-cars">{{ t('land_trips.cars_count') }}</label>
                        <input
                            id="land-driver-cars"
                            v-model="form.cars_count"
                            type="number"
                            min="1"
                            step="1"
                            :class="fbInput"
                            required
                        />
                        <InputError :message="form.errors.cars_count" />
                    </div>
                    <div>
                        <label :class="fbLabel" for="land-driver-type">{{ t('common.type') }}</label>
                        <select id="land-driver-type" v-model="form.type" :class="fbInput" required>
                            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label :class="fbLabel" for="land-driver-date">{{ t('common.date') }}</label>
                        <input id="land-driver-date" v-model="form.payment_date" type="date" :class="fbInput" required />
                        <InputError :message="form.errors.payment_date" />
                    </div>
                    <div>
                        <label :class="fbLabel" for="land-driver-amount">{{ t('common.amount') }} (USD)</label>
                        <input
                            id="land-driver-amount"
                            v-model="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :class="fbInput"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                </div>

                <div class="mb-3">
                    <label :class="fbLabel" for="land-driver-file">{{ t('land_trips.attach_file') }}</label>
                    <input
                        :key="fileKey"
                        id="land-driver-file"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        :class="[fbInput, 'erp-file-input']"
                        @change="form.attachment = $event.target.files?.[0] ?? null"
                    />
                    <p class="mt-1 mb-0 text-xs text-gray-500 dark:text-gray-400">{{ t('land_trips.attach_file_help') }}</p>
                    <InputError :message="form.errors.attachment" />
                </div>

                <div class="mb-1">
                    <label :class="fbLabel" for="land-driver-cash">{{ t('land_trips.cash_account') }}</label>
                    <input
                        id="land-driver-cash"
                        type="text"
                        :class="fbInput"
                        :value="cashAccount?.name || cashAccount?.label || '—'"
                        readonly
                    />
                    <InputError :message="form.errors.cash_account_id" />
                </div>
            </form>

            <div class="flex flex-wrap items-center justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" :class="fbGhostButton" class="cursor-pointer" @click="emit('close')">
                    {{ t('common.cancel') }}
                </button>
                <button type="button" :class="fbButton" class="cursor-pointer !w-auto" :disabled="form.processing" @click="submit">
                    {{ form.processing ? t('common.posting') : t('land_trips.driver_payment_submit') }}
                </button>
            </div>
        </div>
    </div>
</template>
