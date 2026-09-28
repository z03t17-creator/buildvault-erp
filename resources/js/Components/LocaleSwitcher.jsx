import { router, usePage } from '@inertiajs/react';
import useTranslations from '@/hooks/useTranslations';

const LOCALES = [
    { code: 'en', label: 'EN', short: 'EN' },
    { code: 'ckb', label: 'کوردی', short: 'KU' },
    { code: 'ar', label: 'ع', short: 'AR' },
];

export default function LocaleSwitcher({ className = '', compact = false }) {
    const { locale } = usePage().props;
    const t = useTranslations();

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

    return (
        <div
            className={`flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300 ${className}`}
            role="group"
            aria-label={t('locale-label')}
        >
            {!compact && (
                <span className="font-medium text-slate-500 dark:text-slate-400">
                    {t('locale-label')}
                </span>
            )}
            <div className="flex items-center gap-0.5 rounded-md border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-900/80">
                {LOCALES.map(({ code, label, short }) => (
                    <button
                        key={code}
                        type="button"
                        onClick={() => switchLocale(code)}
                        className={
                            code === locale
                                ? 'rounded px-2 py-1 text-xs font-semibold text-white bg-emerald-600 shadow-sm'
                                : 'rounded px-2 py-1 text-xs font-medium text-slate-500 hover:bg-white hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100'
                        }
                        aria-pressed={code === locale}
                        title={label}
                        lang={code}
                    >
                        {compact ? short : label}
                    </button>
                ))}
            </div>
        </div>
    );
}
