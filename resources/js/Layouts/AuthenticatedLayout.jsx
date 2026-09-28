import BrandMark from '@/Components/BrandMark';
import Dropdown from '@/Components/Dropdown';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import useTranslations from '@/hooks/useTranslations';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';

function buildNavItems(t, maturedCount) {
    return [
        {
            key: 'dashboard',
            href: route('dashboard'),
            active: route().current('dashboard'),
            label: t('dashboard'),
            primary: true,
        },
        {
            key: 'vault',
            href: route('dashboards.vault'),
            active: route().current('dashboards.vault'),
            label: t('vault'),
            primary: true,
        },
        {
            key: 'payroll',
            href: route('dashboards.payroll'),
            active: route().current('dashboards.payroll'),
            label: t('payroll'),
            primary: true,
        },
        {
            key: 'projects',
            href: route('projects.index'),
            active:
                route().current('projects.*') ||
                route().current('towers.*') ||
                route().current('floors.*'),
            label: t('projects'),
            primary: true,
        },
        {
            key: 'workers',
            href: route('workers.index'),
            active: route().current('workers.*'),
            label: t('workers'),
            primary: true,
        },
        {
            key: 'attendance',
            href: route('attendance.index'),
            active: route().current('attendance.*'),
            label: t('attendance'),
            primary: true,
        },
        {
            key: 'payouts',
            href: route('payouts.index'),
            active: route().current('payouts.*'),
            label: t('payouts'),
            primary: false,
        },
        {
            key: 'penalties',
            href: route('penalties.index'),
            active: route().current('penalties.*'),
            label: t('penalties'),
            primary: false,
        },
        {
            key: 'docs',
            href: route('documents.index'),
            active: route().current('documents.*'),
            label: t('docs'),
            primary: false,
        },
        {
            key: 'imports',
            href: route('imports.index'),
            active: route().current('imports.*'),
            label: t('imports'),
            primary: false,
        },
        {
            key: 'exports',
            href: route('exports.index'),
            active: route().current('exports.*'),
            label: t('exports'),
            primary: false,
        },
        {
            key: 'backups',
            href: route('backups.index'),
            active: route().current('backups.*'),
            label: t('backups'),
            primary: false,
        },
        {
            key: 'audit',
            href: route('audit.index'),
            active: route().current('audit.*'),
            label: t('audit'),
            primary: false,
        },
        {
            key: 'insurance',
            href: route('retention-holds.index'),
            active: route().current('retention-holds.*'),
            label: t('insurance'),
            primary: false,
            badge: maturedCount > 0 ? maturedCount : null,
        },
    ];
}

