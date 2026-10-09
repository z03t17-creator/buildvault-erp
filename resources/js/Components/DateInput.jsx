import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import {
    forwardRef,
    useCallback,
    useEffect,
    useId,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

function pad2(n) {
    return String(n).padStart(2, '0');
}

function toIso(year, monthIndex, day) {
    return `${year}-${pad2(monthIndex + 1)}-${pad2(day)}`;
}

function parseIso(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) {
        return null;
    }
    const [y, m, d] = value.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    if (
        Number.isNaN(dt.getTime()) ||
        dt.getFullYear() !== y ||
        dt.getMonth() !== m - 1 ||
        dt.getDate() !== d
    ) {
        return null;
    }
    return { year: y, month: m - 1, day: d };
}

function daysInMonth(year, monthIndex) {
    return new Date(year, monthIndex + 1, 0).getDate();
}

function monthLabel(year, monthIndex, locale) {
    try {
        return new Intl.DateTimeFormat(locale || undefined, {
            month: 'long',
            year: 'numeric',
        }).format(new Date(year, monthIndex, 1));
    } catch {
        return `${year}-${pad2(monthIndex + 1)}`;
    }
}

function isoInRange(iso, min, max) {
    if (min && iso < min) {
        return false;
    }
    if (max && iso > max) {
        return false;
    }
    return true;
}

