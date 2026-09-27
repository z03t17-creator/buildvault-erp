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
                <div className={isHero ? 'space-y-1' : 'min-w-0 leading-tight'}>
                    <p
                        className={
                            isHero
                                ? 'font-display text-xs font-semibold uppercase tracking-[0.35em] text-slate-500 dark:text-slate-400'
                                : 'font-display text-[0.65rem] font-semibold uppercase tracking-[0.28em] text-slate-500 dark:text-slate-400'
                        }
                    >
                        ZHAKO
                    </p>
                    <h1
                        className={
                            isHero
                                ? 'font-display text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl'
                                : 'font-display truncate text-lg font-semibold tracking-tight text-slate-900 dark:text-white sm:text-xl'
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
