import { NavIcon } from '@/lib/navIcons.jsx';
import { Link } from '@inertiajs/react';

export default function SidebarNavLink({
    active = false,
    badge = null,
    icon = null,
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'group flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm font-medium transition ' +
                (active
                    ? 'bg-emerald-600/10 text-emerald-800 ring-1 ring-emerald-500/25 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/80 dark:hover:text-slate-100')
            }
        >
            <span className="flex min-w-0 items-center gap-2.5">
                {icon && (
                    <NavIcon
                        name={icon}
                        className={
                            'h-4 w-4 shrink-0 ' +
                            (active
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300')
                        }
                    />
                )}
                <span className="min-w-0 truncate">{children}</span>
            </span>
            {badge != null && (
                <span className="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded bg-amber-500 px-1 text-[10px] font-bold text-white">
                    {badge}
                </span>
            )}
        </Link>
    );
}
