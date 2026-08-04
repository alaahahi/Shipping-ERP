<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    ships: { type: Array, default: () => [] },
    defaults: {
        type: Object,
        default: () => ({
            pol: 'Rajid Port',
            pod: 'Bandar Abbas',
        }),
    },
});

const { t } = useI18n();

const form = useForm({
    ship_id: props.ships[0]?.id ?? null,
    voyage_number: '',
    sailing_date: new Date().toISOString().slice(0, 10),
    arrival_date: '',
    pol: props.defaults.pol || 'Rajid Port',
    pod: props.defaults.pod || 'Bandar Abbas',
    captain: props.ships[0]?.default_captain ?? '',
    cost_per_car_aed: 0,
    captain_commission_aed: 0,
    purchase_price_aed: 0,
    notes: '',
});

watch(
    () => form.ship_id,
    (shipId) => {
        const ship = props.ships.find((item) => item.id === shipId);
        if (ship?.default_captain && !form.captain) {
            form.captain = ship.default_captain;
        }
    },
);

const submit = () => form.post(route('voyages.store'));
</script>

<template>
    <Head :title="t('voyages.add')" />
    <AppLayout>
        <template #header>{{ t('voyages.add') }}</template>

        <div class="mb-3">
            <Link :href="route('voyages.index')" class="text-decoration-none small fw-semibold">← {{ t('voyages.back') }}</Link>
        </div>

        <PageHeader :title="t('voyages.add')" :subtitle="t('voyages.form_help')" />

        <form class="erp-card p-4" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('ships.name') }}</label>
                    <select v-model="form.ship_id" class="form-select form-erp-control" required>
                        <option v-for="ship in ships" :key="ship.id" :value="ship.id">{{ ship.label }}</option>
                    </select>
                    <InputError :message="form.errors.ship_id" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('voyages.number') }}</label>
                    <input v-model="form.voyage_number" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.voyage_number" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('voyages.captain') }}</label>
                    <input v-model="form.captain" class="form-control form-erp-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('voyages.sailing') }}</label>
                    <input v-model="form.sailing_date" type="date" class="form-control form-erp-control" required />
                    <InputError :message="form.errors.sailing_date" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('voyages.arrival') }}</label>
                    <input v-model="form.arrival_date" type="date" class="form-control form-erp-control" />
                    <InputError :message="form.errors.arrival_date" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('voyages.pol') }}</label>
                    <input v-model="form.pol" class="form-control form-erp-control" :placeholder="defaults.pol" />
                </div>
                <div class="col-md-3">
                    <label class="form-erp-label">{{ t('voyages.pod') }}</label>
                    <input v-model="form.pod" class="form-control form-erp-control" :placeholder="defaults.pod" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('voyages.cost_aed') }}</label>
                    <input v-model.number="form.cost_per_car_aed" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('voyages.commission_aed') }}</label>
                    <input v-model.number="form.captain_commission_aed" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                </div>
                <div class="col-md-4">
                    <label class="form-erp-label">{{ t('voyages.purchase_aed') }}</label>
                    <input v-model.number="form.purchase_price_aed" type="number" min="0" step="0.01" class="form-control form-erp-control" />
                </div>
                <div class="col-12">
                    <label class="form-erp-label">{{ t('common.notes') }}</label>
                    <textarea v-model="form.notes" class="form-control form-erp-control" rows="3" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('voyages.index')" class="btn btn-erp-ghost">{{ t('common.cancel') }}</Link>
                <button class="btn btn-erp" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('voyages.create') }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
