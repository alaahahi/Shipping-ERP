import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { applyDocumentLocale, rtlLocales } from '@/i18n';

export function useLocale() {
    const page = usePage();
    const { locale, t } = useI18n();

    const currentLocale = computed(() => page.props.appSettings?.locale || locale.value || 'ar');
    const isRtl = computed(() => rtlLocales.includes(currentLocale.value));

    const syncFromSettings = () => {
        const next = page.props.appSettings?.locale || 'ar';
        locale.value = next;
        applyDocumentLocale(next);
    };

    return {
        t,
        locale,
        currentLocale,
        isRtl,
        syncFromSettings,
    };
}
