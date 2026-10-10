import { Link } from '@inertiajs/react';

export const deskFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-teal-400 dark:focus:ring-teal-400/30';

export const deskMoneyClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-base font-semibold tabular-nums text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-teal-400 dark:focus:ring-teal-400/30';

export function payModelOf(person) {
    if (person?.pay_model) return person.pay_model;
    if (person?.kind === 'salary') return 'monthly';
    if (person?.kind === 'unit') return 'unit';
    return 'daily';
}

export function PayModelChip({ payModel, kind, t }) {
    const model = payModelOf({ pay_model: payModel, kind });
    const tone =
        model === 'monthly'
            ? 'bg-teal-500/15 text-teal-800 dark:bg-teal-400/15 dark:text-teal-100'
            : model === 'unit'
              ? 'bg-sky-500/15 text-sky-800 dark:bg-sky-400/15 dark:text-sky-100'
              : 'bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-100';

    return (
        <span className={`inline-flex rounded-lg px-2 py-1 text-xs font-semibold ${tone}`}>
            {t(`staff_pay_${model}`)}
        </span>
    );
}

export function DeskRowActions({ editHref, onDelete, t }) {
    if (!editHref && !onDelete) return null;

    return (
        <div className="flex flex-wrap items-center gap-2">
            {editHref ? (
                <Link
                    href={editHref}
                    className="inline-flex min-h-10 items-center rounded-xl bg-teal-500/15 px-3 text-sm font-semibold text-teal-800 ring-1 ring-teal-500/30 hover:bg-teal-500/25 dark:bg-teal-500/20 dark:text-teal-100 dark:ring-teal-400/40 dark:hover:bg-teal-500/30"
                >
                    {t('edit')}
                </Link>
            ) : null}
            {onDelete ? (
                <button
                    type="button"
                    onClick={onDelete}
                    className="inline-flex min-h-10 items-center rounded-xl bg-rose-500/15 px-3 text-sm font-semibold text-rose-800 ring-1 ring-rose-500/30 hover:bg-rose-500/25 dark:bg-rose-500/20 dark:text-rose-100 dark:ring-rose-400/40 dark:hover:bg-rose-500/30"
                >
                    {t('delete')}
                </button>
            ) : null}
        </div>
    );
}

export function segmentClass(active, tone = 'teal') {
    const on =
        tone === 'amber'
            ? 'border-amber-500 bg-amber-500/15 text-amber-950 ring-2 ring-amber-500/25 dark:border-amber-400 dark:bg-amber-400/15 dark:text-amber-50 dark:ring-amber-400/30'
            : 'border-teal-500 bg-teal-500/15 text-teal-950 ring-2 ring-teal-500/25 dark:border-teal-400 dark:bg-teal-400/15 dark:text-teal-50 dark:ring-teal-400/30';
    const off =
        'border-slate-200 bg-white text-slate-600 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-slate-500';

    return (
        'min-h-[2.75rem] min-w-[5.5rem] flex-1 rounded-xl border px-2 text-sm font-semibold transition ' +
        (active ? on : off)
    );
}
