export function normalizeHexColor(value, fallback = '#64748B') {
    const raw = String(value ?? '').trim();
    if (/^#[0-9A-Fa-f]{6}$/.test(raw)) {
        return raw.toUpperCase();
    }
    if (/^#[0-9A-Fa-f]{3}$/.test(raw)) {
        const [, a, b, c] = raw;
        return `#${a}${a}${b}${b}${c}${c}`.toUpperCase();
    }

    return fallback;
}

export function hexToRgb(hex) {
    const normalized = normalizeHexColor(hex).slice(1);
    return {
        r: parseInt(normalized.slice(0, 2), 16),
        g: parseInt(normalized.slice(2, 4), 16),
        b: parseInt(normalized.slice(4, 6), 16),
    };
}

export function withAlpha(hex, alpha) {
    const { r, g, b } = hexToRgb(hex);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/** High-contrast fill for cards / summary tiles */
export function statusSurfaceStyle(color, options = {}) {
    const hex = normalizeHexColor(color, options.fallback ?? '#64748B');
    const alpha = options.alpha ?? 0.42;

    if (options.solid) {
        return {
            background: hex,
            borderColor: hex,
            boxShadow: 'none',
            color: '#fff',
        };
    }

    return {
        background: `linear-gradient(145deg, ${withAlpha(hex, Math.min(alpha + 0.18, 0.72))} 0%, ${withAlpha(hex, alpha)} 55%, ${withAlpha(hex, 0.18)} 100%)`,
        borderColor: withAlpha(hex, 0.55),
        boxShadow: `inset 4px 0 0 ${hex}`,
        color: 'inherit',
    };
}

/** Table row tint */
export function statusRowStyle(color) {
    if (!color) {
        return {};
    }

    const hex = normalizeHexColor(color);

    return {
        '--land-status-color': hex,
        '--land-status-bg': withAlpha(hex, 0.38),
        '--land-status-bg-strong': withAlpha(hex, 0.52),
    };
}

export function useLandTripStatusColor() {
    return {
        normalizeHexColor,
        withAlpha,
        statusSurfaceStyle,
        statusRowStyle,
    };
}
