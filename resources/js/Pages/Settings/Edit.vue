<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    tab: { type: String, default: 'company' },
    settings: { type: Object, required: true },
    options: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    canManageUsers: { type: Boolean, default: false },
    canViewUsers: { type: Boolean, default: false },
    countries: { type: Array, default: () => [] },
    users: { type: Object, default: null },
    userFilters: { type: Object, default: () => ({ search: '', role: '' }) },
    roles: { type: Array, default: () => [] },
    logs: { type: Object, default: null },
    logLevel: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
const migrateOutput = computed(() => page.props.flash?.migrate_output);
const currentUserId = computed(() => page.props.auth?.user?.id);
const migrating = ref(false);
const clearingLogs = ref(false);
const editingCountryId = ref(null);

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
        tenant_id: props.settings.whatsapp?.tenant_id || 'kaml-kamal',
        enabled: props.settings.whatsapp?.enabled === '1' || props.settings.whatsapp?.enabled === true,
    },
});

const countryForm = useForm({
    name: '',
    name_ar: '',
    iso_code: '',
    is_active: true,
    sort_order: 0,
});

const userFilterForm = useForm({
    tab: 'users',
    search: props.userFilters.search ?? '',
    role: props.userFilters.role ?? '',
});

const goTab = (tab) => {
    router.get(route('settings.edit'), { tab }, { preserveState: false, preserveScroll: true });
};

const submit = () => form.put(route('settings.update'));

const startEditCountry = (country) => {
    editingCountryId.value = country.id;
    countryForm.name = country.name;
    countryForm.name_ar = country.name_ar;
    countryForm.iso_code = country.iso_code ?? '';
    countryForm.is_active = country.is_active;
    countryForm.sort_order = country.sort_order;
    countryForm.clearErrors();
};

const resetCountryForm = () => {
    editingCountryId.value = null;
    countryForm.reset();
    countryForm.is_active = true;
    countryForm.sort_order = 0;
};

const saveCountry = () => {
    if (editingCountryId.value) {
        countryForm.put(route('settings.countries.update', editingCountryId.value), {
            preserveScroll: true,
            onSuccess: () => resetCountryForm(),
        });
        return;
    }

    countryForm.post(route('settings.countries.store'), {
        preserveScroll: true,
        onSuccess: () => resetCountryForm(),
    });
};

const deleteCountry = (country) => {
    if (!window.confirm(t('settings.delete_country_confirm', { name: country.name }))) return;
    router.delete(route('settings.countries.destroy', country.id), { preserveScroll: true });
};

const applyUserFilters = () => {
    userFilterForm.get(route('settings.edit'), { preserveState: true, replace: true });
};

const destroyUser = (user) => {
    if (!window.confirm(t('users.delete_confirm', { name: user.name }))) return;
    router.delete(route('users.destroy', user.id), {
        preserveScroll: true,
        onSuccess: () => goTab('users'),
    });
};

const runMigrate = () => {
    if (!window.confirm(t('settings.migrate_confirm'))) return;
    migrating.value = true;
    router.post(route('settings.system.migrate'), {}, {
        preserveScroll: true,
        onFinish: () => {
            migrating.value = false;
        },
    });
};

const filterLogs = (level) => {
    router.get(route('settings.edit'), { tab: 'system', level }, { preserveState: true, replace: true });
};

const clearLogs = () => {
    if (!window.confirm(t('settings.clear_log_confirm'))) return;
    clearingLogs.value = true;
    router.post(route('settings.system.logs.clear'), {}, {
        preserveScroll: true,
        onFinish: () => {
            clearingLogs.value = false;
        },
    });
};

const logClass = (level) => {
    if (['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'].includes(level)) return 'is-error';
    if (level === 'WARNING') return 'is-warning';
    return 'is-info';
};
</script>

