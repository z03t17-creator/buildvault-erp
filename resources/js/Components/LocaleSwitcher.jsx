import { router, usePage } from '@inertiajs/react';
import useTranslations from '@/hooks/useTranslations';

const LOCALES = [
    { code: 'en', label: 'English', short: 'EN' },
    { code: 'ckb', label: 'کوردی', short: 'KU' },
    { code: 'ar', label: 'العربية', short: 'AR' },
];

function nextLocale(current) {
    const idx = LOCALES.findIndex((l) => l.code === current);
    const next = LOCALES[(idx + 1) % LOCALES.length];

    return next?.code || 'ckb';
}

export default function LocaleSwitcher({ className = '', cycle = true }) {
    const { locale } = usePage().props;
    const t = useTranslations();
    const current = LOCALES.find((l) => l.code === locale) || LOCALES[1];

    const switchLocale = (next) => {
        if (next === locale) {
            return;
        }

        router.post(
            route('locale.update'),
            { locale: next },
            { preserveScroll: true },
        );
    };

    const cycleNext = () => {
        switchLocale(nextLocale(locale));
    };

    if (cycle) {
        return (
            <button
                type="button"
                onClick={cycleNext}
                className={
                    'inline-flex h-11 min-w-[2.75rem] shrink-0 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold tracking-wide text-slate-700 transition hover:border-teal-300 hover:bg-teal-50 hover:text-teal-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-teal-600/50 dark:hover:bg-slate-800 dark:hover:text-teal-300 ' +
                    className
                }
                aria-label={t('locale-label')}
                title={`${current.label} → ${LOCALES.find((l) => l.code === nextLocale(locale))?.label || ''}`}
                lang={current.code}
            >
                {current.short}
            </button>
        );
    }

    return (
        <div
            className={`flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300 ${className}`}
            role="group"
            aria-label={t('locale-label')}
        >
            <div className="flex items-center gap-0.5 rounded-xl border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-900/80">
                {LOCALES.map(({ code, label, short }) => (
                    <button
                        key={code}
                        type="button"
                        onClick={() => switchLocale(code)}
                        className={
                            code === locale
                                ? 'min-h-8 rounded-lg px-2.5 py-1 text-xs font-semibold text-white bg-teal-700 shadow-sm dark:bg-teal-500 dark:text-slate-950'
                                : 'min-h-8 rounded-lg px-2.5 py-1 text-xs font-medium text-slate-500 hover:bg-white hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100'
                        }
                        aria-pressed={code === locale}
                        title={label}
                        lang={code}
                    >
                        {short}
                    </button>
                ))}
            </div>
        </div>
    );
}
