import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import {
    forwardRef,
    useCallback,
    useImperativeHandle,
    useRef,
} from 'react';

/**
 * Shared date field — opens the browser/OS calendar on click (and icon tap).
 * Always stores ISO `YYYY-MM-DD`. Pair with form `noValidate` so browsers
 * never show English HTML5 validation bubbles; keep app-side checks instead.
 */
const DateInput = forwardRef(function DateInput(
    {
        id,
        name,
        value = '',
        onChange,
        onValueChange,
        onBlur,
        className = '',
        required = false,
        disabled = false,
        min,
        max,
        'aria-label': ariaLabel,
        openOnFocus = true,
        ...rest
    },
    ref,
) {
    const inputRef = useRef(null);
    const t = useTranslations();
    const openLabel = ariaLabel || t('date_open_calendar');

    const openPicker = useCallback(() => {
        const el = inputRef.current;
        if (!el || disabled) {
            return;
        }
        try {
            if (typeof el.showPicker === 'function') {
                el.showPicker();
                return;
            }
        } catch {
            // showPicker can throw if not triggered by a user gesture.
        }
        el.focus({ preventScroll: true });
    }, [disabled]);

    useImperativeHandle(ref, () => ({
        focus: () => inputRef.current?.focus(),
        showPicker: () => openPicker(),
        el: () => inputRef.current,
    }));

    const handleChange = (event) => {
        onChange?.(event);
        onValueChange?.(event.target.value);
    };

    return (
        <div className="relative">
            <button
                type="button"
                tabIndex={-1}
                disabled={disabled}
                onClick={openPicker}
                className="absolute inset-y-0 start-0 z-10 flex w-10 items-center justify-center text-slate-400 transition hover:text-slate-700 disabled:opacity-50 dark:hover:text-slate-200"
                aria-label={openLabel}
            >
                <NavIcon name="calendar" className="text-sm" />
            </button>
            <input
                {...rest}
                ref={inputRef}
                id={id}
                name={name}
                type="date"
                dir="ltr"
                value={value || ''}
                min={min}
                max={max}
                disabled={disabled}
                // Keep required off the DOM control so browsers never emit
                // English HTML5 bubbles; callers validate in JS / server.
                required={false}
                aria-required={required || undefined}
                aria-label={openLabel}
                data-date-input="true"
                onChange={handleChange}
                onBlur={onBlur}
                onClick={openPicker}
                onFocus={() => {
                    if (openOnFocus) {
                        openPicker();
                    }
                }}
                className={
                    'bv-date-input block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white ps-10 pe-3 text-sm font-medium tabular-nums text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/25 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 ' +
                    className
                }
            />
        </div>
    );
});

export default DateInput;
