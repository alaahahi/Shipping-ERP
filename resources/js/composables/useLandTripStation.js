import { useI18n } from 'vue-i18n';

export function useLandTripStation() {
    const { t, te, locale } = useI18n();

    const stationLabel = (status) => {
        if (!status || (status.id == null && !status.code && !status.location_status_code)) {
            return t('land_trips.unspecified_location');
        }

        const code = status.code || status.location_status_code;
        const key = code ? `land_trips.stations.${code}` : '';
        if (key && te(key)) {
            return t(key);
        }

        if (locale.value === 'ckb') {
            return status.name_ckb || status.name_ar || status.name || status.label || status.location_status_label || status.status_text || t('land_trips.unspecified_location');
        }

        if (locale.value === 'ar') {
            return status.name_ar || status.name_ckb || status.name || status.label || status.location_status_label || status.status_text || t('land_trips.unspecified_location');
        }

        return status.name || status.name_ckb || status.name_ar || status.label || status.location_status_label || status.status_text || t('land_trips.unspecified_location');
    };

    const toneLabel = (tone) => {
        const key = `settings.row_tones.${tone}`;

        return te(key) ? t(key) : tone;
    };

    return { stationLabel, toneLabel };
}
