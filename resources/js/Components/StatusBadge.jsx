const STYLES = {
    present: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    late: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
    absent_unexcused: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    leave_paid: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    leave_sick: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    planning: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    on_hold: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
    completed: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    archived: 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    engineer: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    supervisor: 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300',
    subcontractor: 'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-200',
    laborer: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    pending: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    reconciled: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    holding: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
    matured: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    released: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    applied: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    waived: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};

function labelize(value) {
    if (!value) return '—';
    return String(value).replace(/_/g, ' ');
}

export default function StatusBadge({ status, className = '' }) {
    const key = status || '';
    const style = STYLES[key] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';

    return (
        <span
            className={`inline-flex items-center whitespace-nowrap rounded px-2 py-0.5 text-xs font-semibold capitalize tracking-wide ${style} ${className}`}
        >
            {labelize(status)}
        </span>
    );
}
