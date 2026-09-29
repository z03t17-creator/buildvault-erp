import { Link } from '@inertiajs/react';
import { NavIcon } from '@/lib/navIcons.jsx';

const TONE_CLASS = {
    emerald: 'border-emerald-200/80 bg-emerald-50/70 text-emerald-900 hover:border-emerald-400/70 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-100',
    teal: 'border-teal-200/80 bg-teal-50/70 text-teal-900 hover:border-teal-400/70 dark:border-teal-800/60 dark:bg-teal-950/30 dark:text-teal-100',
    amber: 'border-amber-200/80 bg-amber-50/70 text-amber-950 hover:border-amber-400/70 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-100',
    rose: 'border-rose-200/80 bg-rose-50/70 text-rose-900 hover:border-rose-400/70 dark:border-rose-800/60 dark:bg-rose-950/30 dark:text-rose-100',
    slate: 'border-slate-200/80 bg-white/80 text-slate-800 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100',
};

export default function DashboardQuickLink({ href, label, icon, tone = 'slate', badge }) {
    return (
        <Link
            href={href}
            className={
                'group flex min-h-[4.25rem] flex-col justify-between rounded-lg border px-3 py-3 transition ' +
                (TONE_CLASS[tone] || TONE_CLASS.slate)
            }
        >
            <div className="flex items-start justify-between gap-2">
                <NavIcon
                    name={icon}
                    className="h-5 w-5 shrink-0 text-current opacity-90 transition group-hover:scale-105"
                />
                {badge != null && badge !== 0 && (
                    <span className="rounded bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white">
                        {badge}
                    </span>
                )}
            </div>
            <span className="mt-2 text-sm font-semibold leading-snug">{label}</span>
        </Link>
    );
}
