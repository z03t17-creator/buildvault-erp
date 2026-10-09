import { NavIcon } from '@/lib/navIcons';

export default function EmptyState({ title, description, action, icon = 'apps' }) {
    return (
        <div className="bv-panel border-dashed px-6 py-14 text-center">
            <span className="bv-icon-chip mx-auto mb-4">
                <NavIcon name={icon} className="text-lg" />
            </span>
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
