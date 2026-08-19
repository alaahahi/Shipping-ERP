<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ChassisLetterOWarning from '@/Components/LandTrips/ChassisLetterOWarning.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    company: { type: Object, required: true },
    changes: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const flash = computed(() => page.props.flash ?? {});
</script>

<template>
    <Head :title="`${t('land_trips.price_log')} — ${company.name}`" />
    <AppLayout>
        <template #header>{{ t('land_trips.price_log') }}</template>
        <FlashMessage :message="flash.success" />
        <FlashMessage :message="flash.error" />

        <Link :href="route('land-trips.companies.show', company.id)" class="land-hub-back">
            {{ t('land_trips.back_company_cars') }}
        </Link>

        <PageHeader
            :kicker="company.name"
            :title="t('land_trips.price_log')"
            :subtitle="t('land_trips.price_log_help')"
        />

        <EmptyState v-if="!changes.data?.length" icon="$">
            {{ t('land_trips.price_log_empty') }}
        </EmptyState>

        <div v-else class="erp-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table erp-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">{{ t('common.date') }}</th>
                            <th>{{ t('land_trips.changed_by') }}</th>
                            <th>{{ t('land_trips.cars') }}</th>
                            <th>{{ t('land_trips.new_bulk_price') }}</th>
                            <th>{{ t('land_trips.chassis') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="change in changes.data" :key="change.id">
                            <td class="ps-3 small text-secondary text-nowrap">{{ change.created_at }}</td>
                            <td>{{ change.user_name || '—' }}</td>
                            <td>{{ change.cars_count }}</td>
                            <td>{{ change.new_price }}</td>
                            <td class="small">
                                <details v-if="change.items?.length">
                                    <summary>{{ change.chassis_preview || t('land_trips.cars') }}</summary>
                                    <ul class="mb-0 ps-3 mt-2">
                                        <li v-for="item in change.items" :key="item.id">
                                            <ChassisLetterOWarning :value="item.chassis_no" />
                                            :
                                            {{ item.old_price }}
                                            →
                                            {{ item.new_price }}
                                        </li>
                                    </ul>
                                </details>
                                <span v-else>—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="changes.links?.length > 3" class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, index) in changes.links"
                :key="index"
                :href="link.url || undefined"
                class="btn btn-sm"
                :class="link.active ? 'btn-erp' : 'btn-erp-ghost'"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
