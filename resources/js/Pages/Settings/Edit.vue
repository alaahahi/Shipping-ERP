<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useLandTripStation } from '@/composables/useLandTripStation';
import { statusRowStyle } from '@/composables/useLandTripStatusColor';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    tab: { type: String, default: 'company' },
    settings: { type: Object, required: true },
    options: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    canManageUsers: { type: Boolean, default: false },
    canViewUsers: { type: Boolean, default: false },
    countries: { type: Array, default: () => [] },
    landCarStatuses: { type: Array, default: () => [] },
    rowTones: { type: Array, default: () => [] },
    users: { type: Object, default: null },
    userFilters: { type: Object, default: () => ({ search: '', role: '' }) },
    roles: { type: Array, default: () => [] },
    logs: { type: Object, default: null },
    logLevel: { type: String, default: '' },
});

const page = usePage();
const { t } = useI18n();
const { stationLabel, toneLabel } = useLandTripStation();
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
const migrateOutput = computed(() => page.props.flash?.migrate_output);
const currentUserId = computed(() => page.props.auth?.user?.id);
const migrating = ref(false);
const clearingLogs = ref(false);
const editingCountryId = ref(null);
const editingStatusId = ref(null);

const form = useForm({
    company: {
        name: props.settings.company.name,
        email: props.settings.company.email,
        phone: props.settings.company.phone,
        address: props.settings.company.address,
        logo: null,
        remove_logo: false,
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

const logoPreview = computed(() => {
    if (form.company.logo) {
        return URL.createObjectURL(form.company.logo);
    }

    if (form.company.remove_logo) {
        return null;
    }

    return props.settings.company.logo_url || null;
});

const onLogoChange = (event) => {
    form.company.logo = event.target.files?.[0] ?? null;
    form.company.remove_logo = false;
};

const removeLogo = () => {
    form.company.logo = null;
    form.company.remove_logo = true;
};

const submit = () => form.put(route('settings.update'), { forceFormData: true });

const countryForm = useForm({
    name: '',
    name_ar: '',
    iso_code: '',
    latitude: '',
    longitude: '',
    is_active: true,
    sort_order: 0,
});

const statusForm = useForm({
    code: '',
    name: '',
    name_ar: '',
    name_ckb: '',
    row_tone: 'yellow',
    color: '#F59E0B',
    match_aliases_text: '',
    sort_order: 0,
    is_active: true,
    country_id: '',
});

const userFilterForm = useForm({
    tab: 'users',
    search: props.userFilters.search ?? '',
    role: props.userFilters.role ?? '',
});

const goTab = (tab) => {
    router.get(route('settings.edit'), { tab }, { preserveState: false, preserveScroll: true });
};

const startEditCountry = (country) => {
    editingCountryId.value = country.id;
    countryForm.name = country.name;
    countryForm.name_ar = country.name_ar;
    countryForm.iso_code = country.iso_code ?? '';
    countryForm.latitude = country.latitude ?? '';
    countryForm.longitude = country.longitude ?? '';
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

const startEditStatus = (status) => {
    editingStatusId.value = status.id;
    statusForm.code = status.code;
    statusForm.name = status.name;
    statusForm.name_ar = status.name_ar ?? '';
    statusForm.name_ckb = status.name_ckb ?? '';
    statusForm.row_tone = status.row_tone;
    statusForm.color = status.color || '#F59E0B';
    statusForm.match_aliases_text = (status.match_aliases ?? []).join('\n');
    statusForm.sort_order = status.sort_order;
    statusForm.is_active = status.is_active;
    statusForm.country_id = status.country_id ?? '';
    statusForm.clearErrors();
};

const resetStatusForm = () => {
    editingStatusId.value = null;
    statusForm.reset();
    statusForm.row_tone = 'yellow';
    statusForm.color = '#F59E0B';
    statusForm.is_active = true;
    statusForm.sort_order = 0;
    statusForm.country_id = '';
};

const saveStatus = () => {
    const payload = {
        ...statusForm.data(),
        match_aliases: statusForm.match_aliases_text
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean),
        country_id: statusForm.country_id || null,
    };

    if (editingStatusId.value) {
        router.put(route('settings.land-car-statuses.update', editingStatusId.value), payload, {
            preserveScroll: true,
            onSuccess: () => resetStatusForm(),
        });
        return;
    }

    router.post(route('settings.land-car-statuses.store'), payload, {
        preserveScroll: true,
        onSuccess: () => resetStatusForm(),
    });
};

const deleteStatus = (status) => {
    if (!window.confirm(t('settings.delete_land_status_confirm', { name: stationLabel(status) }))) return;
    router.delete(route('settings.land-car-statuses.destroy', status.id), { preserveScroll: true });
};

const statusRowClass = () => 'land-status-colored';

const coloredRowStyle = (color) => statusRowStyle(color);

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

// ─── Database Insights ───────────────────────────────────────────────────────
import axios from 'axios';
const dbData    = ref(null);
const dbLoading = ref(false);
const vacuuming = ref(false);
const dbColors  = ['#6366f1','#22c55e','#f59e0b','#3b82f6','#ec4899','#14b8a6','#fb923c','#a855f7','#ef4444','#64748b'];

const dbTables = computed(() => dbData.value?.tables ?? []);
const dbTableQuery = ref('');
const dbFilteredTables = computed(() => {
    const q = dbTableQuery.value.trim().toLowerCase();
    if (!q) {
        return dbTables.value;
    }

    return dbTables.value.filter((row) => String(row.name ?? '').toLowerCase().includes(q));
});
const dbChartTables = computed(() => {
    const tables = dbTables.value;
    if (tables.length <= 10) {
        return tables;
    }

    const top = tables.slice(0, 10);
    const rest = tables.slice(10);
    const restSize = rest.reduce((sum, row) => sum + (row.size_bytes ?? 0), 0);
    const restRows = rest.reduce((sum, row) => sum + (row.rows ?? 0), 0);
    const total = dbData.value?.db_size ?? restSize;

    return [
        ...top,
        {
            name: 'أخرى',
            rows: restRows,
            size_bytes: restSize,
            percent: total > 0 ? Math.round((restSize / total) * 10000) / 100 : null,
        },
    ];
});

const dbChartGradient = computed(() => {
    const tables = dbChartTables.value;
    if (!tables.length) return '#334155';
    const total = dbData.value?.db_size ?? tables.reduce((s, r) => s + (r.size_bytes ?? 0), 0);
    let offset = 0;
    const stops = [];
    tables.forEach((row, idx) => {
        const pct = total > 0 ? ((row.size_bytes ?? 0) / total) * 100 : 0;
        const color = dbColors[idx % dbColors.length];
        stops.push(`${color} ${offset.toFixed(2)}%`);
        offset += pct;
        stops.push(`${color} ${offset.toFixed(2)}%`);
    });
    if (offset < 100) { stops.push(`#334155 ${offset.toFixed(2)}%`, '#334155 100%'); }
    return `conic-gradient(${stops.join(', ')})`;
});

function formatDbBytes(bytes) {
    if (bytes == null) return '—';
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576)    return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024)       return (bytes / 1024).toFixed(0) + ' KB';
    return bytes + ' B';
}

async function loadDbInsights() {
    if (dbLoading.value) return;
    dbLoading.value = true;
    try {
        const { data } = await axios.get(route('settings.system.db.insights'));
        dbData.value = data;
    } catch {
        // silent
    } finally {
        dbLoading.value = false;
    }
}

async function runVacuum() {
    if (!window.confirm('تفريغ المساحة الحرة يصغّر ملف قاعدة البيانات ولا يحذف بيانات. المتابعة؟')) {
        return;
    }

    vacuuming.value = true;
    try {
        const { data } = await axios.post(route('settings.system.db.vacuum'));
        alert(data.message + (data.saved ? ` | وُفِّر: ${formatDbBytes(data.saved)}` : ''));
        await loadDbInsights();
    } catch {
        alert('فشل تفريغ المساحة الحرة');
    } finally {
        vacuuming.value = false;
    }
}

onMounted(() => { if (props.tab === 'system') loadDbInsights(); });
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
            <button type="button" class="btn" :class="tab === 'land_car_statuses' ? 'btn-erp' : 'btn-erp-ghost'" @click="goTab('land_car_statuses')">
                {{ t('settings.tab_land_car_statuses') }}
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
                    <div class="col-12">
                        <label class="form-erp-label">{{ t('settings.logo') }}</label>
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <img
                                v-if="logoPreview"
                                :src="logoPreview"
                                alt=""
                                class="rounded border"
                                style="max-height: 72px; max-width: 160px; object-fit: contain; background: #fff"
                            />
                            <div class="flex-grow-1" style="min-width: 220px">
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="form-control form-erp-control"
                                    :disabled="!canManage"
                                    @change="onLogoChange"
                                />
                                <InputError :message="form.errors['company.logo']" />
                            </div>
                            <button
                                v-if="logoPreview && canManage"
                                type="button"
                                class="btn btn-erp-ghost btn-sm"
                                @click="removeLogo"
                            >
                                {{ t('settings.remove_logo') }}
                            </button>
                        </div>
                        <p class="small text-secondary mt-2 mb-0">{{ t('settings.logo_help') }}</p>
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
                    <div class="col-md-3">
                        <label class="form-erp-label">{{ t('settings.latitude') }}</label>
                        <input v-model="countryForm.latitude" type="number" step="0.0000001" class="form-control form-erp-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-erp-label">{{ t('settings.longitude') }}</label>
                        <input v-model="countryForm.longitude" type="number" step="0.0000001" class="form-control form-erp-control" />
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

        <div v-else-if="tab === 'land_car_statuses'" class="d-grid gap-3">
            <form v-if="canManage" class="erp-form-panel" @submit.prevent="saveStatus">
                <h2 class="h5 erp-display mb-3">
                    {{ editingStatusId ? t('settings.edit_land_status') : t('settings.add_land_status') }}
                </h2>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-erp-label">{{ t('settings.status_code') }}</label>
                        <input v-model="statusForm.code" class="form-control form-erp-control" required />
                        <InputError :message="statusForm.errors.code" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-erp-label">{{ t('settings.status_name') }}</label>
                        <input v-model="statusForm.name" class="form-control form-erp-control" required />
                    </div>
                    <div class="col-md-3">
                        <label class="form-erp-label">{{ t('settings.status_name_ar') }}</label>
                        <input v-model="statusForm.name_ar" class="form-control form-erp-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-erp-label">{{ t('settings.status_name_ckb') }}</label>
                        <input v-model="statusForm.name_ckb" class="form-control form-erp-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-erp-label">{{ t('settings.status_color') }}</label>
                        <div class="d-flex align-items-center gap-2">
                            <input v-model="statusForm.color" type="color" class="form-control form-control-color land-color-input" required />
                            <input v-model="statusForm.color" type="text" class="form-control form-erp-control font-monospace" maxlength="7" required />
                        </div>
                        <InputError :message="statusForm.errors.color" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-erp-label">{{ t('settings.row_tone') }}</label>
                        <select v-model="statusForm.row_tone" class="form-select form-erp-control" required>
                            <option v-for="tone in rowTones" :key="tone.value" :value="tone.value">{{ toneLabel(tone.value) }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-erp-label">{{ t('settings.sort_order') }}</label>
                        <input v-model="statusForm.sort_order" type="number" min="0" class="form-control form-erp-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-erp-label">{{ t('settings.location_country') }}</label>
                        <select v-model="statusForm.country_id" class="form-select form-erp-control">
                            <option value="">{{ t('common.none') }}</option>
                            <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.label }}</option>
                        </select>
                        <InputError :message="statusForm.errors.country_id" />
                    </div>
                    <div class="col-md-12">
                        <label class="form-erp-label">{{ t('settings.match_aliases') }}</label>
                        <textarea v-model="statusForm.match_aliases_text" rows="3" class="form-control form-erp-control" :placeholder="t('settings.match_aliases_help')" />
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input id="landStatusActive" v-model="statusForm.is_active" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="landStatusActive">{{ t('common.active') }}</label>
                        </div>
                    </div>
                </div>
                <div class="erp-form-actions">
                    <button v-if="editingStatusId" type="button" class="btn btn-erp-ghost" @click="resetStatusForm">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn btn-erp" :disabled="statusForm.processing">
                        {{ statusForm.processing ? t('common.saving') : t('common.save') }}
                    </button>
                </div>
            </form>

            <div class="erp-card p-0 overflow-hidden">
                <table class="table erp-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">{{ t('settings.status_name') }}</th>
                            <th>{{ t('settings.status_name_ckb') }}</th>
                            <th>{{ t('settings.location_country') }}</th>
                            <th>{{ t('settings.status_color') }}</th>
                            <th>{{ t('settings.row_tone') }}</th>
                            <th>{{ t('common.status') }}</th>
                            <th class="text-end pe-4">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="landCarStatuses.length === 0">
                            <td colspan="7"><EmptyState>{{ t('settings.no_land_statuses') }}</EmptyState></td>
                        </tr>
                        <tr
                            v-for="status in landCarStatuses"
                            :key="status.id"
                            class="land-status-colored"
                            :style="coloredRowStyle(status.color)"
                        >
                            <td class="ps-4 fw-semibold">{{ stationLabel(status) }}</td>
                            <td>{{ status.name_ckb || '—' }}</td>
                            <td>{{ status.country_label || '—' }}</td>
                            <td>
                                <span class="land-color-swatch" :style="{ background: status.color }"></span>
                                <span class="font-monospace small ms-1">{{ status.color }}</span>
                            </td>
                            <td>{{ toneLabel(status.row_tone) }}</td>
                            <td>
                                <StatusBadge :tone="status.is_active ? 'success' : 'neutral'" :label="status.is_active ? t('common.active') : t('common.inactive')" />
                            </td>
                            <td class="text-end pe-4">
                                <div v-if="canManage" class="d-inline-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-erp-ghost" @click="startEditStatus(status)">{{ t('common.edit') }}</button>
                                    <button
                                        v-if="!status.is_archive"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="deleteStatus(status)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
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

            <!-- DB Insights card -->
            <div class="erp-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="h5 erp-display mb-1">🗄️ تخزين قاعدة البيانات</h2>
                        <p class="text-secondary small mb-0">توزيع الحجم على مستوى الجداول</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="btn btn-warning btn-sm"
                            :disabled="vacuuming || dbLoading"
                            @click="runVacuum"
                        >
                            <span v-if="vacuuming" class="spinner-border spinner-border-sm me-1"></span>
                            {{ vacuuming ? 'جارٍ التفريغ…' : 'تفريغ المساحة الحرة' }}
                        </button>
                        <button type="button" class="btn btn-erp-ghost btn-sm" :disabled="dbLoading" @click="loadDbInsights">
                            {{ dbLoading ? '…' : '↻ تحديث' }}
                        </button>
                    </div>
                </div>
                <div
                    v-if="dbData && (dbData.free_bytes ?? 0) > 0"
                    class="alert alert-warning py-2 px-3 small mb-3"
                >
                    {{ formatDbBytes(dbData.free_bytes) }} مساحة حرة داخل الملف — التفريغ يصغّر قاعدة البيانات دون حذف بيانات.
                </div>

                <div v-if="dbLoading" class="text-center py-4">
                    <span class="spinner-border text-primary"></span>
                </div>

                <template v-else-if="dbData">
                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <div class="erp-card p-3 text-center">
                                <div class="small text-secondary mb-1">الإجمالي</div>
                                <div class="fw-bold">{{ formatDbBytes(dbData.db_size) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="erp-card p-3 text-center">
                                <div class="small text-secondary mb-1">مستخدم</div>
                                <div class="fw-bold">{{ formatDbBytes(dbData.used_bytes) }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="erp-card p-3 text-center">
                                <div class="small text-secondary mb-1">حر</div>
                                <div class="fw-bold text-success">{{ formatDbBytes(dbData.free_bytes) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-lg-row gap-4 align-items-start">
                        <div class="flex-shrink-0 mx-auto">
                            <div style="position:relative;width:140px;height:140px;">
                                <div style="width:140px;height:140px;border-radius:50%;" :style="{ background: dbChartGradient }"></div>
                                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
                                    <div style="width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-align:center;font-size:0.75rem;" class="erp-card border">
                                        {{ dbTables.length }}<br>جدول
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex-grow-1 w-100">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <input
                                    v-model="dbTableQuery"
                                    type="search"
                                    class="form-control form-control-sm"
                                    style="max-width: 280px;"
                                    placeholder="بحث عن جدول…"
                                >
                                <span class="small text-secondary">{{ dbFilteredTables.length }} / {{ dbTables.length }}</span>
                            </div>
                            <div class="overflow-auto" style="max-height: 420px;">
                                <div v-for="(row, idx) in dbFilteredTables" :key="row.name"
                                    class="d-flex align-items-center gap-2 py-1 border-bottom small">
                                    <span style="width:10px;height:10px;border-radius:50%;flex-shrink:0;" :style="{ background: dbColors[idx % dbColors.length] }"></span>
                                    <code class="flex-grow-1 text-truncate" style="max-width:260px;">{{ row.name }}</code>
                                    <span class="text-secondary">{{ (row.rows ?? 0).toLocaleString() }} صف</span>
                                    <span class="fw-bold" style="min-width:60px;text-align:end;">{{ formatDbBytes(row.size_bytes) }}</span>
                                    <span class="text-secondary" style="min-width:42px;text-align:end;">{{ row.percent != null ? row.percent.toFixed(1) + '%' : '' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-else class="text-center py-4">
                    <button type="button" class="btn btn-erp" @click="loadDbInsights">تحميل المعلومات</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
