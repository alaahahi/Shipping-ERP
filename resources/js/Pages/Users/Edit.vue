<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role ?? 'viewer',
});

const submit = () => form.put(route('users.update', props.user.id));
</script>

<template>
    <Head :title="`${t('users.edit_title')} ${user.name}`" />
    <AppLayout>
        <template #header>{{ t('users.edit_title') }}</template>

        <div class="mb-3">
            <Link :href="route('users.index')" class="text-decoration-none small fw-semibold">← {{ t('users.back') }}</Link>
        </div>

        <form class="erp-card p-4 col-lg-8 col-xl-6" @submit.prevent="submit">
            <div class="mb-4">
                <h2 class="h5 erp-display mb-1">{{ user.name }}</h2>
                <p class="text-secondary small mb-0">{{ t('users.password_optional') }}</p>
            </div>

            <div class="d-grid gap-3">
                <div>
                    <label for="name" class="form-erp-label">{{ t('auth.full_name') }}</label>
                    <input id="name" v-model="form.name" type="text" class="form-control form-erp-control" required autofocus />
                    <InputError :message="form.errors.name" />
                </div>
                <div>
                    <label for="email" class="form-erp-label">{{ t('auth.email') }}</label>
                    <input id="email" v-model="form.email" type="email" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.email" />
                </div>
                <div>
                    <label for="role" class="form-erp-label">{{ t('common.role') }}</label>
                    <select id="role" v-model="form.role" class="form-select form-erp-control" required>
                        <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>
                <div>
                    <label for="password" class="form-erp-label">{{ t('users.new_password') }}</label>
                    <input id="password" v-model="form.password" type="password" class="form-control form-erp-control" autocomplete="new-password" />
                    <InputError :message="form.errors.password" />
                </div>
                <div>
                    <label for="password_confirmation" class="form-erp-label">{{ t('auth.confirm_password') }}</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="form-control form-erp-control" autocomplete="new-password" />
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('users.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('users.save_changes') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
