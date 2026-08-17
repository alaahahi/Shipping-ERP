<script setup>
import InputError from '@/Components/InputError.vue';
import { fbDangerButton, fbGhostButton, fbInput, fbLabel, fbSuccessButton } from '@/flowbite';
import { useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: { type: Boolean, default: false },
    type: { type: String, default: 'receipt' },
    accountId: { type: Number, required: true },
    counterpartAccounts: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);
const { t } = useI18n();
const fileKey = ref(0);
const accountQuery = ref('');
const accountMenuOpen = ref(false);
const accountComboRoot = ref(null);

const form = useForm({
    type: 'receipt',
    counterpart_account_id: '',
    amount: '',
    entry_date: new Date().toISOString().slice(0, 10),
    description: '',
    attachment: null,
});

const title = computed(() => (props.type === 'payment' ? t('accounts.payment') : t('accounts.receipt')));
const help = computed(() => (props.type === 'payment' ? t('accounts.payment_help') : t('accounts.receipt_help')));

const selectedCounterpart = computed(
    () =>
        props.counterpartAccounts.find(
            (item) => String(item.id) === String(form.counterpart_account_id)
        ) ?? null
);

const filteredCounterpartAccounts = computed(() => {
    const q = accountQuery.value.trim().toLocaleLowerCase();
    if (!q) {
        return props.counterpartAccounts;
    }

    return props.counterpartAccounts.filter((item) => {
        const hay = `${item.code ?? ''} ${item.name ?? ''} ${item.label ?? ''}`.toLocaleLowerCase();
        return hay.includes(q);
    });
});

const accountInputValue = computed({
    get() {
        if (accountMenuOpen.value) {
            return accountQuery.value;
        }

        return selectedCounterpart.value?.label ?? accountQuery.value;
    },
    set(value) {
        accountQuery.value = value;
        accountMenuOpen.value = true;
    },
});

watch(
    () => [props.show, props.type],
    ([open]) => {
        if (!open) {
            accountMenuOpen.value = false;
            return;
        }

        form.clearErrors();
        form.reset();
        form.type = props.type === 'payment' ? 'payment' : 'receipt';
        form.entry_date = new Date().toISOString().slice(0, 10);
        fileKey.value += 1;
        accountQuery.value = '';
        accountMenuOpen.value = false;
    }
);

const onDocPointerDown = (event) => {
    if (!accountMenuOpen.value || !accountComboRoot.value) {
        return;
    }

    if (!accountComboRoot.value.contains(event.target)) {
        accountMenuOpen.value = false;
        accountQuery.value = '';
    }
};

watch(
    () => props.show,
    (open) => {
        if (open) {
            document.addEventListener('pointerdown', onDocPointerDown);
            return;
        }

        document.removeEventListener('pointerdown', onDocPointerDown);
    }
);

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocPointerDown);
});

const openAccountMenu = () => {
    accountMenuOpen.value = true;
    accountQuery.value = '';
};

const selectCounterpart = (item) => {
    form.counterpart_account_id = item.id;
    form.clearErrors('counterpart_account_id');
    accountQuery.value = '';
    accountMenuOpen.value = false;
};

const clearCounterpart = () => {
    form.counterpart_account_id = '';
    accountQuery.value = '';
    accountMenuOpen.value = true;
};

const onFile = (event) => {
    form.attachment = event.target.files?.[0] ?? null;
};

const submit = () => {
    if (!form.counterpart_account_id) {
        form.setError('counterpart_account_id', t('accounts.select_counterpart'));
        accountMenuOpen.value = true;
        return;
    }

    form.post(route('accounts.movements.store', props.accountId), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <div v-if="show" class="erp-modal-backdrop" @click.self="emit('close')">
        <div
            class="erp-modal-dialog erp-card p-0 overflow-hidden"
            style="width: min(560px, 100%)"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
        >
            <div class="d-flex justify-content-between align-items-start gap-3 p-3 border-bottom">
                <div>
                    <h3 class="h5 erp-display mb-1">{{ title }}</h3>
                    <p class="small text-secondary mb-0">{{ help }}</p>
                </div>
                <button type="button" :class="fbGhostButton" @click="emit('close')">
                    {{ t('common.cancel') }}
                </button>
            </div>

            <form class="p-3" @submit.prevent="submit">
                <div class="mb-3">
                    <label :class="fbLabel" for="movement-account">{{ t('accounts.counterpart') }}</label>
                    <div ref="accountComboRoot" class="relative">
                        <div class="relative">
                            <input
                                id="movement-account"
                                v-model="accountInputValue"
                                type="search"
                                autocomplete="off"
                                :class="fbInput"
                                class="pe-10"
                                :placeholder="t('accounts.search_placeholder')"
                                :aria-expanded="accountMenuOpen"
                                aria-autocomplete="list"
                                aria-controls="movement-account-list"
                                role="combobox"
                                @focus="openAccountMenu"
                                @keydown.escape.prevent="accountMenuOpen = false"
                            />
                            <button
                                v-if="form.counterpart_account_id"
                                type="button"
                                class="absolute inset-y-0 end-0 flex items-center pe-3 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"
                                :aria-label="t('common.cancel')"
                                @click="clearCounterpart"
                            >
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <ul
                            v-if="accountMenuOpen"
                            id="movement-account-list"
                            role="listbox"
                            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-300 bg-white text-sm shadow-lg dark:border-gray-600 dark:bg-gray-700"
                        >
                            <li
                                v-if="filteredCounterpartAccounts.length === 0"
                                class="px-3 py-2 text-gray-500 dark:text-gray-400"
                            >
                                {{ t('common.no_results') }}
                            </li>
                            <li
                                v-for="item in filteredCounterpartAccounts"
                                :key="item.id"
                                role="option"
                                :aria-selected="String(form.counterpart_account_id) === String(item.id)"
                                class="cursor-pointer px-3 py-2 text-gray-900 hover:bg-teal-50 dark:text-white dark:hover:bg-teal-900/40"
                                :class="{
                                    'bg-teal-50 dark:bg-teal-900/40':
                                        String(form.counterpart_account_id) === String(item.id),
                                }"
                                @mousedown.prevent="selectCounterpart(item)"
                            >
                                {{ item.label }}
                            </li>
                        </ul>
                    </div>
                    <InputError :message="form.errors.counterpart_account_id" />
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label :class="fbLabel" for="movement-amount">{{ t('common.amount') }}</label>
                        <input
                            id="movement-amount"
                            v-model="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :class="fbInput"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                    <div>
                        <label :class="fbLabel" for="movement-date">{{ t('common.date') }}</label>
                        <input id="movement-date" v-model="form.entry_date" type="date" :class="fbInput" required />
                        <InputError :message="form.errors.entry_date" />
                    </div>
                </div>
                <div class="mt-3 mb-3">
                    <label :class="fbLabel" for="movement-notes">{{ t('common.notes') }}</label>
                    <input id="movement-notes" v-model="form.description" type="text" maxlength="255" :class="fbInput" />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="mb-4">
                    <label :class="fbLabel" for="movement-file">{{ t('accounts.attach_image') }}</label>
                    <input
                        :key="fileKey"
                        id="movement-file"
                        type="file"
                        accept="image/*"
                        :class="fbInput"
                        @change="onFile"
                    />
                    <InputError :message="form.errors.attachment" />
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" :class="fbGhostButton" @click="emit('close')">
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :class="type === 'payment' ? fbDangerButton : fbSuccessButton"
                        class="!w-auto min-w-40"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? t('common.saving') : t('accounts.post_movement') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
