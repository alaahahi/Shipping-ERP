import { createI18n } from 'vue-i18n';
import ar from '../lang/ar.json';
import ckb from '../lang/ckb.json';
import en from '../lang/en.json';

export const localeMessages = {
    ar,
    en,
    ckb,
};

export const rtlLocales = ['ar', 'ckb'];

export function createAppI18n(locale = 'ar') {
    const resolved = localeMessages[locale] ? locale : 'ar';

    return createI18n({
        legacy: false,
        globalInjection: true,
        locale: resolved,
        fallbackLocale: resolved === 'ckb' ? 'ar' : 'en',
        messages: localeMessages,
    });
}

export function applyDocumentLocale(locale) {
    const resolved = localeMessages[locale] ? locale : 'ar';
    const isRtl = rtlLocales.includes(resolved);

    document.documentElement.setAttribute('lang', resolved);
    document.documentElement.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
    document.body?.classList.toggle('erp-rtl', isRtl);
}
