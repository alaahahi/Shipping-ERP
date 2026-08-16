<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { fbButton, fbCheckbox, fbGhostButton, fbInput, fbLabel, fbLink } from '@/flowbite';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    types: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    parents: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    code: '',
    name: '',
    type: 'asset',
    currency: 'USD',
    parent_id: null,
    description: '',
    is_active: true,
    show_on_dashboard: false,
});

const submit = () => form.post(route('accounts.store'));
</script>

<template>
    <Head :title="t('accounts.add')" />
    <AppLayout>
        <template #header>{{ t('accounts.add') }}</template>
        <div class="mb-3">
            <Link :href="route('accounts.index')" :class="fbLink">← {{ t('accounts.back') }}</Link>
        </div>

        <form
            class="erp-card p-4 lg:max-w-3xl"
            @submit.prevent="submit"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="md:col-span-4">
                    <label :class="fbLabel">{{ t('accounts.code') }}</label>
                    <input v-model="form.code" :class="fbInput" required />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="md:col-span-8">
                    <label :class="fbLabel">{{ t('common.name') }}</label>
                    <input v-model="form.name" :class="fbInput" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="md:col-span-4">
                    <label :class="fbLabel">{{ t('accounts.type') }}</label>
                    <select v-model="form.type" :class="fbInput" required>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                    <InputError :message="form.errors.type" />
                </div>
                <div class="md:col-span-4">
                    <label :class="fbLabel">{{ t('common.currency') }}</label>
                    <select v-model="form.currency" :class="fbInput" required>
                        <option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                    </select>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ t('accounts.aed_hint') }}</p>
                    <InputError :message="form.errors.currency" />
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
                    <InputError :message="form.errors.description" />
                </div>
                <div class="md:col-span-12">
                    <div class="flex items-start gap-2">
                        <input id="show_on_dashboard" v-model="form.show_on_dashboard" :class="fbCheckbox" type="checkbox" />
                        <div>
                            <label for="show_on_dashboard" class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ t('accounts.show_on_dashboard') }}
                            </label>
                            <p class="mb-0 text-sm text-gray-600 dark:text-gray-300">{{ t('accounts.show_on_dashboard_help') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <Link :href="route('accounts.index')" :class="fbGhostButton">{{ t('common.cancel') }}</Link>
                <button :class="[fbButton, '!w-auto']" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('accounts.create_account') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
