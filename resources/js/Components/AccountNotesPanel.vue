<script setup>
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import { fbButton, fbDangerButton, fbGhostButton, fbInput, fbLabel } from '@/flowbite';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    accountId: { type: Number, required: true },
    notes: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    defaultDate: { type: String, default: '' },
});

const { t } = useI18n();
const editingId = ref(null);

const createForm = useForm({
    body: '',
    note_date: props.defaultDate,
});

const editForm = useForm({
    body: '',
    note_date: '',
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

const startEdit = (note) => {
    editingId.value = note.id;
    editForm.body = note.body;
    editForm.note_date = note.note_date || props.defaultDate;
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const saveEdit = (note) => {
    editForm.put(route('accounts.notes.update', [props.accountId, note.id]), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            editForm.reset();
        },
    });
};

const deleteNote = (note) => {
    if (!window.confirm(t('accounts.note_delete_confirm'))) {
        return;
    }

    router.delete(route('accounts.notes.destroy', [props.accountId, note.id]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="erp-card p-4 mb-3" v-if="canManage">
        <form class="flex flex-col gap-3" @submit.prevent="addNote">
            <div>
                <label :class="fbLabel" for="account-note-date">{{ t('common.date') }}</label>
                <input
                    id="account-note-date"
                    v-model="createForm.note_date"
                    type="date"
                    :class="[fbInput, 'sm:w-48']"
                />
                <InputError :message="createForm.errors.note_date" />
            </div>
            <div>
                <label :class="fbLabel" for="account-note-body">{{ t('accounts.add_note') }}</label>
                <textarea
                    id="account-note-body"
                    v-model="createForm.body"
                    :class="fbInput"
                    rows="3"
                    maxlength="5000"
                    :placeholder="t('accounts.note_placeholder')"
                    required
                />
                <InputError :message="createForm.errors.body" />
            </div>
            <div class="flex justify-end">
                <button type="submit" :class="[fbButton, '!w-auto']" :disabled="createForm.processing">
                    {{ createForm.processing ? t('common.saving') : t('accounts.add_note') }}
                </button>
            </div>
        </form>
    </div>

    <div class="erp-card p-0 overflow-hidden">
        <div v-if="!notes.data?.length" class="p-4">
            <EmptyState icon="N">{{ t('accounts.notes_none') }}</EmptyState>
        </div>
        <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
            <li v-for="note in notes.data" :key="note.id" class="p-4">
                <div v-if="editingId === note.id" class="flex flex-col gap-3">
                    <input
                        v-model="editForm.note_date"
                        type="date"
                        :class="[fbInput, 'sm:w-48']"
                    />
                    <InputError :message="editForm.errors.note_date" />
                    <textarea
                        v-model="editForm.body"
                        :class="fbInput"
                        rows="3"
                        maxlength="5000"
                    />
                    <InputError :message="editForm.errors.body" />
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :class="[fbButton, '!w-auto']"
                            :disabled="editForm.processing"
                            @click="saveEdit(note)"
                        >
                            {{ editForm.processing ? t('common.saving') : t('common.save') }}
                        </button>
                        <button type="button" :class="fbGhostButton" @click="cancelEdit">
                            {{ t('common.cancel') }}
                        </button>
                    </div>
                </div>
                <div v-else>
                    <p class="mb-1 text-xs font-semibold text-teal-700 dark:text-teal-400">{{ note.note_date }}</p>
                    <p class="whitespace-pre-wrap text-sm text-gray-900 dark:text-white">{{ note.body }}</p>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <p class="mb-0 text-xs text-gray-500 dark:text-gray-400">
                            {{ note.created_by_name || '—' }}
                            · {{ note.created_at_label }}
                            <span v-if="note.updated_at_label">
                                · {{ t('accounts.note_edited') }} {{ note.updated_at_label }}
                            </span>
                        </p>
                        <div v-if="canManage" class="flex gap-1">
                            <button
                                type="button"
                                :class="[fbGhostButton, '!px-2 !py-1 text-xs']"
                                @click="startEdit(note)"
                            >
                                {{ t('common.edit') }}
                            </button>
                            <button
                                type="button"
                                :class="[fbDangerButton, '!w-auto !px-2 !py-1 text-xs']"
                                @click="deleteNote(note)"
                            >
                                {{ t('common.delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <div
            v-if="notes.prev_page_url || notes.next_page_url"
            class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 p-3 dark:border-gray-700"
        >
            <Link
                v-if="notes.prev_page_url"
                :href="notes.prev_page_url"
                :class="fbGhostButton"
                preserve-scroll
            >
                {{ t('common.prev') }}
            </Link>
            <span v-else />
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ notes.from }}–{{ notes.to }} / {{ notes.total }}
            </span>
            <Link
                v-if="notes.next_page_url"
                :href="notes.next_page_url"
                :class="fbGhostButton"
                preserve-scroll
            >
                {{ t('common.next') }}
            </Link>
            <span v-else />
        </div>
    </div>
</template>
