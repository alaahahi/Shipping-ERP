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

const CARTO_DARK_NOLABELS = 'https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png';
const CARTO_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>';

const mappable = (rows) => rows.filter((row) => row.latitude != null && row.longitude != null && Number(row.cars_count) > 0);

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const countryName = (country) => country.country_label || country.label || country.iso_code || '';

const carHaulerMarkup = (size, count, uid) => `
    <div class="company-country-car-pin" style="--pin-size:${size}px">
        <svg viewBox="0 0 128 88" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <linearGradient id="${uid}-body" x1="8" y1="12" x2="120" y2="78" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#5eead4"/>
                    <stop offset="42%" stop-color="#14b8a6"/>
                    <stop offset="100%" stop-color="#0f766e"/>
                </linearGradient>
                <linearGradient id="${uid}-cab" x1="82" y1="18" x2="122" y2="62" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#2dd4bf"/>
                    <stop offset="100%" stop-color="#115e59"/>
                </linearGradient>
                <linearGradient id="${uid}-car" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#ecfeff"/>
                    <stop offset="100%" stop-color="#5eead4"/>
                </linearGradient>
                <linearGradient id="${uid}-glass" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#f8fafc" stop-opacity=".95"/>
                    <stop offset="100%" stop-color="#67e8f9" stop-opacity=".75"/>
                </linearGradient>
                <radialGradient id="${uid}-halo" cx="50%" cy="46%" r="52%">
                    <stop offset="0%" stop-color="#2dd4bf" stop-opacity=".28"/>
                    <stop offset="70%" stop-color="#0f766e" stop-opacity=".08"/>
                    <stop offset="100%" stop-color="#0f766e" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <ellipse cx="64" cy="80" rx="36" ry="5.5" fill="#020617" opacity=".45"/>
            <circle cx="64" cy="44" r="40" fill="url(#${uid}-halo)"/>
            <g stroke-linejoin="round" stroke-linecap="round">
                <path d="M10 58h72" stroke="#042f2e" stroke-width="3" opacity=".35"/>
                <rect x="8" y="50" width="76" height="10" rx="2.4" fill="url(#${uid}-body)" stroke="#99f6e4" stroke-width="1.2"/>
                <path d="M12 50v-6h68v6" fill="none" stroke="#99f6e4" stroke-width="1.4" opacity=".85"/>
                <g transform="translate(12 28)">
                    <path d="M3 18h30c1.4 0 2.4-1.1 2.6-2.4L37 10c.4-1.6-.7-3.2-2.4-3.4H16.5c-1.2 0-2.3.6-2.9 1.6L10 14H3c-.8 0-1.4.7-1.4 1.5v1c0 .8.6 1.5 1.4 1.5z" fill="url(#${uid}-car)" stroke="#ccfbf1" stroke-width="1"/>
                    <path d="M16 8.2h16.2c.9 0 1.5.8 1.3 1.7l-.8 3.1H14.6l1.4-4.8z" fill="url(#${uid}-glass)"/>
                </g>
                <g transform="translate(46 22)">
                    <path d="M3 18h30c1.4 0 2.4-1.1 2.6-2.4L37 10c.4-1.6-.7-3.2-2.4-3.4H16.5c-1.2 0-2.3.6-2.9 1.6L10 14H3c-.8 0-1.4.7-1.4 1.5v1c0 .8.6 1.5 1.4 1.5z" fill="url(#${uid}-car)" stroke="#ccfbf1" stroke-width="1"/>
                    <path d="M16 8.2h16.2c.9 0 1.5.8 1.3 1.7l-.8 3.1H14.6l1.4-4.8z" fill="url(#${uid}-glass)"/>
                </g>
                <path d="M84 60V34c0-5.2 3.8-9 9.2-9h12.3L118 40.5V60z" fill="url(#${uid}-cab)" stroke="#99f6e4" stroke-width="1.3"/>
                <path d="M95 27.4h8.8L112 39H95z" fill="url(#${uid}-glass)" stroke="#ecfeff" stroke-width=".8"/>
                <rect x="116.2" y="43" width="5.4" height="5.2" rx="1" fill="#f0fdfa"/>
                <rect x="86" y="44" width="8" height="4" rx="1" fill="#042f2e" opacity=".28"/>
            </g>
            <g fill="#020617" stroke="#99f6e4" stroke-width="1.3">
                <circle cx="22" cy="64.5" r="6.2"/>
                <circle cx="40" cy="64.5" r="6.2"/>
                <circle cx="62" cy="64.5" r="6.2"/>
                <circle cx="106" cy="64.5" r="6.2"/>
            </g>
            <g fill="#5eead4">
                <circle cx="22" cy="64.5" r="2.5"/>
                <circle cx="40" cy="64.5" r="2.5"/>
                <circle cx="62" cy="64.5" r="2.5"/>
                <circle cx="106" cy="64.5" r="2.5"/>
            </g>
        </svg>
        <span class="company-country-car-count">${count}</span>
    </div>
`;

const renderMarkers = () => {
    if (!map || !layerGroup) {
        return;
    }

    layerGroup.clearLayers();
    const points = [];

    mappable(props.countries).forEach((country) => {
        const count = Number(country.cars_count) || 0;
        const size = Math.round(Math.min(64, 44 + Math.sqrt(count) * 3.4));
        const name = countryName(country);
        const locations = (country.locations ?? [])
            .filter((item) => Number(item.count) > 0)
            .map((item) => `${escapeHtml(item.label)}: ${item.count}`)
            .join('<br>');

        const marker = L.marker([country.latitude, country.longitude], {
            icon: L.divIcon({
                className: 'company-country-car-marker',
                iconSize: [size, size],
                iconAnchor: [size / 2, size / 2],
                popupAnchor: [0, -size / 2],
                html: carHaulerMarkup(size, count, `ch-${country.id}`),
            }),
        }).bindPopup(
            `<strong>${escapeHtml(name)}</strong><br>${t('land_trips.map_cars_count', { count })}`
            + (locations ? `<br><br>${locations}` : '')
        );

        if (name) {
            marker.bindTooltip(escapeHtml(name), {
                permanent: true,
                direction: 'top',
                offset: L.point(0, -size / 2 - 4),
                opacity: 1,
                className: 'company-country-map-label',
            });
        }

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
    L.tileLayer(CARTO_DARK_NOLABELS, {
        attribution: CARTO_ATTRIBUTION,
        subdomains: 'abcd',
        maxZoom: 19,
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
