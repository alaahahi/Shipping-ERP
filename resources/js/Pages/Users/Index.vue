<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', role: '' }) },
    roles: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
const currentUserId = computed(() => page.props.auth?.user?.id);

const filterForm = useForm({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
});

const applyFilters = () => {
    filterForm.get(route('users.index'), { preserveState: true, replace: true });
};

const destroy = (user) => {
    if (!window.confirm(t('users.delete_confirm', { name: user.name }))) {
        return;
    }
    router.delete(route('users.destroy', user.id));
};
</script>

<template>
    <Head :title="t('users.title')" />
    <AppLayout>
        <template #header>{{ t('users.title') }}</template>

        <FlashMessage :message="success" />
        <div v-if="error" class="alert alert-danger border-0 shadow-sm mb-3" role="alert">{{ error }}</div>

        <PageHeader :kicker="t('nav.admin')" :title="t('users.management')" :subtitle="t('users.help')">
            <template #actions>
                <Link v-if="canManage" :href="route('users.create')" class="btn btn-erp">{{ t('users.add') }}</Link>
            </template>
        </PageHeader>

        <div class="erp-card p-0 overflow-hidden">
            <form class="erp-toolbar row g-2 mx-0" @submit.prevent="applyFilters">
                <div class="col-md-6">
                    <input v-model="filterForm.search" type="search" class="form-control form-erp-control" :placeholder="t('users.search_placeholder')" />
                </div>
                <div class="col-md-4">
                    <select v-model="filterForm.role" class="form-select form-erp-control">
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
                        <tr v-if="users.data.length === 0">
                            <td colspan="5">
                                <EmptyState icon="U">{{ t('users.none') }}</EmptyState>
                            </td>
                        </tr>
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="ps-4 fw-semibold">{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>
                                <StatusBadge tone="neutral" :label="user.role ?? '—'" :dot="false" />
                            </td>
                            <td class="text-secondary">{{ user.created_at }}</td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-2">
                                    <Link v-if="canManage" :href="route('users.edit', user.id)" class="btn btn-sm btn-erp-ghost">{{ t('common.edit') }}</Link>
                                    <button
                                        v-if="canManage && user.id !== currentUserId"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="destroy(user)"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="users.links?.length > 3" class="p-3 border-top d-flex flex-wrap gap-2 justify-content-center">
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
    </AppLayout>
</template>
