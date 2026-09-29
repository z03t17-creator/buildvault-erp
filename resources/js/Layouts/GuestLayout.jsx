import BrandMark from '@/Components/BrandMark';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen flex-col items-center px-4 pb-14 pt-6 sm:justify-center sm:pt-0">
            <div className="absolute end-4 top-4 z-10 flex items-center gap-3">
                <LocaleSwitcher />
                <ThemeToggle />
            </div>

            <div className="mt-12 w-full max-w-md sm:mt-0">
                <BrandMark size="hero" href="/" className="mb-8 justify-center sm:justify-start" />

                <div className="bv-panel border-slate-200/70 px-6 py-7 shadow-sm shadow-slate-900/[0.04] sm:px-8 sm:py-8 dark:border-slate-700/70 dark:shadow-none">
                    {children}
                </div>

                <p className="mt-6 text-center text-xs tracking-wide text-slate-400 dark:text-slate-500">
                    BuildVault ERP · ZHAKO
                </p>
            </div>
        </div>
    );
}
