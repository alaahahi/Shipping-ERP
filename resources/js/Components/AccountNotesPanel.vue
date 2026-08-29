<script setup>
import InputError from '@/Components/InputError.vue';
import { fbButton, fbInput, fbLabel } from '@/flowbite';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    accountId: { type: Number, required: true },
    defaultDate: { type: String, default: '' },
});

const { t } = useI18n();

const createForm = useForm({
    body: '',
    note_date: props.defaultDate,
});

const addNote = () => {
    createForm.post(route('accounts.notes.store', props.accountId), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.note_date = props.defaultDate;
        },
    });
};
</script>

<template>
    <div class="mb-3 rounded-lg border border-teal-200 bg-teal-50/70 p-3 dark:border-teal-900 dark:bg-teal-950/40">
        <form class="flex flex-col gap-2 lg:flex-row lg:items-end" @submit.prevent="addNote">
            <div class="lg:w-44">
                <label :class="fbLabel" for="account-note-date">{{ t('common.date') }}</label>
                <input
                    id="account-note-date"
                    v-model="createForm.note_date"
                    type="date"
                    :class="fbInput"
                />
                <InputError :message="createForm.errors.note_date" />
            </div>
            <div class="min-w-0 flex-1">
                <label :class="fbLabel" for="account-note-body">{{ t('accounts.add_note') }}</label>
                <textarea
                    id="account-note-body"
                    v-model="createForm.body"
                    :class="fbInput"
                    rows="2"
                    maxlength="5000"
                    :placeholder="t('accounts.note_placeholder')"
                    required
                />
                <InputError :message="createForm.errors.body" />
            </div>
            <button type="submit" :class="[fbButton, '!w-auto lg:mb-0.5']" :disabled="createForm.processing">
                {{ createForm.processing ? t('common.saving') : t('accounts.add_note') }}
            </button>
        </form>
    </div>
</template>
