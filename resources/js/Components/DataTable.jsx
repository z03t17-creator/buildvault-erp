/**
 * Shared scroll wrapper + table chrome for money / list pages.
 * Pass hideOnMobile when a MobileCardList sibling covers small screens.
 */
export default function DataTable({ children, minWidth = '40rem', caption, hideOnMobile = false }) {
    return (
        <div className={`bv-table-wrap ${hideOnMobile ? 'hidden md:block' : ''}`.trim()}>
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

export function Td({ children, align = 'start', className = '', muted = false, money = false, ...props }) {
    const alignClass =
        align === 'end' ? 'text-end' : align === 'center' ? 'text-center' : 'text-start';
    const moneyClass = money || align === 'end' ? 'font-mono tabular-nums' : '';
    return (
        <td
            {...props}
            dir={money || align === 'end' ? 'ltr' : undefined}
            className={
                `px-4 py-3.5 text-sm ${alignClass} ${moneyClass} ` +
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
