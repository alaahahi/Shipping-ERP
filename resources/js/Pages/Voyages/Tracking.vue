<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import InputError from '@/Components/InputError.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    voyage: { type: Object, required: true },
    tracking: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
const success = computed(() => page.props.flash?.success);
const mapEl = ref(null);

const waypointForm = useForm({
    name: '',
    reached_at: '',
    latitude: null,
    longitude: null,
    sort_order: 0,
    notes: '',
});

const submitWaypoint = () => {
    waypointForm.post(route('voyages.waypoints.store', props.voyage.id), {
        preserveScroll: true,
        onSuccess: () => {
            waypointForm.reset('name', 'reached_at', 'latitude', 'longitude', 'notes');
            waypointForm.sort_order = (props.tracking.waypoints?.length ?? 0);
        },
    });
};

const removeWaypoint = (waypoint) => {
    if (!window.confirm(t('voyages.waypoint_delete_confirm'))) return;
    router.delete(route('voyages.waypoints.destroy', [props.voyage.id, waypoint.id]), {
        preserveScroll: true,
    });
};

onMounted(() => {
    if (!mapEl.value) return;

    const map = L.map(mapEl.value).setView([27.0, 55.0], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const waypoints = props.tracking.waypoints || [];
    const routes = props.tracking.routes || [];

    const markers = [];
    waypoints.forEach((wp) => {
        if (wp.latitude !== null && wp.longitude !== null) {
            const marker = L.marker([wp.latitude, wp.longitude])
                .addTo(map)
                .bindPopup(`<strong>${wp.name}</strong><br>${wp.reached_at || ''}`);
            markers.push(marker);
        }
    });

    routes.forEach((route) => {
        const coords = route.coordinates.map((c) => [c.lat, c.lng]);
        if (coords.length > 1) {
            L.polyline(coords, { color: route.color, weight: 4, opacity: 0.8 })
                .addTo(map)
                .bindPopup(route.label || '');
        }
    });

    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.2));
    }
});
</script>

<template>
    <Head :title="`${voyage.voyage_number} · ${t('voyages.tracking')}`" />
    <AppLayout>
        <template #header>{{ t('voyages.tracking') }}</template>
        <FlashMessage :message="success" />

        <div class="mb-3">
            <Link :href="route('voyages.show', voyage.id)" class="text-decoration-none small fw-semibold">
                ← {{ t('voyages.back') }}
            </Link>
        </div>

        <PageHeader
            :kicker="voyage.voyage_number"
            :title="t('voyages.tracking')"
            :subtitle="`${voyage.ship?.name || ''} · ${voyage.pol || ''} → ${voyage.pod || ''}`"
        />

        <div class="erp-card p-0 overflow-hidden mb-3">
            <div ref="mapEl" style="height: 500px; width: 100%;" />
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="erp-card p-4">
                    <h3 class="erp-panel-title mb-3">{{ t('voyages.waypoints') }}</h3>
                    <form v-if="canManage" class="erp-form-panel mb-3" @submit.prevent="submitWaypoint">
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-erp-label">{{ t('voyages.waypoint_name') }}</label>
                                <input v-model="waypointForm.name" class="form-control form-erp-control" required />
                                <InputError :message="waypointForm.errors.name" />
                            </div>
                            <div class="col-6">
                                <label class="form-erp-label">{{ t('voyages.waypoint_date') }}</label>
                                <input v-model="waypointForm.reached_at" type="datetime-local" class="form-control form-erp-control" />
                            </div>
                            <div class="col-6">
                                <label class="form-erp-label">{{ t('voyages.sort_order') }}</label>
                                <input v-model.number="waypointForm.sort_order" type="number" min="0" class="form-control form-erp-control" />
                            </div>
                            <div class="col-6">
                                <label class="form-erp-label">Lat</label>
                                <input v-model.number="waypointForm.latitude" type="number" step="any" class="form-control form-erp-control" />
                            </div>
                            <div class="col-6">
                                <label class="form-erp-label">Lng</label>
                                <input v-model.number="waypointForm.longitude" type="number" step="any" class="form-control form-erp-control" />
                            </div>
                            <div class="col-12">
                                <label class="form-erp-label">{{ t('common.notes') }}</label>
                                <input v-model="waypointForm.notes" class="form-control form-erp-control" />
                            </div>
                        </div>
                        <div class="erp-form-actions">
                            <button type="submit" class="btn btn-erp" :disabled="waypointForm.processing">
                                {{ waypointForm.processing ? t('common.saving') : t('voyages.add_waypoint') }}
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table erp-table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ t('voyages.waypoint_name') }}</th>
                                    <th>{{ t('voyages.waypoint_date') }}</th>
                                    <th class="text-end pe-3">{{ t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="tracking.waypoints.length === 0">
                                    <td colspan="3" class="text-center text-secondary py-4">
                                        {{ t('voyages.no_waypoints') }}
                                    </td>
                                </tr>
                                <tr v-for="wp in tracking.waypoints" :key="wp.id || wp.name">
                                    <td>
                                        <div class="fw-semibold">{{ wp.name }}</div>
                                        <div v-if="wp.notes" class="small text-secondary">{{ wp.notes }}</div>
                                    </td>
                                    <td>{{ wp.reached_at || '—' }}</td>
                                    <td class="text-end pe-3">
                                        <button
                                            v-if="canManage && wp.id"
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            @click="removeWaypoint(wp)"
                                        >
                                            {{ t('common.delete') }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="erp-card p-4">
                    <h3 class="erp-panel-title mb-3">{{ t('voyages.route_info') }}</h3>
                    <p class="small text-secondary mb-0">
                        {{ t('voyages.route_info_help') }}
                    </p>
                    <div class="mt-3">
                        <strong>{{ t('voyages.pol') }}:</strong> {{ voyage.pol || '—' }}
                        <br />
                        <strong>{{ t('voyages.pod') }}:</strong> {{ voyage.pod || '—' }}
                        <br />
                        <strong>{{ t('common.date') }}:</strong> {{ voyage.sailing_date || '—' }} → {{ voyage.arrival_date || '—' }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
