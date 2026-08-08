/**
 * Display-only money formatting: thousand separators, drop trailing .00 on whole numbers.
 * Stored values stay decimal; 30.50 keeps two places. Negatives stay prefixed: -111,482.
 */
export function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '0';
    }

    const numeric = Number(String(value).replace(/,/g, '').trim());
    if (!Number.isFinite(numeric)) {
        return '0';
    }

    const rounded = Math.round(numeric * 100) / 100 || 0;
    const isWhole = Math.abs(rounded - Math.round(rounded)) < 0.0005;

    return rounded.toLocaleString('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: isWhole ? 0 : 2,
    });
}
