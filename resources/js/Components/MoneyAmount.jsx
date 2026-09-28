import { formatIqd, formatNumber } from '@/lib/numberFormat';

const SIZE_CLASS = {
    sm: 'text-sm font-semibold sm:text-base',
    md: 'text-base font-semibold sm:text-lg',
    lg: 'text-lg font-semibold sm:text-xl',
    xl: 'text-2xl font-semibold sm:text-3xl',
    hero: 'text-3xl font-semibold tracking-tight sm:text-4xl',
};

/**
 * Primary money/amount display — larger type, LTR digits for RTL locales.
 *
 * @param {{
 *   value: string|number|null|undefined,
 *   label?: string|null,
 *   size?: 'sm'|'md'|'lg'|'xl'|'hero',
 *   showLabel?: boolean,
 *   className?: string,
 *   accent?: boolean,
 * }} props
 */
export default function MoneyAmount({
    value,
    label = 'IQD',
    size = 'md',
    showLabel = true,
    className = '',
    accent = false,
}) {
    const text = showLabel ? formatIqd(value, label) : formatNumber(value);
    const sizeClass = SIZE_CLASS[size] || SIZE_CLASS.md;
    const hasCustomColor = /\btext-/.test(className);
    const colorClass = hasCustomColor
        ? ''
        : accent
          ? 'text-emerald-700 dark:text-emerald-400'
          : 'text-slate-900 dark:text-white';

    return (
        <span
            dir="ltr"
            className={`inline-block font-display tabular-nums ${sizeClass} ${colorClass} ${className}`.trim()}
        >
            {text}
        </span>
    );
}
