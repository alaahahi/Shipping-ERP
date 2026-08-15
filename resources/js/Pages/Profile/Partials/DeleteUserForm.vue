<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <section class="erp-card erp-profile-danger p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="h5 erp-display mb-1">{{ t('profile.danger_title') }}</h2>
                <p class="text-secondary small mb-0">{{ t('profile.danger_help') }}</p>
            </div>
            <button type="button" class="btn btn-outline-danger" @click="confirmUserDeletion">
                {{ t('profile.delete_account') }}
            </button>
        </div>
    </section>

    <div
        v-if="confirmingUserDeletion"
        class="erp-modal-backdrop"
        role="presentation"
        @click.self="closeModal"
    >
        <div class="erp-modal-dialog is-narrow erp-card p-4" role="dialog" aria-modal="true" aria-labelledby="profile-delete-title">
            <h3 id="profile-delete-title" class="h5 erp-display mb-2">{{ t('profile.delete_confirm_title') }}</h3>
            <p class="text-secondary small mb-3">{{ t('profile.delete_confirm_text') }}</p>

            <label for="delete_password" class="form-erp-label">{{ t('auth.password') }}</label>
            <input
                id="delete_password"
                ref="passwordInput"
                v-model="form.password"
                type="password"
                class="form-control form-erp-control"
                autocomplete="current-password"
                @keyup.enter="deleteUser"
            >
            <InputError :message="form.errors.password" />

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-erp-ghost" @click="closeModal">{{ t('common.cancel') }}</button>
                <button
                    type="button"
                    class="btn btn-outline-danger"
                    :disabled="form.processing"
                    @click="deleteUser"
                >
                    {{ t('profile.delete_account') }}
                </button>
            </div>
        </div>
    </div>
</template>
