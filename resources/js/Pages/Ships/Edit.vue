<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    ship: { type: Object, required: true },
});

const { t } = useI18n();

const form = useForm({
    name: props.ship.name,
    flag: props.ship.flag ?? '',
    imo_number: props.ship.imo_number ?? '',
    call_sign: props.ship.call_sign ?? '',
    default_captain: props.ship.default_captain ?? '',
    is_active: props.ship.is_active,
    notes: props.ship.notes ?? '',
});

const submit = () => form.put(route('ships.update', props.ship.id));
</script>

<template>
    <Head :title="`${t('common.edit')} ${ship.name}`" />
    <AppLayout>
        <template #header>{{ t('ships.edit') }}</template>

        <div class="mb-3">
            <Link :href="route('ships.show', ship.id)" class="text-decoration-none small fw-semibold">
                ← {{ t('ships.back_ship') }}
            </Link>
        </div>

        <PageHeader :title="t('ships.edit')" :subtitle="ship.name" />

        <form class="erp-card p-4 col-lg-8" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('ships.name') }}</label>
                    <input v-model="form.name" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="col-md-6">
                    <label class="form-erp-label">{{ t('ships.flag') }}</label>
                    <input v-model="form.flag" class="form-control form-erp-control" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('ships.imo') }}</label>
                    <input v-model="form.imo_number" class="form-control form-erp-control" />
                    <InputError :message="form.errors.imo_number" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('ships.call_sign') }}</label>
                    <input v-model="form.call_sign" class="form-control form-erp-control" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('ships.captain') }}</label>
                    <input v-model="form.default_captain" class="form-control form-erp-control" />
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <textarea v-model="form.notes" class="form-control form-erp-control" rows="3" />
                </div>
                <div class="col-12 form-check ms-1">
                    <input id="is_active" v-model="form.is_active" type="checkbox" class="form-check-input" />
                    <label for="is_active" class="form-check-label">{{ t('common.active') }}</label>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('ships.show', ship.id)" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('common.save') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
