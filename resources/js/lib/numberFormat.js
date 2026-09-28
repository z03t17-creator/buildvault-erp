/**
 * Shared number / IQD formatting for React UI.
 * Grouping: en-US commas. No "$". Strip commas before submit/API.
 */

const GROUPED = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
    minimumFractionDigits: 0,
});

const GROUPED_DECIMALS = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 2,
    minimumFractionDigits: 0,
});

/**
 * Strip grouping chars and keep digits / one decimal / leading minus.
 * @param {string|number|null|undefined} value
 * @returns {string} raw numeric string ('' if empty)
 */
export function parseNumberInput(value) {
    if (value === null || value === undefined) {
        return '';
    }

    let raw = String(value).replace(/,/g, '').replace(/[^\d.\-]/g, '');

    const negative = raw.startsWith('-');
    raw = raw.replace(/-/g, '');
    const parts = raw.split('.');
    const intPart = parts[0] ?? '';
    const fracPart = parts.length > 1 ? parts.slice(1).join('') : null;

    let out = intPart;
    if (fracPart !== null) {
        out = `${intPart}.${fracPart}`;
    }
    if (negative && out !== '') {
        out = `-${out}`;
    }

    return out;
}

/**
 * @param {string|number|null|undefined} value
 * @returns {number}
 */
export function parseNumber(value) {
    const raw = parseNumberInput(value);
    if (raw === '' || raw === '-' || raw === '.') {
        return 0;
    }
    const n = Number(raw);
    return Number.isFinite(n) ? n : 0;
}

/**
 * Format for display with thousand separators.
 * @param {string|number|null|undefined} value
 * @param {{ decimals?: number }} [options]
 */
export function formatNumber(value, options = {}) {
    const decimals = options.decimals ?? 0;
    const n = parseNumber(value);
    if (decimals > 0) {
        return new Intl.NumberFormat('en-US', {
            maximumFractionDigits: decimals,
            minimumFractionDigits: 0,
        }).format(n);
    }
    return GROUPED.format(n);
}

/**
 * Live input formatting: commas while typing; preserves trailing decimal point.
 * @param {string|number|null|undefined} rawInput
 * @param {{ allowDecimals?: boolean }} [options]
 * @returns {{ display: string, raw: string }}
 */
export function formatNumberInput(rawInput, options = {}) {
    const allowDecimals = options.allowDecimals ?? false;
    let raw = parseNumberInput(rawInput);

    if (!allowDecimals) {
        raw = raw.replace(/\..*$/, '');
    }

    if (raw === '' || raw === '-') {
        return { display: raw === '-' ? '-' : '', raw: raw === '-' ? '' : raw };
    }

    const negative = raw.startsWith('-');
    const body = negative ? raw.slice(1) : raw;
    const hasTrailingDot = allowDecimals && body.endsWith('.') && body.indexOf('.') === body.length - 1;
    const [intPart = '', ...rest] = body.split('.');
    const frac = rest.join('');

    const intNum = intPart === '' ? 0 : Number(intPart);
    let display = GROUPED.format(Number.isFinite(intNum) ? intNum : 0);
    if (negative) {
        display = `-${display}`;
    }
    if (allowDecimals && (hasTrailingDot || frac !== '')) {
        display = `${display}.${frac}`;
    }

    let nextRaw = negative ? `-${intPart}` : intPart;
    if (allowDecimals && (hasTrailingDot || frac !== '')) {
        nextRaw = `${nextRaw}.${frac}`;
    } else if (allowDecimals && body.includes('.')) {
        nextRaw = `${nextRaw}.${frac}`;
    }

    return { display, raw: nextRaw };
}

/**
 * Format IQD amount for read-only display.
 * @param {string|number|null|undefined} value
 * @param {string|null} [label='IQD']
 * @param {{ decimals?: number }} [options]
 */
export function formatIqd(value, label = 'IQD', options = {}) {
    const formatted = formatNumber(value, options);
    if (label === null || label === '') {
        return formatted;
    }
    return `${formatted} ${label}`;
}

export { GROUPED, GROUPED_DECIMALS };
