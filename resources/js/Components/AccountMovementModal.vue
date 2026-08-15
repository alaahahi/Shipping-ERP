<script setup>
import InputError from '@/Components/InputError.vue';
import { fbDangerButton, fbGhostButton, fbInput, fbLabel, fbSuccessButton } from '@/flowbite';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

watch(
    () => [props.show, props.type],
    ([open]) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.reset();
        form.type = props.type === 'payment' ? 'payment' : 'receipt';
        form.entry_date = new Date().toISOString().slice(0, 10);
        fileKey.value += 1;
    }
);

const onFile = (event) => {
    form.attachment = event.target.files?.[0] ?? null;
};

const submit = () => {
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
                    <select
                        id="movement-account"
                        v-model="form.counterpart_account_id"
                        :class="fbInput"
                        required
                    >
                        <option value="">{{ t('accounts.select_counterpart') }}</option>
                        <option v-for="item in counterpartAccounts" :key="item.id" :value="item.id">
                            {{ item.label }}
                        </option>
                    </select>
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
