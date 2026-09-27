import { router, usePage } from '@inertiajs/react';
import useTranslations from '@/hooks/useTranslations';

const LOCALES = [
    { code: 'en', label: 'EN' },
    { code: 'ckb', label: 'کوردی' },
    { code: 'ar', label: 'العربية' },
];

export default function LocaleSwitcher({ className = '' }) {
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
            className={`flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 ${className}`}
            role="group"
            aria-label={t('locale-label')}
        >
            <span className="hidden font-medium text-slate-500 dark:text-slate-400 sm:inline">
                {t('locale-label')}
            </span>
            <div className="flex items-center gap-1">
                {LOCALES.map(({ code, label }) => (
                    <button
                        key={code}
                        type="button"
                        onClick={() => switchLocale(code)}
                        className={
                            code === locale
                                ? 'rounded-md bg-emerald-600 px-2 py-1 font-semibold text-white shadow-sm'
                                : 'rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100'
                        }
                        aria-pressed={code === locale}
                    >
                        {label}
                    </button>
                ))}
            </div>
        </div>
    );
}
