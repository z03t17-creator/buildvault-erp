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
            className={`flex items-center gap-2 text-sm text-gray-600 ${className}`}
            role="group"
            aria-label={t('locale-label')}
        >
            <span className="hidden sm:inline font-medium text-gray-500">
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
                                ? 'rounded px-2 py-1 font-semibold text-gray-900 bg-gray-200'
                                : 'rounded px-2 py-1 text-gray-500 hover:text-gray-800 hover:bg-gray-100'
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
