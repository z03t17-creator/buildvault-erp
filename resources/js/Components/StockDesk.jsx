import { Link } from '@inertiajs/react';

export const stockFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/30';

export const stockMoneyClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-base font-semibold tabular-nums text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/30';

export function stockStatusOf(item) {
    if (item?.is_out_of_stock || Number(item?.quantity) <= 0) return 'out';
    if (item?.is_low_stock) return 'low';
    return 'ok';
}

export function StockStatusBadge({ item, t }) {
    const status = stockStatusOf(item);
    const tone =
        status === 'out'
            ? 'bg-rose-500/15 text-rose-800 ring-rose-500/30 dark:bg-rose-500/20 dark:text-rose-100 dark:ring-rose-400/40'
            : status === 'low'
              ? 'bg-amber-500/15 text-amber-900 ring-amber-500/30 dark:bg-amber-500/20 dark:text-amber-100 dark:ring-amber-400/40'
              : 'bg-emerald-500/15 text-emerald-800 ring-emerald-500/30 dark:bg-emerald-500/20 dark:text-emerald-100 dark:ring-emerald-400/40';
    const label =
        status === 'out' ? t('stock_out') : status === 'low' ? t('stock_low') : t('stock_ok');

    return (
        <span className={`inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold ring-1 ${tone}`}>
            {label}
        </span>
    );
}

export function StockStatCard({ label, value, hint, tone = 'emerald', href = null, badge = null }) {
    const iconTone =
        tone === 'amber'
            ? 'bg-amber-500/15 text-amber-800 dark:bg-amber-400/15 dark:text-amber-100'
            : tone === 'rose'
              ? 'bg-rose-500/15 text-rose-800 dark:bg-rose-400/15 dark:text-rose-100'
              : tone === 'sky'
                ? 'bg-sky-500/15 text-sky-800 dark:bg-sky-400/15 dark:text-sky-100'
                : 'bg-emerald-500/15 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-100';

    const body = (
        <div className="bv-card relative px-4 py-3.5">
            {badge != null && Number(badge) > 0 ? (
                <span className="absolute end-3 top-3 inline-flex min-w-6 items-center justify-center rounded-full bg-amber-400 px-1.5 text-xs font-bold text-slate-950">
                    {badge}
                </span>
            ) : null}
            <div className="flex items-start gap-3">
                <span className={`inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${iconTone}`}>
                    ◆
                </span>
                <div className="min-w-0">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {label}
                    </p>
                    <p
                        dir="ltr"
                        className="mt-1 font-sans text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-100"
                    >
                        {value}
                    </p>
                    {hint ? (
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
                    ) : null}
                </div>
            </div>
        </div>
    );

    if (href) {
        return (
            <Link href={href} className="block transition hover:opacity-95">
                {body}
            </Link>
        );
    }

    return body;
}

export function stockSegmentClass(active, tone = 'emerald') {
    const on =
        tone === 'amber'
            ? 'border-amber-500 bg-amber-500/15 text-amber-950 ring-2 ring-amber-500/25 dark:border-amber-400 dark:bg-amber-400/15 dark:text-amber-50 dark:ring-amber-400/30'
            : 'border-emerald-500 bg-emerald-500/15 text-emerald-950 ring-2 ring-emerald-500/25 dark:border-emerald-400 dark:bg-emerald-400/15 dark:text-emerald-50 dark:ring-emerald-400/30';
    const off =
        'border-slate-200 bg-white text-slate-600 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-slate-500';

    return (
        'min-h-[2.75rem] min-w-[5.5rem] flex-1 rounded-xl border px-2 text-sm font-semibold transition ' +
        (active ? on : off)
    );
}
