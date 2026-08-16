<script setup>
import { fbGhostButton, fbLink } from '@/flowbite';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    companyId: { type: [Number, String], required: true },
    canManage: { type: Boolean, default: false },
    search: { type: String, default: '' },
    locationStatusId: { type: [Number, String], default: '' },
});

const emit = defineEmits(['toast']);

const { t } = useI18n();

const groups = ref([]);
const loading = ref(false);
const error = ref('');
const uploadingKey = ref(null);
const removingKey = ref(null);
const fileInputs = ref({});

const groupCount = computed(() => groups.value.length);
const carCount = computed(() => groups.value.reduce((sum, g) => sum + (g.cars_count || 0), 0));

const loadGroups = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(route('land-trips.companies.cmr-groups', props.companyId), {
            params: {
                search: String(props.search ?? '').trim() || undefined,
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
    () => [props.companyId, props.search, props.locationStatusId],
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

        <div v-else-if="!loading && !groups.length" class="land-cmr-groups-empty">
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
                v-for="(group, index) in groups"
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
                        <h3 class="land-cmr-group-title">{{ groupTitle(group) }}</h3>
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
                                :disabled="removingKey === group.cmr_key || uploadingKey !== null"
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
                            :disabled="uploadingKey !== null || removingKey !== null"
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
