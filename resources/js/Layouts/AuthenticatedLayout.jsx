import BrandMark from '@/Components/BrandMark';
import Dropdown from '@/Components/Dropdown';
import FlashBanner from '@/Components/FlashBanner';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import SidebarNavLink from '@/Components/SidebarNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import useTranslations from '@/hooks/useTranslations';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const NAV_GROUPS = [
    {
        id: 'main',
        labelKey: 'nav_group_main',
        keys: ['dashboard', 'vault', 'payroll', 'settlements', 'projects', 'workers', 'clientAdvances', 'stock'],
    },
    {
        id: 'ops',
        labelKey: 'nav_group_operations',
        keys: ['payouts', 'expenses', 'penalties', 'advances', 'productions', 'spatial', 'attendance', 'insurance'],
    },
    {
        id: 'system',
        labelKey: 'nav_group_system',
        keys: ['docs', 'imports', 'reports', 'backups', 'users', 'audit'],
    },
];

function buildNavCatalog(t, maturedCount) {
    return {
        dashboard: {
            key: 'dashboard',
            href: route('dashboard'),
            active: route().current('dashboard'),
            label: t('dashboard'),
        },
        vault: {
            key: 'vault',
            href: route('vault.index'),
            active:
                route().current('dashboards.vault') ||
                route().current('vault.*'),
            label: t('vault'),
        },
        payroll: {
            key: 'payroll',
            href: route('dashboards.payroll'),
            active: route().current('dashboards.payroll'),
            label: t('payroll'),
        },
        settlements: {
            key: 'settlements',
            href: route('settlements.index'),
            active: route().current('settlements.*'),
            label: t('settlements'),
        },
        projects: {
            key: 'projects',
            href: route('projects.index'),
            active:
                route().current('projects.*') ||
                route().current('towers.*') ||
                route().current('floors.*'),
            label: t('projects'),
        },
        workers: {
            key: 'workers',
            href: route('workers.index'),
            active: route().current('workers.*'),
            label: t('people'),
        },
        clientAdvances: {
            key: 'clientAdvances',
            href: route('client-advances.index'),
            active: route().current('client-advances.*'),
            label: t('client_advances'),
        },
        stock: {
            key: 'stock',
            href: route('stock.dashboard'),
            active: route().current('stock.*'),
            label: t('stock'),
        },
        payouts: {
            key: 'payouts',
            href: route('payouts.index'),
            active: route().current('payouts.*'),
            label: t('payouts'),
        },
        expenses: {
            key: 'expenses',
            href: route('expenses.index'),
            active: route().current('expenses.*'),
            label: t('expenses'),
        },
        penalties: {
            key: 'penalties',
            href: route('penalties.index'),
            active: route().current('penalties.*'),
            label: t('penalties'),
        },
        advances: {
            key: 'advances',
            href: route('advances.index'),
            active: route().current('advances.*'),
            label: t('advances'),
        },
        productions: {
            key: 'productions',
            href: route('productions.index'),
            active: route().current('productions.*'),
            label: t('productions'),
        },
        spatial: {
            key: 'spatial',
            href: route('spatial.index'),
            active: route().current('spatial.*'),
            label: t('spatial_grid'),
        },
        attendance: {
            key: 'attendance',
            href: route('attendance.index'),
            active: route().current('attendance.*'),
            label: t('attendance'),
        },
        insurance: {
            key: 'insurance',
            href: route('retention-holds.index'),
            active: route().current('retention-holds.*'),
            label: t('insurance'),
            badge: maturedCount > 0 ? maturedCount : null,
        },
        docs: {
            key: 'docs',
            href: route('documents.index'),
            active: route().current('documents.*'),
            label: t('docs'),
        },
        imports: {
            key: 'imports',
            href: route('imports.index'),
            active: route().current('imports.*'),
            label: t('imports'),
        },
        reports: {
            key: 'reports',
            href: route('reports.index'),
            active:
                route().current('reports.*') || route().current('exports.*'),
            label: t('reports'),
        },
        backups: {
            key: 'backups',
            href: route('backups.index'),
            active: route().current('backups.*'),
            label: t('backups'),
        },
        users: {
            key: 'users',
            href: route('users.index'),
            active: route().current('users.*'),
            label: t('users'),
        },
        audit: {
            key: 'audit',
            href: route('audit.index'),
            active: route().current('audit.*'),
            label: t('audit'),
        },
    };
}

function SidebarNav({ groups, onNavigate }) {
    return (
        <nav className="flex flex-1 flex-col gap-6 overflow-y-auto px-3 py-4" aria-label="Primary">
            {groups.map((group) => (
                <div key={group.id}>
                    <p className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">
                        {group.label}
                    </p>
                    <div className="space-y-0.5">
                        {group.items.map((item) => (
                            <SidebarNavLink
                                key={item.key}
                                href={item.href}
                                active={item.active}
                                badge={item.badge}
                                onClick={onNavigate}
                            >
                                {item.label}
                            </SidebarNavLink>
                        ))}
                    </div>
                </div>
            ))}
        </nav>
    );
}

