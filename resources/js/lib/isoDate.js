/**
 * Calendar-date helpers that stay on the civil YYYY-MM-DD calendar.
 * Never use Date#toISOString() for day identity — UTC shift breaks UTC+ locales
 * (e.g. Asia/Baghdad) where local midnight is the previous UTC day.
 */

function pad2(n) {
    return String(n).padStart(2, '0');
}

export function formatIsoDate(year, monthIndex, day) {
    return `${year}-${pad2(monthIndex + 1)}-${pad2(day)}`;
}

export function todayIsoDate() {
    const now = new Date();
    return formatIsoDate(now.getFullYear(), now.getMonth(), now.getDate());
}

/**
 * True when `value` is a real civil calendar day as YYYY-MM-DD.
 * Empty / null / undefined → false (callers treat optional fields separately).
 */
export function isValidIsoDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
        return false;
    }
    const [y, m, d] = value.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    return (
        !Number.isNaN(dt.getTime()) &&
        dt.getFullYear() === y &&
        dt.getMonth() === m - 1 &&
        dt.getDate() === d
    );
}

export function addDaysIso(iso, days) {
    if (!isValidIsoDate(iso)) {
        return null;
    }
    const [y, m, d] = iso.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() + days);
    return formatIsoDate(dt.getFullYear(), dt.getMonth(), dt.getDate());
}
