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
