/**
 * Display-only money formatting: drop trailing .00 on whole numbers.
 * Stored values stay decimal; 30.50 keeps two places.
 */
export function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    const numeric = Number(String(value).replace(/,/g, '').trim());
    if (!Number.isFinite(numeric)) {
        return '0';
    }

    const rounded = Math.round(numeric * 100) / 100;

    if (Math.abs(rounded - Math.round(rounded)) < 0.0005) {
        return String(Math.round(rounded));
    }

    return rounded.toFixed(2);
}
