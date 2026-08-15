<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    imports: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    importLog: { type: Object, default: () => ({ can_undo: false }) },
});

const page = usePage();
const { t } = useI18n();
const flash = computed(() => page.props.flash ?? {});

const undoLast = () => {
    router.post(route('land-trips.companies.import-logs.undo', props.company.id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${t('land_trips.import_log')} — ${company.name}`" />
    <AppLayout>
        <template #header>{{ t('land_trips.import_log') }}</template>
        <FlashMessage :message="flash.success" />
        <FlashMessage :message="flash.error" />

        <Link :href="route('land-trips.companies.show', company.id)" class="land-hub-back">
            {{ t('land_trips.back_company_cars') }}
        </Link>

        <PageHeader
            :kicker="company.name"
            :title="t('land_trips.import_log')"
            :subtitle="t('land_trips.import_log_help')"
        >
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="btn btn-erp"
                    :disabled="!importLog.can_undo"
                    @click="undoLast"
                >
                    {{ t('land_trips.undo_last_import') }}
                </button>
            </template>
        </PageHeader>

        <EmptyState v-if="!imports.data?.length" icon="I">
            {{ t('land_trips.import_log_empty') }}
        </EmptyState>

        <div v-else class="erp-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">{{ t('common.date') }}</th>
                            <th>{{ t('land_trips.changed_by') }}</th>
                            <th>{{ t('land_trips.import_file') }}</th>
                            <th>{{ t('land_trips.imported_count') }}</th>
                            <th>{{ t('land_trips.updated_count') }}</th>
                            <th>{{ t('land_trips.skipped_count') }}</th>
                            <th>{{ t('common.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in imports.data" :key="item.id">
                            <td class="ps-3 small text-secondary text-nowrap">{{ item.created_at }}</td>
                            <td>{{ item.user_name || '—' }}</td>
                            <td>{{ item.original_filename || '—' }}</td>
                            <td>{{ item.imported_count }}</td>
                            <td>{{ item.updated_count }}</td>
                            <td>{{ item.skipped_count }}</td>
                            <td>
                                <StatusBadge
                                    v-if="item.undone"
                                    tone="neutral"
                                    :label="item.undone_at ? `${t('land_trips.undone')} · ${item.undone_at}` : t('land_trips.undone')"
                                />
                                <StatusBadge v-else tone="success" :label="t('land_trips.applied')" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="imports.links?.length > 3" class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, index) in imports.links"
                :key="index"
                :href="link.url || undefined"
                class="btn btn-sm"
                :class="link.active ? 'btn-erp' : 'btn-erp-ghost'"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
