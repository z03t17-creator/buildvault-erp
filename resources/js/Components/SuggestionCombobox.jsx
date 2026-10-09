import { useId, useMemo } from 'react';

/**
 * Text field with datalist + suggestion chips (free typing allowed).
 */
export default function SuggestionCombobox({
    id,
    value = '',
    onChange,
    suggestions = [],
    className = '',
    placeholder = '',
    disabled = false,
}) {
    const autoId = useId();
    const listId = `${id || 'suggest'}-list-${autoId.replace(/:/g, '')}`;

    const options = useMemo(() => {
        const seen = new Set();
        const out = [];
        for (const raw of suggestions) {
            const unit = String(raw ?? '').trim();
            if (!unit) continue;
            const key = unit.toLocaleLowerCase();
            if (seen.has(key)) continue;
            seen.add(key);
            out.push(unit);
        }
        return out;
    }, [suggestions]);

    const current = String(value ?? '');

    return (
        <div>
            <input
                id={id}
                type="text"
                list={listId}
                value={current}
                disabled={disabled}
                placeholder={placeholder}
                autoComplete="off"
                onChange={(e) => onChange(e.target.value)}
                className={className}
            />
            <datalist id={listId}>
                {options.map((unit) => (
                    <option key={unit} value={unit} />
                ))}
            </datalist>
            {options.length > 0 ? (
                <div className="mt-1.5 flex flex-wrap gap-1.5">
                    {options.map((unit) => {
                        const selected = current.trim() === unit;
                        return (
                            <button
                                key={unit}
                                type="button"
                                disabled={disabled}
                                onClick={() => onChange(unit)}
                                className={
                                    'min-h-[2rem] rounded-lg border px-2.5 text-xs font-semibold transition ' +
                                    (selected
                                        ? 'border-teal-500 bg-teal-50 text-teal-900 ring-2 ring-teal-500/25 dark:border-teal-400 dark:bg-teal-950/50 dark:text-teal-100'
                                        : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-teal-400/60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200')
                                }
                            >
                                {unit}
                            </button>
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}
