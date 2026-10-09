/**
 * Form field layout — stacked by default; pass cols={2|3} for dense multi-column
 * short fields (stack on mobile). Mark full-width fields with FormField sm:col-span-*.
 */
export default function FormSection({ children, className = '', cols = 1 }) {
    const layout =
        cols >= 3
            ? 'grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3'
            : cols === 2
              ? 'grid gap-x-4 gap-y-3 sm:grid-cols-2'
              : 'space-y-5';

    return <div className={`${layout} ${className}`.trim()}>{children}</div>;
}

export function FormField({ children, className = '' }) {
    return <div className={`min-w-0 ${className}`.trim()}>{children}</div>;
}

export function FormActions({ children, className = '' }) {
    return (
        <div
            className={`flex flex-wrap items-center gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-700/80 ${className}`.trim()}
        >
            {children}
        </div>
    );
}
