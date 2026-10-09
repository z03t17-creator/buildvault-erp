import { formatIqd, formatNumber } from '@/lib/numberFormat';

const SIZE_CLASS = {
    sm: 'text-base font-semibold sm:text-lg',
    md: 'text-lg font-semibold sm:text-xl',
    lg: 'text-xl font-semibold sm:text-2xl',
    xl: 'text-3xl font-bold sm:text-4xl',
    hero: 'text-4xl font-bold tracking-normal sm:text-5xl',
};

/**
 * Primary money/amount display — readable sans digits, LTR for RTL locales.
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
            className={`inline-block font-sans tracking-normal tabular-nums ${sizeClass} ${colorClass} ${className}`.trim()}
        >
            {text}
        </span>
    );
}
