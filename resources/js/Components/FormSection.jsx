/**
 * Readable form field stack — consistent label spacing on create/edit screens.
 */
export default function FormSection({ children, className = '' }) {
    return <div className={`space-y-5 ${className}`.trim()}>{children}</div>;
}

export function FormField({ children, className = '' }) {
    return <div className={`min-w-0 ${className}`.trim()}>{children}</div>;
}

export function FormActions({ children, className = '' }) {
    return (
        <div className={`flex flex-wrap items-center gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-700/80 ${className}`.trim()}>
            {children}
        </div>
    );
}
