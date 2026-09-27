/**
 * ZHAKO status tones:
 * - emerald: inflows / healthy / present
 * - crimson (rose): expenses / penalties / rejected
 * - amber: retention / insurance / pending / late
 */

const EMERALD =
    'bg-emerald-100 text-emerald-800 ring-emerald-200/80 dark:bg-emerald-900/40 dark:text-emerald-300 dark:ring-emerald-800/60';
const CRIMSON =
    'bg-rose-100 text-rose-800 ring-rose-200/80 dark:bg-rose-900/40 dark:text-rose-300 dark:ring-rose-800/60';
const AMBER =
    'bg-amber-100 text-amber-900 ring-amber-200/80 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800/60';
const SLATE =
    'bg-slate-100 text-slate-700 ring-slate-200/80 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700';
const SKY =
    'bg-sky-100 text-sky-800 ring-sky-200/80 dark:bg-sky-900/40 dark:text-sky-300 dark:ring-sky-800/60';

const STYLES = {
    // Attendance / project
    present: EMERALD,
    late: AMBER,
    absent_unexcused: CRIMSON,
    leave_paid: SKY,
    leave_sick: SLATE,
    planning: SLATE,
    active: EMERALD,
    on_hold: AMBER,
    completed: SKY,
    archived: SLATE,

    // Roles (neutral)
    engineer: 'bg-indigo-100 text-indigo-800 ring-indigo-200/80 dark:bg-indigo-900/40 dark:text-indigo-300 dark:ring-indigo-800/60',
    supervisor: 'bg-teal-100 text-teal-800 ring-teal-200/80 dark:bg-teal-900/40 dark:text-teal-300 dark:ring-teal-800/60',
    subcontractor: 'bg-orange-100 text-orange-900 ring-orange-200/80 dark:bg-orange-900/40 dark:text-orange-200 dark:ring-orange-800/60',
    laborer: SLATE,

    // Payouts
    pending: AMBER,
    approved: EMERALD,
    reconciled: SKY,
    rejected: CRIMSON,

    // Insurance / retention
    holding: AMBER,
    matured: AMBER,
    released: EMERALD,

    // Penalties
    applied: CRIMSON,
    waived: SLATE,

    // Imports
    processing: AMBER,
    failed: CRIMSON,
    rolled_back: SLATE,
    imported: EMERALD,
    invalid: CRIMSON,
    valid: EMERALD,
    skipped: SLATE,

    // Ledger / money cues
    deposit: EMERALD,
    inflow: EMERALD,
    allocation: EMERALD,
    withdrawal: CRIMSON,
    expenses: CRIMSON,
    penalty: CRIMSON,
    retention: AMBER,
    insurance: AMBER,
    profit: EMERALD,
    healthy: EMERALD,
    warning: AMBER,
    critical: CRIMSON,
};

function labelize(value) {
    if (!value) return '—';
    return String(value).replace(/_/g, ' ');
}

export default function StatusBadge({ status, className = '' }) {
    const key = status || '';
    const style = STYLES[key] || SLATE;

    return (
        <span
            className={`bv-badge inline-flex items-center whitespace-nowrap rounded-sm px-2 py-0.5 text-xs font-semibold capitalize tracking-wide ring-1 ${style} ${className}`}
        >
            {labelize(status)}
        </span>
    );
}
