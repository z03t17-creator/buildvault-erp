import { NavIcon } from '@/lib/navIcons';
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
                'bv-nav-item group ' +
                (active ? 'bv-nav-item-active' : 'bv-nav-item-idle')
            }
        >
            {icon && (
                <span
                    className={
                        'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[0.95rem] transition ' +
                        (active
                            ? 'bg-teal-600 text-white dark:bg-teal-500 dark:text-slate-950'
                            : 'bg-slate-100 text-slate-500 group-hover:bg-teal-50 group-hover:text-teal-700 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-teal-500/10 dark:group-hover:text-teal-300')
                    }
                >
                    <NavIcon name={icon} solid={active} />
                </span>
            )}
            <span className="min-w-0 flex-1 truncate">{children}</span>
            {badge != null && (
                <span className="inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-md bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white">
                    {badge}
                </span>
            )}
        </Link>
    );
}
