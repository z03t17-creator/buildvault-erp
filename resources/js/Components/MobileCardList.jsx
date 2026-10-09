import { Link } from '@inertiajs/react';

/**
 * Mobile stacked cards for dense tables.
 * Pair with DataTable: hide cards on md+, hide table wrap on small screens via CSS/props.
 */
export default function MobileCardList({ children, className = '' }) {
    return (
        <ul
            className={`bv-mobile-cards md:hidden divide-y divide-slate-200 dark:divide-slate-800 ${className}`.trim()}
        >
            {children}
        </ul>
    );
}

export function MobileCard({ href, title, subtitle, badge, rows = [], footer }) {
    const body = (
        <>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="truncate font-medium text-slate-900 dark:text-white">{title}</div>
                    {subtitle ? (
                        <div className="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            {subtitle}
                        </div>
                    ) : null}
                </div>
                {badge ? <div className="shrink-0">{badge}</div> : null}
            </div>
            {rows.length > 0 ? (
                <dl className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
                    {rows.map((row) => (
                        <div key={row.label} className={row.span === 2 ? 'col-span-2' : ''}>
                            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                {row.label}
                            </dt>
                            <dd
                                className={`mt-0.5 text-sm text-slate-800 dark:text-slate-100 ${
                                    row.money ? 'font-mono tabular-nums' : ''
                                }`.trim()}
                                dir={row.money ? 'ltr' : undefined}
                            >
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            ) : null}
            {footer ? <div className="mt-3">{footer}</div> : null}
        </>
    );

    const className =
        'block px-4 py-3.5 transition-colors hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20';

    return (
        <li>
            {href ? (
                <Link href={href} className={className}>
                    {body}
                </Link>
            ) : (
                <div className={className}>{body}</div>
            )}
        </li>
    );
}