export default function AuthenticatedLayout({ header, children, showFlash = true }) {
    const page = usePage();
    const user = page.props.auth.user;
    const allowedNav = new Set(page.props.auth?.nav || []);
    const maturedCount = page.props.alerts?.maturedRetentionCount || 0;
    const flash = page.props.flash || {};
    const t = useTranslations();
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const catalog = buildNavCatalog(t, maturedCount);
    const navGroups = NAV_GROUPS.map((group) => ({
        id: group.id,
        label: t(group.labelKey),
        items: group.keys
            .filter((key) => allowedNav.has(key) && catalog[key])
            .map((key) => catalog[key]),
    })).filter((group) => group.items.length > 0);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') {
                setSidebarOpen(false);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const closeSidebar = () => setSidebarOpen(false);

    const sidebarBody = (
        <>
            <div className="flex h-16 shrink-0 items-center border-b border-slate-200/80 px-4 dark:border-slate-800 sm:h-[4.25rem]">
                <BrandMark size="header" href={route('dashboard')} />
            </div>
            <SidebarNav groups={navGroups} onNavigate={closeSidebar} />
            <div className="mt-auto border-t border-slate-200/80 p-3 dark:border-slate-800">
                <Link
                    href={route('profile.edit')}
                    onClick={closeSidebar}
                    className={
                        'flex items-center gap-3 rounded-md px-3 py-2.5 transition ' +
                        (route().current('profile.*')
                            ? 'bg-emerald-600/10 ring-1 ring-emerald-500/25 dark:bg-emerald-500/15'
                            : 'hover:bg-slate-100 dark:hover:bg-slate-800/80')
                    }
                >
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                        {(user.name || '?').charAt(0).toUpperCase()}
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-medium text-slate-800 dark:text-slate-100" dir="auto">
                            {user.name}
                        </span>
                        <span className="block truncate text-xs text-slate-500">{t('profile')}</span>
                    </span>
                </Link>
            </div>
        </>
    );

    return (
        <div className="min-h-screen lg:flex">
            <aside className="bv-sidebar sticky top-0 z-30 hidden h-screen w-60 shrink-0 flex-col border-e border-slate-200/80 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 lg:flex xl:w-64">
                {sidebarBody}
            </aside>

            <div
                className={
                    (sidebarOpen ? 'pointer-events-auto' : 'pointer-events-none') +
                    ' fixed inset-0 z-40 lg:hidden'
                }
                aria-hidden={!sidebarOpen}
            >
                <div
                    className={
                        'absolute inset-0 bg-slate-900/40 transition-opacity ' +
                        (sidebarOpen ? 'opacity-100' : 'opacity-0')
                    }
                    onClick={closeSidebar}
                />
                <aside
                    className={
                        'bv-sidebar absolute inset-y-0 start-0 flex w-[min(18rem,88vw)] flex-col border-e border-slate-200 bg-white shadow-xl transition-transform dark:border-slate-800 dark:bg-slate-900 ' +
                        (sidebarOpen ? 'translate-x-0' : 'ltr:-translate-x-full rtl:translate-x-full')
                    }
                >
                    {sidebarBody}
                </aside>
            </div>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90">
                    <div className="flex h-14 items-center justify-between gap-3 px-4 sm:h-16 sm:px-6 lg:px-8">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setSidebarOpen(true)}
                                className="inline-flex items-center justify-center rounded-md border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none dark:border-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200 lg:hidden"
                                aria-expanded={sidebarOpen}
                                aria-label={t('toggle_navigation')}
                            >
                                <svg className="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                </svg>
                            </button>
                            <div className="min-w-0 lg:hidden">
                                <BrandMark size="header" href={route('dashboard')} />
                            </div>
                            {header && (
                                <div className="hidden min-w-0 lg:block">{header}</div>
                            )}
                        </div>

                        <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                            <LocaleSwitcher compact />
                            <ThemeToggle />
                            <div className="relative">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex max-w-[11rem] items-center truncate rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-emerald-400/60 hover:text-emerald-800 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-emerald-600/50 dark:hover:text-emerald-300"
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
                                    <Dropdown.Content contentClasses="py-1 bg-white dark:bg-slate-900">
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                            className="text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                                        >
                                            {t('profile')}
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                            className="text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                                        >
                                            {t('log_out')}
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>
                    </div>

                    {header && (
                        <div className="border-t border-slate-200/60 px-4 py-4 lg:hidden dark:border-slate-800">
                            {header}
                        </div>
                    )}
                </header>

                <main className="bv-row-enter flex-1">
                    {showFlash && (flash.success || flash.error || flash.warning) && (
                        <div className="mx-auto max-w-7xl space-y-2 px-4 pt-6 sm:px-6 lg:px-8">
                            {flash.success && <FlashBanner tone="success">{flash.success}</FlashBanner>}
                            {flash.error && <FlashBanner tone="error">{flash.error}</FlashBanner>}
                            {flash.warning && <FlashBanner tone="warning">{flash.warning}</FlashBanner>}
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
