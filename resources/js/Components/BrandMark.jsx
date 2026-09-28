import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

/**
 * Brand-first ZHAKO mark + BuildVault ERP title.
 * size: 'hero' (login) | 'header' (authenticated shell)
 */
export default function BrandMark({
    size = 'hero',
    href = '/',
    className = '',
    showTitle = true,
}) {
    const isHero = size === 'hero';

    const content = (
        <div
            className={`flex items-center ${isHero ? 'flex-col gap-5 text-center' : 'gap-3'} ${className}`}
        >
            <div
                className={
                    isHero
                        ? 'rounded-sm bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700'
                        : 'shrink-0 rounded-sm bg-white p-1 ring-1 ring-slate-200 dark:ring-slate-700'
                }
            >
                <ApplicationLogo
                    className={
                        isHero
                            ? 'h-36 w-36 sm:h-44 sm:w-44'
                            : 'h-11 w-11 sm:h-12 sm:w-12'
                    }
                    alt="ZHAKO Company"
                />
            </div>

            {showTitle && (
                <div
                    className={isHero ? 'space-y-1' : 'min-w-0 leading-tight'}
                    dir="ltr"
                >
                    <p
                        className={
                            isHero
                                ? 'font-display text-2xl font-semibold tracking-[0.2em] text-slate-900 dark:text-white sm:text-3xl'
                                : 'font-display text-base font-semibold tracking-[0.18em] text-slate-900 transition group-hover:text-emerald-700 dark:text-white dark:group-hover:text-emerald-400 sm:text-lg'
                        }
                    >
                        ZHAKO
                    </p>
                    <h1
                        className={
                            isHero
                                ? 'font-sans text-lg font-semibold tracking-tight text-slate-600 dark:text-slate-300 sm:text-xl'
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
        <Link href={href} className="group outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
            {content}
        </Link>
    );
}
