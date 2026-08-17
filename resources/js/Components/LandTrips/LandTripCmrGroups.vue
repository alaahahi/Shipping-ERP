<script setup>
import { fbButton, fbGhostButton, fbInput, fbLink } from '@/flowbite';
import axios from 'axios';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companyId: { type: [Number, String], required: true },
    canManage: { type: Boolean, default: false },
    search: { type: String, default: '' },
    locationStatusId: { type: [Number, String], default: '' },
});

const emit = defineEmits(['toast', 'renamed']);

const { t } = useI18n();

const groups = ref([]);
const loading = ref(false);
const error = ref('');
const uploadingKey = ref(null);
const removingKey = ref(null);
const renamingKey = ref(null);
const editingKey = ref(null);
const editValue = ref('');
const editInputRef = ref(null);
const fileInputs = ref({});

const matchesGroup = (group, query) => {
    if (!query) {
        return true;
    }

    const hay = [
        group.cmr_label,
        group.cmr_key,
        ...(group.chassis_nos ?? []),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return hay.includes(query);
};

const visibleGroups = computed(() => {
    const query = String(props.search ?? '').trim().toLowerCase();
    if (!query) {
        return groups.value;
    }

    return groups.value.filter((group) => matchesGroup(group, query));
});

const groupCount = computed(() => visibleGroups.value.length);
const carCount = computed(() => visibleGroups.value.reduce((sum, g) => sum + (g.cars_count || 0), 0));
const busy = computed(() => uploadingKey.value !== null || removingKey.value !== null || renamingKey.value !== null);

const loadGroups = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(route('land-trips.companies.cmr-groups', props.companyId), {
            params: {
                location_status_id: props.locationStatusId || undefined,
            },
        });
        groups.value = data.groups ?? [];
    } catch {
        error.value = t('land_trips.cmr_groups_load_error');
        groups.value = [];
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.companyId, props.locationStatusId],
    () => {
        loadGroups();
    },
    { immediate: true },
);

const groupTitle = (group) => (
    group.is_unspecified
        ? t('land_trips.cmr_unspecified')
        : (group.cmr_label || group.cmr_key)
);

const setFileInputRef = (key, el) => {
    if (el) {
        fileInputs.value[key] = el;
    } else {
        delete fileInputs.value[key];
    }
};

const startEdit = async (group) => {
    if (!props.canManage || busy.value) {
        return;
    }
    editingKey.value = group.cmr_key;
    editValue.value = group.is_unspecified ? '' : (group.cmr_label || group.cmr_key || '');
    await nextTick();
    editInputRef.value?.focus?.();
    editInputRef.value?.select?.();
};

const cancelEdit = () => {
    editingKey.value = null;
    editValue.value = '';
};

const saveEdit = async (group) => {
    if (!props.canManage || renamingKey.value !== null) {
        return;
    }

    const fromKey = group.cmr_key ?? '';
    const toKey = String(editValue.value ?? '').trim();

    if (fromKey === '' && toKey === '') {
        cancelEdit();
        return;
    }

    renamingKey.value = fromKey;
    error.value = '';

    try {
        const { data } = await axios.patch(route('land-trips.companies.cmr-groups.rename', props.companyId), {
            from_cmr_key: fromKey,
            to_cmr_key: toKey,
        });

        editingKey.value = null;
        editValue.value = '';
        await loadGroups();
        emit('toast', t('land_trips.cmr_renamed', { count: data.updated ?? 0 }));
        emit('renamed', data);
    } catch (err) {
        const message = err?.response?.data?.message
            || err?.response?.data?.errors?.to_cmr_key?.[0]
            || t('land_trips.cmr_rename_error');
        error.value = message;
    } finally {
        renamingKey.value = null;
    }
};

const pickFile = (group) => {
    fileInputs.value[group.cmr_key]?.click();
};

