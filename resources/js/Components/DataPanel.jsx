/**
 * Calm surface for tables / filter bars — shared across Vault, Payroll, Stock, etc.
 */
export default function DataPanel({
    title,
    subtitle,
    actions,
    children,
    className = '',
    padded = true,
}) {
    return (
        <section className={`bv-panel ${className}`.trim()}>
            {(title || actions) && (
                <div className="flex flex-col gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-700/80 sm:flex-row sm:items-center sm:justify-between">
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
                    {actions && (
                        <div className="flex flex-wrap items-center gap-2">{actions}</div>
                    )}
                </div>
            )}
            <div className={padded ? 'p-5' : ''}>{children}</div>
        </section>
    );
}