export default function AuthenticatedLayout({ header, children }) {
    const page = usePage();
    const user = page.props.auth.user;
    const allowedNav = new Set(page.props.auth?.nav || []);
    const maturedCount = page.props.alerts?.maturedRetentionCount || 0;
    const t = useTranslations();
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    const navItems = buildNavItems(t, maturedCount).filter((item) => allowedNav.has(item.key));
    const primaryItems = navItems.filter((item) => item.primary);
    const secondaryItems = navItems.filter((item) => !item.primary);
    const secondaryActive = secondaryItems.some((item) => item.active);
    const insuranceBadge =
        (page.props.auth?.can?.['vault.retention'] && maturedCount > 0) ? maturedCount : 0;

    return (
        <div className="min-h-screen">
            <nav className="border-b border-slate-200/80 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Top bar: brand + controls — never overlaps */}
                    <div className="flex h-16 items-center justify-between gap-3 sm:h-[4.25rem]">
                        <div className="min-w-0 shrink">
                            <BrandMark size="header" href={route('dashboard')} />
                        </div>

                        <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                            <div className="hidden lg:block">
                                <LocaleSwitcher compact />
                            </div>
                            <ThemeToggle />
                            <div className="relative hidden lg:block">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex max-w-[10rem] items-center truncate rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-emerald-400/60 hover:text-emerald-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-600/50 dark:hover:text-emerald-300"
                                            >
                                                <span className="truncate" dir="auto">
                                                    {user.name}
                                                </span>
                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4 shrink-0"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                    aria-hidden
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>
                                            {t('profile')}
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            {t('log_out')}
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>

                            <button
                                type="button"
                                onClick={() =>
                                    setShowingNavigationDropdown((previousState) => !previousState)
                                }
                                className="inline-flex items-center justify-center rounded-md border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none dark:border-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200 lg:hidden"
                                aria-expanded={showingNavigationDropdown}
                                aria-label={t('toggle_navigation')}
                            >
                                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {/* Desktop primary nav — second row, clear hierarchy */}
                    <div className="hidden border-t border-slate-200/70 py-1 lg:block dark:border-slate-800">
                        <div className="flex items-center gap-1 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            {primaryItems.map((item) => (
                                <NavLink key={item.key} href={item.href} active={item.active}>
                                    {item.label}
                                </NavLink>
                            ))}

                            {secondaryItems.length > 0 && (
                                <div className="relative ms-1 shrink-0">
                                    <Dropdown>
                                        <Dropdown.Trigger>
                                            <button
                                                type="button"
                                                className={
                                                    'inline-flex items-center border-b-2 px-2 pt-1 pb-0.5 text-sm font-medium leading-5 transition ' +
                                                    (secondaryActive
                                                        ? 'border-emerald-500 text-slate-900 dark:text-white'
                                                        : 'border-transparent text-slate-500 hover:border-emerald-300/70 hover:text-emerald-800 dark:text-slate-400 dark:hover:text-emerald-300')
                                                }
                                            >
                                                {t('more')}
                                                {insuranceBadge > 0 && (
                                                    <span className="ms-1.5 inline-flex min-w-[1.25rem] items-center justify-center rounded bg-amber-500 px-1 text-[10px] font-bold text-white">
                                                        {insuranceBadge}
                                                    </span>
                                                )}
                                                <svg
                                                    className="ms-1 h-3.5 w-3.5"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                    aria-hidden
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </Dropdown.Trigger>
                                        <Dropdown.Content align="left" width="48">
                                            {secondaryItems.map((item) => (
                                                <Dropdown.Link key={item.key} href={item.href}>
                                                    <span className="inline-flex items-center gap-2">
                                                        {item.label}
                                                        {item.badge != null && (
                                                            <span className="inline-flex min-w-[1.25rem] items-center justify-center rounded bg-amber-500 px-1 text-[10px] font-bold text-white">
                                                                {item.badge}
                                                            </span>
                                                        )}
                                                    </span>
                                                </Dropdown.Link>
                                            ))}
                                        </Dropdown.Content>
                                    </Dropdown>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Mobile panel */}
                <div
                    className={
                        (showingNavigationDropdown
                            ? 'max-h-[40rem] opacity-100'
                            : 'max-h-0 opacity-0 pointer-events-none') +
                        ' bv-nav-panel overflow-hidden border-t border-slate-200/80 lg:hidden dark:border-slate-800'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        {navItems.map((item) => (
                            <ResponsiveNavLink
                                key={item.key}
                                href={item.href}
                                active={item.active}
                            >
                                <span className="inline-flex items-center gap-2">
                                    {item.label}
                                    {item.badge != null && (
                                        <span className="inline-flex min-w-[1.25rem] items-center justify-center rounded bg-amber-500 px-1 text-[10px] font-bold text-white">
                                            {item.badge}
                                        </span>
                                    )}
                                </span>
                            </ResponsiveNavLink>
                        ))}
                    </div>

                    <div className="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                        <LocaleSwitcher />
                    </div>

                    <div className="border-t border-slate-200 pb-1 pt-4 dark:border-slate-800">
                        <div className="px-4">
                            <div className="text-base font-medium text-slate-800 dark:text-slate-100" dir="auto">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-slate-500" dir="ltr">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                {t('profile')}
                            </ResponsiveNavLink>
                            <ResponsiveNavLink method="post" href={route('logout')} as="button">
                                {t('log_out')}
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="border-b border-slate-200/60 bg-white/60 transition-colors duration-200 dark:border-slate-800 dark:bg-slate-900/40">
                    <div className="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main className="bv-row-enter">{children}</main>
        </div>
    );
}
