import BrandMark from '@/Components/BrandMark';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen flex-col items-center px-4 pb-12 pt-6 sm:justify-center sm:pt-0">
            <div className="absolute end-4 top-4 z-10 flex items-center gap-3">
                <LocaleSwitcher />
                <ThemeToggle />
            </div>

            <div className="mt-10 w-full max-w-md sm:mt-0">
                <BrandMark size="hero" href="/" className="mb-8" />

                <div className="overflow-hidden rounded-lg border border-slate-200/80 bg-white/90 px-6 py-5 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/90">
                    {children}
                </div>
            </div>
        </div>
    );
}