<template>
    <Head :title="t('settings.title')" />

    <AppLayout>
        <template #header>{{ t('settings.title') }}</template>

        <div v-if="success" class="alert alert-success border-0 shadow-sm mb-3" role="status">{{ success }}</div>
        <div v-if="error" class="alert alert-danger border-0 shadow-sm mb-3" role="alert">{{ error }}</div>

        <nav class="erp-settings-tabs">
            <button type="button" class="btn" :class="tab === 'company' ? 'btn-erp' : 'btn-erp-ghost'" @click="goTab('company')">
                {{ t('settings.tab_company') }}
            </button>
            <button type="button" class="btn" :class="tab === 'countries' ? 'btn-erp' : 'btn-erp-ghost'" @click="goTab('countries')">
                {{ t('settings.tab_countries') }}
            </button>
            <button
                v-if="canViewUsers"
                type="button"
                class="btn"
                :class="tab === 'users' ? 'btn-erp' : 'btn-erp-ghost'"
                @click="goTab('users')"
            >
                {{ t('settings.tab_users') }}
            </button>
            <button
                v-if="canManage"
                type="button"
                class="btn"
                :class="tab === 'system' ? 'btn-erp' : 'btn-erp-ghost'"
                @click="goTab('system')"
            >
                {{ t('settings.tab_system') }}
            </button>
        </nav>

        <form v-if="tab === 'company'" class="d-grid gap-3" @submit.prevent="submit">
            <div class="erp-card p-4">
                <h2 class="h5 erp-display mb-1">{{ t('settings.company_profile') }}</h2>
                <p class="text-secondary small mb-4">{{ t('settings.company_help') }}</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-erp-label">{{ t('settings.company_name') }}</label>
                        <input v-model="form.company.name" class="form-control form-erp-control" :disabled="!canManage" required />
                        <InputError :message="form.errors['company.name']" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-erp-label">{{ t('settings.company_email') }}</label>
                        <input v-model="form.company.email" type="email" class="form-control form-erp-control" :disabled="!canManage" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-erp-label">{{ t('settings.phone') }}</label>
                        <input v-model="form.company.phone" class="form-control form-erp-control" :disabled="!canManage" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-erp-label">{{ t('settings.address') }}</label>
                        <input v-model="form.company.address" class="form-control form-erp-control" :disabled="!canManage" />
                    </div>
                </div>
            </div>

            <div class="erp-card p-4">
                <h2 class="h5 erp-display mb-1">{{ t('settings.localization') }}</h2>
                <p class="text-secondary small mb-4">{{ t('settings.localization_help') }}</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-erp-label">{{ t('settings.timezone') }}</label>
                        <select v-model="form.app.timezone" class="form-select form-erp-control" :disabled="!canManage" required>
                            <option v-for="timezone in options.timezones" :key="timezone.value" :value="timezone.value">{{ timezone.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-erp-label">{{ t('settings.locale') }}</label>
                        <select v-model="form.app.locale" class="form-select form-erp-control" :disabled="!canManage" required>
                            <option v-for="locale in options.locales" :key="locale.value" :value="locale.value">{{ locale.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-erp-label">{{ t('common.currency') }}</label>
                        <select v-model="form.app.currency" class="form-select form-erp-control" :disabled="!canManage" required>
                            <option v-for="currency in options.currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="erp-card p-4">
                <h2 class="h5 erp-display mb-1">{{ t('settings.whatsapp') }}</h2>
                <p class="text-secondary small mb-4">{{ t('settings.whatsapp_help') }}</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-erp-label">{{ t('settings.whatsapp_tenant_id') }}</label>
                        <input v-model="form.whatsapp.tenant_id" class="form-control form-erp-control" :disabled="!canManage" required />
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input id="whatsapp_enabled" v-model="form.whatsapp.enabled" class="form-check-input" type="checkbox" :disabled="!canManage" />
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

        <div v-else-if="tab === 'countries'" class="d-grid gap-3">
            <form v-if="canManage" class="erp-form-panel" @submit.prevent="saveCountry">
                <h2 class="h5 erp-display mb-3">
                    {{ editingCountryId ? t('settings.edit_country') : t('settings.add_country') }}
                </h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-erp-label">{{ t('settings.country_name') }}</label>
                        <input v-model="countryForm.name" class="form-control form-erp-control" required />
                        <InputError :message="countryForm.errors.name" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-erp-label">{{ t('settings.country_name_ar') }}</label>
                        <input v-model="countryForm.name_ar" class="form-control form-erp-control" required />
                    </div>
                    <div class="col-md-2">
                        <label class="form-erp-label">{{ t('settings.iso_code') }}</label>
                        <input v-model="countryForm.iso_code" class="form-control form-erp-control" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-erp-label">{{ t('settings.sort_order') }}</label>
                        <input v-model="countryForm.sort_order" type="number" min="0" class="form-control form-erp-control" />
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input id="countryActive" v-model="countryForm.is_active" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="countryActive">{{ t('common.active') }}</label>
                        </div>
                    </div>
                </div>
                <div class="erp-form-actions">
                    <button v-if="editingCountryId" type="button" class="btn btn-erp-ghost" @click="resetCountryForm">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn btn-erp" :disabled="countryForm.processing">
                        {{ countryForm.processing ? t('common.saving') : t('common.save') }}
                    </button>
                </div>
            </form>

            <div class="erp-card p-0 overflow-hidden">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('settings.country_name') }}</th>
                            <th>{{ t('settings.country_name_ar') }}</th>
                            <th>{{ t('settings.iso_code') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="countries.length === 0">
                            <td colspan="5"><EmptyState>{{ t('settings.no_countries') }}</EmptyState></td>
                        </tr>
                        <tr v-for="country in countries" :key="country.id">
                            <td class="ps-4 fw-semibold">{{ country.name }}</td>
                            <td>{{ country.name_ar }}</td>
                            <td class="font-monospace">{{ country.iso_code || '—' }}</td>
                            <td>
                                <StatusBadge :tone="country.is_active ? 'success' : 'neutral'" :label="country.is_active ? t('common.active') : t('common.inactive')" />
                            </td>
                            <td class="text-end pe-4">
                                <div v-if="canManage" class="d-inline-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-erp-ghost" @click="startEditCountry(country)">{{ t('common.edit') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteCountry(country)">{{ t('common.delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-else-if="tab === 'users' && canViewUsers" class="erp-card p-0 overflow-hidden">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 erp-display mb-0">{{ t('users.management') }}</h2>
                    <p class="small text-secondary mb-0">{{ t('users.help') }}</p>
                </div>
                <Link v-if="canManageUsers" :href="route('users.create')" class="btn btn-erp">{{ t('users.add') }}</Link>
            </div>
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyUserFilters">
                <div class="col-md-6">
                    <input v-model="userFilterForm.search" type="search" class="form-control form-erp-control" :placeholder="t('users.search_placeholder')" />
                </div>
                <div class="col-md-4">
                    <select v-model="userFilterForm.role" class="form-select form-erp-control">
                        <option value="">{{ t('users.all_roles') }}</option>
                        <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-erp-ghost w-100">{{ t('common.filter') }}</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('common.name') }}</th>
                            <th>{{ t('common.email') }}</th>
                            <th>{{ t('common.role') }}</th>
                            <th>{{ t('common.created') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!users?.data?.length">
                            <td colspan="5"><EmptyState>{{ t('users.none') }}</EmptyState></td>
                        </tr>
                        <tr v-for="user in users?.data ?? []" :key="user.id">
                            <td class="ps-4 fw-semibold">{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td><StatusBadge tone="neutral" :label="user.role ?? '—'" :dot="false" /></td>
                            <td>{{ user.created_at }}</td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-2">
                                    <Link v-if="canManageUsers" :href="route('users.edit', user.id)" class="btn btn-sm btn-erp-ghost">{{ t('common.edit') }}</Link>
                                    <button
                                        v-if="canManageUsers && user.id !== currentUserId"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroyUser(user)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="users?.links?.length > 3" class="p-3 border-top d-flex flex-wrap gap-2 justify-content-center">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, index) in users.links"
                    :key="index"
                    :href="link.url || undefined"
                    class="btn btn-sm"
                    :class="link.active ? 'btn-erp' : 'btn-erp-ghost'"
                    v-html="link.label"
                />
            </div>
        </div>

        <div v-else-if="tab === 'system' && canManage" class="d-grid gap-3">
            <div class="erp-card p-4">
                <h2 class="h5 erp-display mb-1">{{ t('settings.migrations') }}</h2>
                <p class="text-secondary small mb-3">{{ t('settings.migrations_help') }}</p>
                <button type="button" class="btn btn-erp" :class="{ 'is-posting': migrating }" :disabled="migrating" @click="runMigrate">
                    {{ migrating ? t('settings.migrating') : t('settings.run_migrate') }}
                </button>
                <pre v-if="migrateOutput" class="erp-log-viewer mt-3 mb-0">{{ migrateOutput }}</pre>
            </div>

            <div class="erp-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="h5 erp-display mb-1">{{ t('settings.logs') }}</h2>
                        <p class="text-secondary small mb-0">{{ logs?.file }} · {{ logs?.total ?? 0 }} {{ t('settings.log_lines') }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <select class="form-select form-erp-control" style="width: auto" :value="logLevel" @change="filterLogs($event.target.value)">
                            <option value="">{{ t('common.all') }}</option>
                            <option value="ERROR">ERROR</option>
                            <option value="WARNING">WARNING</option>
                            <option value="INFO">INFO</option>
                            <option value="DEBUG">DEBUG</option>
                        </select>
                        <a :href="route('settings.system.logs.download')" class="btn btn-erp-ghost">{{ t('settings.download_log') }}</a>
                        <button
                            type="button"
                            class="btn btn-outline-danger"
                            :disabled="clearingLogs"
                            @click="clearLogs"
                        >
                            {{ clearingLogs ? t('settings.clearing_log') : t('settings.clear_log') }}
                        </button>
                    </div>
                </div>
                <div class="erp-log-viewer">
                    <div v-if="!logs?.lines?.length">{{ t('settings.no_logs') }}</div>
                    <div
                        v-for="(line, index) in logs?.lines ?? []"
                        :key="index"
                        class="erp-log-line"
                        :class="logClass(line.level)"
                    >{{ line.text }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
