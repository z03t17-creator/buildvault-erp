export default function EmptyState({ title, description, action }) {
    return (
        <div className="rounded-lg border border-dashed border-slate-300/90 bg-white/40 px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900/30">
            <p className="font-display text-lg font-semibold text-slate-800 dark:text-slate-100">
                {title}
            </p>
            {description && (
                <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {description}
                </p>
            )}
            {action && <div className="mt-5 flex justify-center">{action}</div>}
        </div>
    );
}
