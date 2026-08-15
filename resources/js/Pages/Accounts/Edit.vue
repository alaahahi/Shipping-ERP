<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { fbButton, fbCheckbox, fbGhostButton, fbInput, fbLabel, fbLink } from '@/flowbite';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    account: { type: Object, required: true },
    types: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    code: props.account.code,
    name: props.account.name,
    type: props.account.type,
    currency: props.account.currency,
    parent_id: props.account.parent_id,
    description: props.account.description ?? '',
    is_active: props.account.is_active,
    show_on_dashboard: props.account.show_on_dashboard,
});

const submit = () => form.put(route('accounts.update', props.account.id));
</script>

<template>
    <Head :title="`${t('accounts.edit_account')} ${account.code}`" />
    <AppLayout>
        <template #header>{{ t('accounts.edit_account') }}</template>
        <div class="mb-3">
            <Link :href="route('accounts.index')" :class="fbLink">← {{ t('accounts.back') }}</Link>
        </div>

        <form
            class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 lg:max-w-3xl"
            @submit.prevent="submit"
        >
            <div
                v-if="account.is_system"
                class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-300"
            >
                {{ t('accounts.system_locked') }}
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="md:col-span-4">
                    <label :class="fbLabel">{{ t('accounts.code') }}</label>
                    <input v-model="form.code" :class="fbInput" :disabled="account.is_system" required />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="md:col-span-8">
                    <label :class="fbLabel">{{ t('common.name') }}</label>
                    <input v-model="form.name" :class="fbInput" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="md:col-span-4">
                    <label :class="fbLabel">{{ t('accounts.type') }}</label>
                    <select v-model="form.type" :class="fbInput" :disabled="account.is_system" required>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label :class="fbLabel">{{ t('common.currency') }}</label>
                    <select v-model="form.currency" :class="fbInput" :disabled="account.is_system" required>
                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label :class="fbLabel">{{ t('accounts.parent') }}</label>
                    <select v-model="form.parent_id" :class="fbInput">
                        <option :value="null">{{ t('accounts.none_parent') }}</option>
                        <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.label }}</option>
                    </select>
                    <InputError :message="form.errors.parent_id" />
                </div>
                <div class="md:col-span-12">
                    <label :class="fbLabel">{{ t('accounts.description') }}</label>
                    <textarea v-model="form.description" :class="fbInput" rows="3" />
                </div>
                <div class="md:col-span-12 space-y-3">
                    <div class="flex items-center gap-2">
                        <input id="is_active" v-model="form.is_active" :class="fbCheckbox" type="checkbox" />
                        <label for="is_active" class="text-sm font-medium text-gray-900 dark:text-white">{{ t('common.active') }}</label>
                    </div>
                    <div class="flex items-start gap-2">
                        <input id="show_on_dashboard" v-model="form.show_on_dashboard" :class="fbCheckbox" type="checkbox" />
                        <div>
                            <label for="show_on_dashboard" class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ t('accounts.show_on_dashboard') }}
                            </label>
                            <p class="mb-0 text-sm text-gray-500 dark:text-gray-400">{{ t('accounts.show_on_dashboard_help') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <Link :href="route('accounts.index')" :class="fbGhostButton">{{ t('common.cancel') }}</Link>
                <button :class="[fbButton, '!w-auto']" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('users.save_changes') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
