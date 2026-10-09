/**
 * Toggle for whether the 10% staff insurance hold applies on this payout.
 * Knob uses logical inset (start/end) so it stays inside the track in RTL.
 */
export default function InsuranceHoldToggle({
    checked,
    onChange,
    label,
    onLabel,
    offLabel,
    hintOn,
    hintOff,
    tone = 'amber',
}) {
    const tones = {
        amber: {
            on: 'border-amber-500 bg-amber-50 text-amber-950 ring-amber-500/30 dark:border-amber-400 dark:bg-amber-950/40 dark:text-amber-100',
            off: 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300',
            trackOn: 'bg-amber-500',
            knob: 'bg-white',
        },
        sky: {
            on: 'border-sky-500 bg-sky-50 text-sky-950 ring-sky-500/30 dark:border-sky-400 dark:bg-sky-950/40 dark:text-sky-100',
            off: 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300',
            trackOn: 'bg-sky-500',
            knob: 'bg-white',
        },
    };
    const t = tones[tone] || tones.amber;

    return (
        <div
            className={
                'rounded-xl border p-3 transition ' +
                (checked ? t.on + ' ring-2' : t.off)
            }
        >
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                onClick={() => onChange(!checked)}
                className="flex w-full items-center justify-between gap-3 text-start"
            >
                <span className="min-w-0 flex-1">
                    <span className="block text-sm font-semibold">{label}</span>
                    <span className="mt-0.5 block text-xs font-medium opacity-80">
                        {checked ? onLabel : offLabel}
                    </span>
                </span>
                <span
                    className={
                        'relative h-7 w-12 shrink-0 rounded-full transition ' +
                        (checked ? t.trackOn : 'bg-slate-300 dark:bg-slate-600')
                    }
                    aria-hidden
                >
                    <span
                        className={
                            'absolute top-1 h-5 w-5 rounded-full shadow transition-all duration-150 ' +
                            t.knob +
                            ' ' +
                            (checked ? 'start-auto end-1' : 'start-1 end-auto')
                        }
                    />
                </span>
            </button>
            <p className="mt-2 text-xs leading-relaxed opacity-80">
                {checked ? hintOn : hintOff}
            </p>
        </div>
    );
}
