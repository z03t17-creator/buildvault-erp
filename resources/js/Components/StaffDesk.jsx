import { Link } from '@inertiajs/react';

export const deskFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm font-medium text-slate-100 shadow-sm focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-400/30';

export const deskMoneyClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-700 bg-slate-950 px-3 font-sans text-base font-semibold tabular-nums text-slate-100 shadow-sm focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-400/30';

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
            ? 'bg-teal-400/15 text-teal-100'
            : model === 'unit'
              ? 'bg-sky-400/15 text-sky-100'
              : 'bg-amber-400/15 text-amber-100';

    return (
        <span className={`inline-flex rounded-lg px-2 py-1 text-xs font-semibold ${tone}`}>
            {t(`staff_pay_${model}`)}
        </span>
    );
}

export function DeskRowActions({ editHref, onDelete, t }) {
    if (!editHref && !onDelete) return null;

    return (
        <div className="flex flex-wrap gap-2">
            {editHref ? (
                <Link
                    href={editHref}
                    className="inline-flex min-h-9 items-center rounded-lg border border-slate-300 px-2.5 text-xs font-semibold text-slate-800 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800"
                >
                    {t('edit')}
                </Link>
            ) : null}
            {onDelete ? (
                <button
                    type="button"
                    onClick={onDelete}
                    className="inline-flex min-h-9 items-center rounded-lg border border-rose-300 px-2.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-200 dark:hover:bg-rose-500/10"
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
            ? 'border-amber-400 bg-amber-400/15 text-amber-50 ring-2 ring-amber-400/30'
            : 'border-teal-400 bg-teal-400/15 text-teal-50 ring-2 ring-teal-400/30';
    const off = 'border-slate-700 bg-slate-950 text-slate-300 hover:border-slate-500';

    return (
        'min-h-[2.75rem] min-w-[5.5rem] flex-1 rounded-xl border px-2 text-sm font-semibold transition ' +
        (active ? on : off)
    );
}
