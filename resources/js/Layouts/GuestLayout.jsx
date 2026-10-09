import BrandMark from '@/Components/BrandMark';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';

export default function GuestLayout({ children, footer }) {
    return (
        <div className="bv-guest relative flex min-h-screen flex-col items-center px-4 pb-14 pt-6 sm:justify-center sm:pt-0">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 overflow-hidden"
            >
                <div className="bv-guest-glow absolute -start-24 -top-28 h-80 w-80 rounded-full bg-teal-400/20 blur-3xl dark:bg-teal-400/10" />
                <div className="bv-guest-glow-delay absolute -end-20 top-1/3 h-72 w-72 rounded-full bg-amber-300/15 blur-3xl dark:bg-amber-400/10" />
                <div className="bv-guest-grid absolute inset-0 opacity-[0.35] dark:opacity-[0.2]" />
            </div>

            <div className="absolute end-4 top-4 z-10 flex items-center gap-2">
                <LocaleSwitcher cycle />
                <ThemeToggle className="h-11 w-11 rounded-xl p-0" />
            </div>

            <div className="relative z-[1] mt-12 w-full max-w-md sm:mt-0">
                <div className="bv-guest-brand mb-8">
                    <BrandMark
                        size="hero"
                        href="/"
                        className="justify-center sm:justify-start"
                    />
                </div>

                <div className="bv-guest-panel bv-panel border-slate-200/70 px-6 py-7 shadow-judi sm:px-8 sm:py-8 dark:border-slate-700/70">
                    {children}
                </div>

                <p className="mt-6 text-center text-xs tracking-wide text-slate-400 dark:text-slate-500">
                    {footer || 'ZHAKO · BuildVault ERP'}
                </p>
            </div>
        </div>
    );
}
