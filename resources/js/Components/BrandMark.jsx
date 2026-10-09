import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

/**
 * Brand-first ZHAKO mark + BuildVault ERP title (Judi-clean hierarchy).
 * size: 'hero' | 'header'
 * Header wordmark can be hidden on narrow screens via titleClassName (icon-only).
 */
export default function BrandMark({
    size = 'hero',
    href = '/',
    className = '',
    showTitle = true,
    titleClassName = '',
}) {
    const isHero = size === 'hero';

    const content = (
        <div
            className={`flex items-center ${isHero ? 'flex-col gap-5 text-center' : 'gap-2 sm:gap-3'} ${className}`}
        >
            <div
                className={
                    isHero
                        ? 'rounded-2xl bg-white p-3 shadow-judi ring-1 ring-slate-200/80 dark:bg-slate-900 dark:ring-slate-700'
                        : 'shrink-0 rounded-xl bg-white p-0.5 ring-1 ring-slate-200/80 sm:p-1 dark:bg-slate-900 dark:ring-slate-700'
                }
            >
                <ApplicationLogo
                    className={
                        isHero
                            ? 'h-36 w-36 sm:h-44 sm:w-44'
                            : 'h-9 w-9 sm:h-11 sm:w-11'
                    }
                    alt="ZHAKO Company"
                />
            </div>

            {showTitle && (
                <div
                    className={
                        (isHero ? 'space-y-1' : 'min-w-0 leading-tight') +
                        (titleClassName ? ` ${titleClassName}` : '')
                    }
                    dir="ltr"
                >
                    <p
                        className={
                            isHero
                                ? 'font-display text-3xl font-semibold tracking-[0.18em] text-slate-900 dark:text-white sm:text-4xl'
                                : 'font-display text-base font-semibold tracking-[0.16em] text-slate-900 transition group-hover:text-teal-700 dark:text-white dark:group-hover:text-teal-300 sm:text-lg'
                        }
                    >
                        ZHAKO
                    </p>
                    <h1
                        className={
                            isHero
                                ? 'font-sans text-lg font-semibold tracking-tight text-teal-800 dark:text-teal-300 sm:text-xl'
                                : 'truncate font-sans text-xs font-medium tracking-wide text-slate-500 dark:text-slate-400 sm:text-sm'
                        }
                    >
                        BuildVault ERP
                    </h1>
                </div>
            )}
        </div>
    );

    if (!href) {
        return content;
    }

    return (
        <Link
            href={href}
            className="group min-w-0 shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
            aria-label="ZHAKO BuildVault ERP"
        >
            {content}
        </Link>
    );
}
