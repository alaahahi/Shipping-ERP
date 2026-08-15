<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section class="erp-card p-4 h-100">
        <header class="mb-4">
            <h2 class="h5 erp-display mb-1">{{ t('profile.password_title') }}</h2>
            <p class="text-secondary small mb-0">{{ t('profile.password_help') }}</p>
        </header>

        <form class="d-grid gap-3" @submit.prevent="updatePassword">
            <div>
                <label for="current_password" class="form-erp-label">{{ t('profile.current_password') }}</label>
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="form-control form-erp-control"
                    autocomplete="current-password"
                >
                <InputError :message="form.errors.current_password" />
            </div>

            <div>
                <label for="password" class="form-erp-label">{{ t('users.new_password') }}</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="form-control form-erp-control"
                    autocomplete="new-password"
                >
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="form-erp-label">{{ t('auth.confirm_password') }}</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="form-control form-erp-control"
                    autocomplete="new-password"
                >
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3 mt-1">
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('common.save') }}
                </button>
                <Transition
                    enter-active-class="transition-opacity"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity"
                    leave-to-class="opacity-0"
                >
                    <span v-if="form.recentlySuccessful" class="small text-success">{{ t('profile.saved') }}</span>
                </Transition>
            </div>
        </form>
    </section>
</template>
