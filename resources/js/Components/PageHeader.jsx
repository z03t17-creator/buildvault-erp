export default function PageHeader({ title, subtitle, actions }) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                <h1 className="font-display text-2xl font-semibold leading-tight tracking-tight text-slate-900 dark:text-white sm:text-[1.65rem]">
                    {title}
                </h1>
                {subtitle && (
                    <p className="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        {subtitle}
                    </p>
                )}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
