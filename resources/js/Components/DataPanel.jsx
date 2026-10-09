/**
 * Calm surface for tables / filter bars — Judi-style hierarchy.
 */
export default function DataPanel({
    title,
    subtitle,
    actions,
    children,
    className = '',
    padded = true,
    icon = null,
}) {
    return (
        <section className={`bv-panel ${className}`.trim()}>
            {(title || actions) && (
                <div className="flex flex-col gap-3 border-b border-slate-200/70 px-4 py-4 dark:border-slate-700/70 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div className="flex min-w-0 items-start gap-3">
                        {icon && <span className="bv-icon-chip mt-0.5">{icon}</span>}
                        <div className="min-w-0">
                            {title && (
                                <h3 className="font-display text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                                    {title}
                                </h3>
                            )}
                            {subtitle && (
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                    </div>
                    {actions && (
                        <div className="flex flex-wrap items-center gap-2">{actions}</div>
                    )}
                </div>
            )}
            <div className={padded ? 'p-4 sm:p-5' : ''}>{children}</div>
        </section>
    );
}
