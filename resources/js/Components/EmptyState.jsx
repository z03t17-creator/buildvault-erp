export default function EmptyState({ title, description, action }) {
    return (
        <div className="border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700">
            <p className="font-display text-lg font-semibold text-slate-800 dark:text-slate-100">
                {title}
            </p>
            {description && (
                <p className="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
                    {description}
                </p>
            )}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