/**
 * Mobile-first date field — tap opens a calm bottom-sheet calendar.
 * Always displays and stores ISO `YYYY-MM-DD` (never OS locale MM/DD/YYYY).
 * Pair with form `noValidate` so browsers never show English HTML5 bubbles.
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
        openOnFocus = false,
        ...rest
    },
    ref,
) {
    const t = useTranslations();
    const autoId = useId();
    const fieldId = id || autoId;
    const triggerRef = useRef(null);
    const [open, setOpen] = useState(false);
    const parsed = parseIso(value);
    const today = useMemo(() => {
        const now = new Date();
        return {
            year: now.getFullYear(),
            month: now.getMonth(),
            day: now.getDate(),
            iso: toIso(now.getFullYear(), now.getMonth(), now.getDate()),
        };
    }, []);

    const [viewYear, setViewYear] = useState(parsed?.year ?? today.year);
    const [viewMonth, setViewMonth] = useState(parsed?.month ?? today.month);

    const emit = useCallback(
        (iso) => {
            onValueChange?.(iso);
            if (onChange) {
                onChange({
                    target: { name, value: iso, id: fieldId },
                    currentTarget: { name, value: iso, id: fieldId },
                });
            }
        },
        [fieldId, name, onChange, onValueChange],
    );

    const openSheet = useCallback(() => {
        if (disabled) {
            return;
        }
        const base = parseIso(value) || today;
        setViewYear(base.year);
        setViewMonth(base.month);
        setOpen(true);
    }, [disabled, today, value]);

    const closeSheet = useCallback(() => {
        setOpen(false);
        onBlur?.({ target: triggerRef.current });
    }, [onBlur]);

    useImperativeHandle(ref, () => ({
        focus: () => triggerRef.current?.focus(),
        showPicker: () => openSheet(),
        el: () => triggerRef.current,
    }));

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        const onKey = (e) => {
            if (e.key === 'Escape') {
                closeSheet();
            }
        };
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = prev;
            window.removeEventListener('keydown', onKey);
        };
    }, [closeSheet, open]);

    const selectDay = (day) => {
        const iso = toIso(viewYear, viewMonth, day);
        if (!isoInRange(iso, min, max)) {
            return;
        }
        emit(iso);
        setOpen(false);
    };

    const shiftMonth = (delta) => {
        const d = new Date(viewYear, viewMonth + delta, 1);
        setViewYear(d.getFullYear());
        setViewMonth(d.getMonth());
    };

    const setYear = (year) => {
        setViewYear(year);
    };

    const setMonth = (monthIndex) => {
        setViewMonth(monthIndex);
    };

    const grid = useMemo(() => {
        const firstDow = new Date(viewYear, viewMonth, 1).getDay();
        const count = daysInMonth(viewYear, viewMonth);
        const cells = [];
        for (let i = 0; i < firstDow; i += 1) {
            cells.push(null);
        }
        for (let d = 1; d <= count; d += 1) {
            cells.push(d);
        }
        while (cells.length % 7 !== 0) {
            cells.push(null);
        }
        return cells;
    }, [viewMonth, viewYear]);

    const years = useMemo(() => {
        const center = parsed?.year ?? today.year;
        const list = [];
        for (let y = center - 40; y <= center + 20; y += 1) {
            list.push(y);
        }
        return list;
    }, [parsed?.year, today.year]);

    const display = value && parseIso(value) ? value : '';
    const openLabel = ariaLabel || t('date_open_calendar');
    const locale =
        typeof document !== 'undefined'
            ? document.documentElement.lang || undefined
            : undefined;

    const sheet =
        open && typeof document !== 'undefined'
            ? createPortal(
                  <div
                      className="fixed inset-0 z-[80] flex items-end justify-center sm:items-center"
                      role="dialog"
                      aria-modal="true"
                      aria-label={openLabel}
                  >
                      <button
                          type="button"
                          className="absolute inset-0 bg-slate-950/45 backdrop-blur-[2px]"
                          aria-label={t('cancel')}
                          onClick={closeSheet}
                      />
                      <div className="relative z-[1] w-full max-w-md animate-[bvSheetIn_180ms_ease-out] rounded-t-3xl border border-slate-200 bg-white p-4 shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:rounded-3xl sm:p-5">
                          <div className="mx-auto mb-3 h-1 w-10 rounded-full bg-slate-200 dark:bg-slate-700 sm:hidden" />

                          <div className="mb-4 flex items-center justify-between gap-2">
                              <p className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                  {t('date_pick_title')}
                              </p>
                              <button
                                  type="button"
                                  onClick={closeSheet}
                                  className="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                                  aria-label={t('cancel')}
                              >
                                  <NavIcon name="close" className="text-base" />
                              </button>
                          </div>

                          <div className="mb-3 grid grid-cols-2 gap-2">
                              <label className="block">
                                  <span className="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                      {t('date_month')}
                                  </span>
                                  <select
                                      className="block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                      value={viewMonth}
                                      onChange={(e) => setMonth(Number(e.target.value))}
                                  >
                                      {Array.from({ length: 12 }, (_, i) => (
                                          <option key={i} value={i}>
                                              {monthLabel(viewYear, i, locale).replace(
                                                  /\s+\d{4}$/,
                                                  '',
                                              )}
                                          </option>
                                      ))}
                                  </select>
                              </label>
                              <label className="block">
                                  <span className="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                      {t('date_year')}
                                  </span>
                                  <select
                                      className="block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium tabular-nums text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                      value={viewYear}
                                      onChange={(e) => setYear(Number(e.target.value))}
                                  >
                                      {years.map((y) => (
                                          <option key={y} value={y}>
                                              {y}
                                          </option>
                                      ))}
                                  </select>
                              </label>
                          </div>

                          <div className="mb-2 flex items-center justify-between gap-2">
                              <button
                                  type="button"
                                  onClick={() => shiftMonth(-1)}
                                  className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                  aria-label={t('date_prev_month')}
                              >
                                  {/* rtl:rotate-180: flex puts prev on the right in RTL; mirror so the tip points toward the past */}
                                  <NavIcon name="chevronLeft" className="text-sm rtl:rotate-180" />
                              </button>
                              <p
                                  dir="ltr"
                                  className="font-sans text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100"
                              >
                                  {monthLabel(viewYear, viewMonth, locale)}
                              </p>
                              <button
                                  type="button"
                                  onClick={() => shiftMonth(1)}
                                  className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                  aria-label={t('date_next_month')}
                              >
                                  <NavIcon name="chevronRight" className="text-sm rtl:rotate-180" />
                              </button>
                          </div>

                          <div
                              dir="ltr"
                              className="grid grid-cols-7 gap-1 text-center"
                          >
                              {WEEKDAYS.map((w) => (
                                  <div
                                      key={w}
                                      className="py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400"
                                  >
                                      {w}
                                  </div>
                              ))}
                              {grid.map((day, idx) => {
                                  if (day == null) {
                                      return <div key={`e-${idx}`} className="h-10" />;
                                  }
                                  const iso = toIso(viewYear, viewMonth, day);
                                  const selected = value === iso;
                                  const isToday = today.iso === iso;
                                  const disabledDay = !isoInRange(iso, min, max);
                                  return (
                                      <button
                                          key={iso}
                                          type="button"
                                          disabled={disabledDay}
                                          onClick={() => selectDay(day)}
                                          className={
                                              'inline-flex h-10 items-center justify-center rounded-xl text-sm font-semibold tabular-nums transition ' +
                                              (selected
                                                  ? 'bg-teal-600 text-white shadow-sm dark:bg-teal-400 dark:text-slate-950'
                                                  : isToday
                                                    ? 'bg-teal-50 text-teal-900 ring-1 ring-teal-300 dark:bg-teal-950/50 dark:text-teal-100 dark:ring-teal-700'
                                                    : 'text-slate-800 hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800') +
                                              (disabledDay
                                                  ? ' cursor-not-allowed opacity-30'
                                                  : '')
                                          }
                                      >
                                          {day}
                                      </button>
                                  );
                              })}
                          </div>

                          <div className="mt-4 flex flex-wrap items-center gap-2">
                              <button
                                  type="button"
                                  onClick={() => {
                                      if (!isoInRange(today.iso, min, max)) {
                                          return;
                                      }
                                      emit(today.iso);
                                      setOpen(false);
                                  }}
                                  className="min-h-[2.5rem] flex-1 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                              >
                                  {t('date_today')}
                              </button>
                              {!required ? (
                                  <button
                                      type="button"
                                      onClick={() => {
                                          emit('');
                                          setOpen(false);
                                      }}
                                      className="min-h-[2.5rem] flex-1 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                                  >
                                      {t('date_clear')}
                                  </button>
                              ) : null}
                              <button
                                  type="button"
                                  onClick={closeSheet}
                                  className="min-h-[2.5rem] flex-1 rounded-xl bg-teal-600 px-3 text-sm font-semibold text-white hover:bg-teal-500 dark:bg-teal-400 dark:text-slate-950"
                              >
                                  {t('done')}
                              </button>
                          </div>
                      </div>
                  </div>,
                  document.body,
              )
            : null;

    return (
        <div className="relative">
            <input type="hidden" name={name} value={display} readOnly />
            <button
                {...rest}
                ref={triggerRef}
                id={fieldId}
                type="button"
                disabled={disabled}
                data-date-input="true"
                data-date-value={display || ''}
                aria-required={required || undefined}
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-label={openLabel}
                onClick={openSheet}
                onFocus={() => {
                    if (openOnFocus) {
                        openSheet();
                    }
                }}
                className={
                    'bv-date-input flex w-full min-h-[2.5rem] items-center gap-2 rounded-xl border border-slate-200 bg-white ps-3 pe-3 text-start text-sm font-medium shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/25 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 ' +
                    className
                }
            >
                <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-200">
                    <NavIcon name="calendar" className="text-sm" />
                </span>
                <span
                    dir="ltr"
                    className={
                        'font-sans tabular-nums ' +
                        (display
                            ? 'font-semibold text-slate-900 dark:text-white'
                            : 'font-medium text-slate-400')
                    }
                >
                    {display || t('date_placeholder')}
                </span>
            </button>
            {sheet}
        </div>
    );
});

export default DateInput;