const onFileChosen = async (group, event) => {
    const input = event.target;
    const file = input?.files?.[0];
    if (!file || uploadingKey.value !== null) {
        return;
    }

    uploadingKey.value = group.cmr_key;
    error.value = '';

    const formData = new FormData();
    formData.append('file', file);
    formData.append('cmr_key', group.cmr_key ?? '');

    try {
        const { data } = await axios.post(
            route('land-trips.companies.cmr-files.store', props.companyId),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        const idx = groups.value.findIndex((g) => g.cmr_key === group.cmr_key);
        if (idx !== -1) {
            groups.value[idx] = {
                ...groups.value[idx],
                attachment: data.attachment,
            };
        }
        emit('toast', t('land_trips.cmr_file_uploaded'));
    } catch (err) {
        const message = err?.response?.data?.message
            || err?.response?.data?.errors?.file?.[0]
            || t('land_trips.cmr_file_upload_error');
        error.value = message;
    } finally {
        uploadingKey.value = null;
        if (input) {
            input.value = '';
        }
    }
};

const removeFile = async (group) => {
    if (!group.attachment || removingKey.value !== null) {
        return;
    }
    if (!window.confirm(t('land_trips.cmr_file_remove_confirm'))) {
        return;
    }

    removingKey.value = group.cmr_key;
    error.value = '';

    try {
        await axios.delete(route('land-trips.companies.cmr-files.destroy', props.companyId), {
            data: { cmr_key: group.cmr_key ?? '' },
        });

        const idx = groups.value.findIndex((g) => g.cmr_key === group.cmr_key);
        if (idx !== -1) {
            groups.value[idx] = {
                ...groups.value[idx],
                attachment: null,
            };
        }
        emit('toast', t('land_trips.cmr_file_removed'));
    } catch {
        error.value = t('land_trips.cmr_file_remove_error');
    } finally {
        removingKey.value = null;
    }
};

defineExpose({ reload: loadGroups });
</script>

<template>
    <div class="land-cmr-groups" role="region" :aria-label="t('land_trips.view_by_cmr')">
        <div class="land-cmr-groups-meta">
            <p class="land-cmr-groups-help mb-0">
                {{ loading
                    ? t('land_trips.loading_more')
                    : t('land_trips.cmr_groups_summary', { groups: groupCount, cars: carCount }) }}
            </p>
        </div>

        <p v-if="error" class="land-cmr-groups-error mb-0" role="alert">{{ error }}</p>

        <div v-if="loading && !groups.length" class="land-cmr-groups-skeleton" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="land-cmr-group is-skeleton" />
        </div>

        <div v-else-if="!loading && !visibleGroups.length" class="land-cmr-groups-empty">
            {{ t('land_trips.cmr_groups_empty') }}
        </div>

        <TransitionGroup
            v-else
            name="land-cmr-stagger"
            tag="div"
            class="land-cmr-groups-list"
            appear
        >
            <article
                v-for="(group, index) in visibleGroups"
                :key="group.cmr_key === '' ? '__unspecified__' : group.cmr_key"
                class="land-cmr-group"
                :class="{ 'is-unspecified': group.is_unspecified }"
                :style="{ '--cmr-delay': `${Math.min(index, 12) * 30}ms` }"
            >
                <header class="land-cmr-group-head">
                    <div class="land-cmr-group-title-wrap">
                        <span
                            class="land-cmr-badge"
                            :class="{ 'is-muted': group.is_unspecified }"
                        >
                            {{ t('land_trips.cmr') }}
                        </span>

                        <div v-if="canManage && editingKey === group.cmr_key" class="land-cmr-edit-row">
                            <input
                                ref="editInputRef"
                                v-model="editValue"
                                type="text"
                                maxlength="80"
                                :class="[fbInput, 'land-cmr-edit-input']"
                                :disabled="renamingKey === group.cmr_key"
                                :placeholder="t('land_trips.cmr_edit_placeholder')"
                                :aria-label="t('land_trips.cmr_edit')"
                                @keydown.enter.prevent="saveEdit(group)"
                                @keydown.esc.prevent="cancelEdit"
                            />
                            <button
                                type="button"
                                :class="[fbButton, '!w-auto land-cmr-action-btn cursor-pointer']"
                                :disabled="renamingKey === group.cmr_key"
                                @click="saveEdit(group)"
                            >
                                {{ renamingKey === group.cmr_key ? t('common.saving') : t('common.save') }}
                            </button>
                            <button
                                type="button"
                                :class="[fbGhostButton, '!w-auto land-cmr-action-btn cursor-pointer']"
                                :disabled="renamingKey === group.cmr_key"
                                @click="cancelEdit"
                            >
                                {{ t('common.cancel') }}
                            </button>
                        </div>

                        <template v-else>
                            <h3 class="land-cmr-group-title">{{ groupTitle(group) }}</h3>
                            <button
                                v-if="canManage"
                                type="button"
                                :class="[fbGhostButton, '!w-auto land-cmr-action-btn cursor-pointer']"
                                :disabled="busy"
                                :aria-label="t('land_trips.cmr_edit')"
                                @click="startEdit(group)"
                            >
                                {{ t('land_trips.cmr_edit') }}
                            </button>
                        </template>

                        <span class="land-cmr-count">
                            {{ t('land_trips.cmr_cars_count', { count: group.cars_count }) }}
                        </span>
                    </div>

                    <div class="land-cmr-file-actions">
                        <template v-if="group.attachment">
                            <a
                                :href="group.attachment.url"
                                :class="fbLink"
                                class="land-cmr-file-link"
                                target="_blank"
                                rel="noopener noreferrer"
                                :title="group.attachment.original_name || ''"
                            >
                                {{ group.attachment.original_name || t('land_trips.cmr_view_file') }}
                            </a>
                            <button
                                v-if="canManage"
                                type="button"
                                :class="fbGhostButton"
                                class="!w-auto cursor-pointer land-cmr-action-btn"
                                :disabled="removingKey === group.cmr_key || busy"
                                @click="removeFile(group)"
                            >
                                {{ removingKey === group.cmr_key ? t('land_trips.cmr_removing') : t('common.delete') }}
                            </button>
                        </template>
                        <button
                            v-if="canManage"
                            type="button"
                            :class="fbGhostButton"
                            class="!w-auto cursor-pointer land-cmr-action-btn"
                            :disabled="busy"
                            @click="pickFile(group)"
                        >
                            {{ uploadingKey === group.cmr_key
                                ? t('land_trips.cmr_uploading')
                                : (group.attachment ? t('land_trips.cmr_replace_file') : t('land_trips.cmr_attach_file')) }}
                        </button>
                        <input
                            :ref="(el) => setFileInputRef(group.cmr_key, el)"
                            type="file"
                            class="visually-hidden"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,application/pdf,image/*"
                            @change="onFileChosen(group, $event)"
                        />
                    </div>
                </header>

                <div class="land-cmr-chassis">
                    <span
                        v-for="(chassis, cIdx) in group.chassis_nos"
                        :key="`${group.cmr_key}-${chassis}-${cIdx}`"
                        class="land-cmr-chip"
                    >
                        {{ chassis }}
                    </span>
                    <span v-if="!group.chassis_nos?.length" class="land-cmr-chassis-empty">
                        {{ t('land_trips.cmr_no_chassis') }}
                    </span>
                </div>
            </article>
        </TransitionGroup>
    </div>
</template>
