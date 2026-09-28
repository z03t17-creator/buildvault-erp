/**
 * Shared scroll wrapper + table chrome for money / list pages.
 */
export default function DataTable({ children, minWidth = '40rem', caption }) {
    return (
        <div className="bv-table-wrap">
            <table className="bv-table" style={{ minWidth }}>
                {caption && (
                    <caption className="sr-only">{caption}</caption>
                )}
                {children}
            </table>
        </div>
    );
}

export function Th({ children, align = 'start', className = '', ...props }) {
    const alignClass =
        align === 'end' ? 'text-end' : align === 'center' ? 'text-center' : 'text-start';
    return (
        <th
            {...props}
            className={`px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 ${alignClass} ${className}`.trim()}
        >
            {children}
        </th>
    );
}

export function Td({ children, align = 'start', className = '', muted = false, ...props }) {
    const alignClass =
        align === 'end' ? 'text-end' : align === 'center' ? 'text-center' : 'text-start';
    return (
        <td
            {...props}
            className={
                `px-4 py-3.5 text-sm ${alignClass} ` +
                (muted
                    ? 'text-slate-500 dark:text-slate-400 '
                    : 'text-slate-800 dark:text-slate-100 ') +
                className
            }
        >
            {children}
        </td>
    );
}
