<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    options: {
        type: Object,
        required: true,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);

const form = useForm({
    company: {
        name: props.settings.company.name,
        email: props.settings.company.email,
        phone: props.settings.company.phone,
        address: props.settings.company.address,
    },
    app: {
        timezone: props.settings.app.timezone,
        locale: props.settings.app.locale || 'ar',
        currency: props.settings.app.currency,
    },
    whatsapp: {
        tenant_id: props.settings.whatsapp.tenant_id || 'kaml-kamal',
        enabled: props.settings.whatsapp.enabled === '1' || props.settings.whatsapp.enabled === true,
    },
});

const submit = () => {
    form.put(route('settings.update'));
};
</script>

<template>
    <Head :title="t('settings.title')" />

    <AppLayout>
        <template #header>{{ t('settings.title') }}</template>

        <div v-if="success" class="alert alert-success border-0 shadow-sm mb-3" role="status">
            {{ success }}
        </div>

        <form class="d-grid gap-3" @submit.prevent="submit">
            <div class="erp-card p-4">
                <h2 class="h5 erp-display mb-1">{{ t('settings.company_profile') }}</h2>
                <p class="text-secondary small mb-4">{{ t('settings.company_help') }}</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="company_name" class="form-erp-label">{{ t('settings.company_name') }}</label>
                        <input
                            id="company_name"
                            v-model="form.company.name"
                            type="text"
                            class="form-control form-erp-control"
                            :disabled="!canManage"
                            required
                        />
                        <InputError :message="form.errors['company.name']" />
                    </div>

                    <div class="col-md-6">
                        <label for="company_email" class="form-erp-label">{{ t('settings.company_email') }}</label>
                        <input
                            id="company_email"
                            v-model="form.company.email"
                            type="email"
                            class="form-control form-erp-control"
                            :disabled="!canManage"
                        />
                        <InputError :message="form.errors['company.email']" />
                    </div>

                    <div class="col-md-6">
                        <label for="company_phone" class="form-erp-label">{{ t('settings.phone') }}</label>
                        <input
                            id="company_phone"
                            v-model="form.company.phone"
                            type="text"
                            class="form-control form-erp-control"
                            :disabled="!canManage"
                        />
                        <InputError :message="form.errors['company.phone']" />
                    </div>

                    <div class="col-md-6">
                        <label for="company_address" class="form-erp-label">{{ t('settings.address') }}</label>
                        <input
                            id="company_address"
                            v-model="form.company.address"
                            type="text"
                            class="form-control form-erp-control"
                            :disabled="!canManage"
                        />
                        <InputError :message="form.errors['company.address']" />
                    </div>
                </div>
            </div>

            <div class="erp-card p-4">
                <h2 class="h5 erp-display mb-1">{{ t('settings.localization') }}</h2>
                <p class="text-secondary small mb-4">{{ t('settings.localization_help') }}</p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="app_timezone" class="form-erp-label">{{ t('settings.timezone') }}</label>
                        <select
                            id="app_timezone"
                            v-model="form.app.timezone"
                            class="form-select form-erp-control"
                            :disabled="!canManage"
                            required
                        >
                            <option
                                v-for="timezone in options.timezones"
                                :key="timezone.value"
                                :value="timezone.value"
                            >
                                {{ timezone.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors['app.timezone']" />
                    </div>

                    <div class="col-md-4">
                        <label for="app_locale" class="form-erp-label">{{ t('settings.locale') }}</label>
                        <select
                            id="app_locale"
                            v-model="form.app.locale"
                            class="form-select form-erp-control"
                            :disabled="!canManage"
                            required
                        >
                            <option
                                v-for="locale in options.locales"
                                :key="locale.value"
                                :value="locale.value"
                            >
                                {{ locale.label }}
                            </option>
                        </select>
                        <p class="small text-secondary mt-1 mb-0">
                            العربية · English · کوردی سۆرانی
                        </p>
                        <InputError :message="form.errors['app.locale']" />
                    </div>

                    <div class="col-md-4">
                        <label for="app_currency" class="form-erp-label">{{ t('common.currency') }}</label>
                        <select
                            id="app_currency"
                            v-model="form.app.currency"
                            class="form-select form-erp-control"
                            :disabled="!canManage"
                            required
                        >
                            <option
                                v-for="currency in options.currencies"
                                :key="currency.value"
                                :value="currency.value"
                            >
                                {{ currency.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors['app.currency']" />
                    </div>
                </div>
            </div>

            <div class="erp-card p-4">
                <h2 class="h5 erp-display mb-1">{{ t('settings.whatsapp') }}</h2>
                <p class="text-secondary small mb-4">{{ t('settings.whatsapp_help') }}</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="whatsapp_tenant_id" class="form-erp-label">{{ t('settings.whatsapp_tenant_id') }}</label>
                        <input
                            id="whatsapp_tenant_id"
                            v-model="form.whatsapp.tenant_id"
                            type="text"
                            class="form-control form-erp-control"
                            :disabled="!canManage"
                            required
                        />
                        <InputError :message="form.errors['whatsapp.tenant_id']" />
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input
                                id="whatsapp_enabled"
                                v-model="form.whatsapp.enabled"
                                class="form-check-input"
                                type="checkbox"
                                :disabled="!canManage"
                            />
                            <label class="form-check-label" for="whatsapp_enabled">{{ t('settings.whatsapp_enabled') }}</label>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="canManage" class="d-flex justify-content-end">
                <button type="submit" class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('settings.save_settings') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
