import useTranslations from '@/hooks/useTranslations';
import { useEffect, useState } from 'react';

function getPreferredTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const stored = localStorage.getItem('theme');
    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

export default function ThemeToggle({ className = '' }) {
    const [theme, setTheme] = useState('light');
    const t = useTranslations();

    useEffect(() => {
        const initial = getPreferredTheme();
        setTheme(initial);
        applyTheme(initial);
    }, []);

    const toggle = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        setTheme(next);
        localStorage.setItem('theme', next);
        applyTheme(next);
    };

    return (
        <button
            type="button"
            onClick={toggle}
            className={`inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-slate-600 transition duration-200 hover:border-emerald-500/40 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-emerald-500/50 dark:hover:text-emerald-400 ${className}`}
            aria-label={theme === 'dark' ? t('switch_light_mode') : t('switch_dark_mode')}
            title={theme === 'dark' ? t('light_mode') : t('dark_mode')}
        >
            {theme === 'dark' ? (
                <svg className="h-4 w-4 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                    <path
                        fillRule="evenodd"
                        d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                        clipRule="evenodd"
                    />
                </svg>
            ) : (
                <svg className="h-4 w-4 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden>
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                </svg>
            )}
        </button>
    );
}
