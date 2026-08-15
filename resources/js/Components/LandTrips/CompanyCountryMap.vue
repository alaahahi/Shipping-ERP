<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    countries: { type: Array, default: () => [] },
    active: { type: Boolean, default: true },
});

const { t } = useI18n();
const mapEl = ref(null);
let map = null;
let layerGroup = null;

const mappable = (rows) => rows.filter((row) => row.latitude != null && row.longitude != null && Number(row.cars_count) > 0);

const renderMarkers = () => {
    if (!map || !layerGroup) {
        return;
    }

    layerGroup.clearLayers();
    const points = [];

    mappable(props.countries).forEach((country) => {
        const count = Number(country.cars_count) || 0;
        const radius = Math.min(28, 10 + Math.sqrt(count) * 3);
        const locations = (country.locations ?? [])
            .filter((item) => Number(item.count) > 0)
            .map((item) => `${item.label}: ${item.count}`)
            .join('<br>');

        const marker = L.circleMarker([country.latitude, country.longitude], {
            radius,
            color: '#0f766e',
            weight: 2,
            fillColor: '#14b8a6',
            fillOpacity: 0.72,
        }).bindPopup(
            `<strong>${country.label}</strong><br>${t('land_trips.map_cars_count', { count })}`
            + (locations ? `<br><br>${locations}` : '')
        );

        marker.addTo(layerGroup);
        points.push([country.latitude, country.longitude]);
    });

    if (points.length === 1) {
        map.setView(points[0], 5);
    } else if (points.length > 1) {
        map.fitBounds(points, { padding: [36, 36], maxZoom: 6 });
    } else {
        map.setView([32.4, 53.7], 4);
    }
};

onMounted(() => {
    if (!mapEl.value) {
        return;
    }

    map = L.map(mapEl.value, { scrollWheelZoom: false, attributionControl: true }).setView([32.4, 53.7], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);
    layerGroup = L.layerGroup().addTo(map);
    renderMarkers();
});

watch(() => props.countries, () => renderMarkers(), { deep: true });
watch(() => props.active, (on) => {
    if (on) {
        nextTick(() => {
            map?.invalidateSize();
            renderMarkers();
        });
    }
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    layerGroup = null;
});
</script>

<template>
    <section class="company-country-card mb-3 overflow-hidden">
        <div class="grid lg:grid-cols-12">
            <div class="lg:col-span-8">
                <div class="flex items-center justify-between px-4 pt-4 pb-2">
                    <div>
                        <h3 class="company-country-title mb-0">{{ t('land_trips.cars_by_country') }}</h3>
                        <p class="company-country-help mb-0">{{ t('land_trips.cars_by_country_help') }}</p>
                    </div>
                </div>
                <div ref="mapEl" class="company-country-map"></div>
            </div>
            <div class="company-country-list lg:col-span-4 p-4">
                <p class="company-country-help fw-semibold mb-3">{{ t('land_trips.country_totals') }}</p>
                <div v-if="!countries.length" class="company-country-help">{{ t('land_trips.no_country_cars') }}</div>
                <ul v-else class="list-unstyled mb-0 d-grid gap-3">
                    <li v-for="country in countries" :key="country.id" class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="company-country-name">{{ country.label }}</div>
                            <div class="company-country-help">
                                {{ (country.locations ?? []).filter((item) => Number(item.count) > 0).map((item) => item.label).join(' · ') }}
                            </div>
                        </div>
                        <span class="company-country-count">{{ country.cars_count }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
