export const THEME_STORAGE_KEY = 'erp-theme';

export function resolveTheme(theme) {
    if (theme === 'dark' || theme === 'light') {
        return theme;
    }

    if (typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'dark';
    }

    return 'light';
}

export function readStoredTheme() {
    try {
        return localStorage.getItem(THEME_STORAGE_KEY) || 'light';
    } catch {
        return 'light';
    }
}

export function applyTheme(theme) {
    const resolved = resolveTheme(theme);
    const root = document.documentElement;

    root.setAttribute('data-theme', resolved);
    root.style.colorScheme = resolved;
}

export function persistTheme(theme) {
    const resolved = theme === 'dark' ? 'dark' : 'light';

    try {
        localStorage.setItem(THEME_STORAGE_KEY, resolved);
    } catch {
        // Ignore private-mode storage failures.
    }

    applyTheme(resolved);

    return resolved;
}
