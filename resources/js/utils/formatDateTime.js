/**
 * Format a datetime for display in a local timezone.
 * Do not use Date#toISOString() for user-facing date times (that is UTC).
 * Preformatted local strings (Y-m-d H:i) are returned as-is.
 */
export function formatDateTime(value, timeZone) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(value) && !/[TZ]/i.test(value)) {
        return value.slice(0, 16);
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const options = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    };

    if (timeZone) {
        options.timeZone = timeZone;
    }

    const parts = new Intl.DateTimeFormat('en-GB', options).formatToParts(date);
    const pick = (type) => parts.find((part) => part.type === type)?.value ?? '';

    return `${pick('year')}-${pick('month')}-${pick('day')} ${pick('hour')}:${pick('minute')}`;
}
