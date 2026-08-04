<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    role: { type: Object, required: true },
    permissionGroups: { type: Object, required: true },
});

const { t } = useI18n();

const form = useForm({
    permissions: [...props.role.permissions],
});

const groups = computed(() => Object.entries(props.permissionGroups));

const toggle = (permissionName) => {
    if (form.permissions.includes(permissionName)) {
        form.permissions = form.permissions.filter((item) => item !== permissionName);
        return;
    }
    form.permissions = [...form.permissions, permissionName];
};

const isChecked = (permissionName) => form.permissions.includes(permissionName);

const submit = () => form.put(route('roles.update', props.role.id));
</script>

<template>
    <Head :title="t('roles.edit_role', { name: role.name })" />
    <AppLayout>
        <template #header>{{ t('roles.edit_role', { name: role.name }) }}</template>

        <div class="mb-3">
            <Link :href="route('roles.index')" class="text-decoration-none small fw-semibold">← {{ t('roles.back') }}</Link>
        </div>

        <form class="erp-card p-4" @submit.prevent="submit">
            <div class="mb-4">
                <h2 class="h5 erp-display mb-1 text-capitalize">{{ role.name }}</h2>
                <p class="text-secondary small mb-0">{{ t('roles.select_permissions') }}</p>
            </div>

            <InputError :message="form.errors.permissions" />

            <div class="row g-3 mb-4">
                <div v-for="[group, permissions] in groups" :key="group" class="col-md-6 col-xl-4">
                    <div class="border rounded-3 p-3 h-100" style="border-color: var(--erp-border) !important">
                        <h3 class="h6 erp-display text-capitalize mb-3">{{ group }}</h3>
                        <div class="d-grid gap-2">
                            <label
                                v-for="permission in permissions"
                                :key="permission.name"
                                class="form-check form-check-erp d-flex align-items-center gap-2 mb-0"
                            >
                                <input
                                    class="form-check-input mt-0"
                                    type="checkbox"
                                    :checked="isChecked(permission.name)"
                                    @change="toggle(permission.name)"
                                />
                                <span class="small">{{ permission.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <Link :href="route('roles.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('roles.save_permissions') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
