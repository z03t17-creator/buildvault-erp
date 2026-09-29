/**
 * Consistent success / error / warning flash surfaces.
 */
const TONES = {
    success:
        'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100',
    error: 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100',
    warning:
        'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100',
    info: 'border-slate-300 bg-slate-50 text-slate-800 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200',
};

export default function FlashBanner({ tone = 'success', children, className = '' }) {
    if (!children) {
        return null;
    }

    return (
        <p
            role="status"
            className={`rounded-md border px-4 py-2.5 text-sm ${TONES[tone] || TONES.info} ${className}`.trim()}
        >
            {children}
        </p>
    );
}
