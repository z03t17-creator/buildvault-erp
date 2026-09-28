import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import { formatNumberInput, parseNumberInput } from '@/lib/numberFormat';

/**
 * Money amount input with live thousand separators.
 * Form/API value is always the raw numeric string (no commas).
 *
 * @param {{
 *   value: string|number,
 *   onValueChange: (raw: string) => void,
 *   allowDecimals?: boolean,
 *   className?: string,
 *   id?: string,
 *   name?: string,
 *   required?: boolean,
 *   disabled?: boolean,
 *   min?: string|number,
 *   placeholder?: string,
 *   isFocused?: boolean,
 * }} props
 */
const MoneyInput = forwardRef(function MoneyInput(
    {
        value = '',
        onValueChange,
        allowDecimals = false,
        className = '',
        isFocused = false,
        onChange,
        ...props
    },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    const raw = parseNumberInput(value);
    const { display } = formatNumberInput(raw === '' ? '' : raw, { allowDecimals });

    const handleChange = (e) => {
        const next = formatNumberInput(e.target.value, { allowDecimals });
        onValueChange?.(next.raw);
        onChange?.(e, next.raw);
    };

    return (
        <input
            {...props}
            ref={localRef}
            type="text"
            inputMode={allowDecimals ? 'decimal' : 'numeric'}
            dir="ltr"
            autoComplete="off"
            value={display}
            onChange={handleChange}
            className={
                'rounded-md border-slate-300 font-display text-base font-semibold tabular-nums shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 sm:text-lg ' +
                className
            }
        />
    );
});

export default MoneyInput;
