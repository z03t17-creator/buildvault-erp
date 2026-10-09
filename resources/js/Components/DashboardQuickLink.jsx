import { IconChip } from '@/lib/navIcons';
import { Link } from '@inertiajs/react';

const TONE_BORDER = {
    emerald: 'hover:border-teal-400/70 dark:hover:border-teal-500/40',
    teal: 'hover:border-teal-400/70 dark:hover:border-teal-500/40',
    amber: 'hover:border-amber-400/70 dark:hover:border-amber-500/40',
    rose: 'hover:border-rose-400/70 dark:hover:border-rose-500/40',
    slate: 'hover:border-slate-300 dark:hover:border-slate-600',
};

export default function DashboardQuickLink({ href, label, icon, tone = 'slate', badge }) {
    return (
        <Link
            href={href}
            className={
                'bv-card group flex min-h-[5rem] flex-col justify-between p-3.5 transition hover:-translate-y-0.5 hover:shadow-judi ' +
                (TONE_BORDER[tone] || TONE_BORDER.slate)
            }
        >
            <div className="flex items-start justify-between gap-2">
                <IconChip name={icon} tone={tone === 'emerald' ? 'teal' : tone} />
                {badge != null && badge !== 0 && (
                    <span className="rounded-md bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white">
                        {badge}
                    </span>
                )}
            </div>
            <span className="mt-3 text-sm font-semibold leading-snug text-slate-800 dark:text-slate-100">
                {label}
            </span>
        </Link>
    );
}
