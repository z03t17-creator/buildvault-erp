import BrandMark from '@/Components/BrandMark';
import Dropdown from '@/Components/Dropdown';
import FlashBanner from '@/Components/FlashBanner';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import MobileDock from '@/Components/MobileDock';
import SidebarNavLink from '@/Components/SidebarNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
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
            active: route().current('dashboards.vault') || route().current('vault.*'),
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
            active: route().current('reports.*') || route().current('exports.*'),
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
        <nav className="flex flex-1 flex-col gap-5 overflow-y-auto px-3 py-4" aria-label="Primary">
            {groups.map((group) => (
                <div key={group.id}>
                    <p className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">
                        {group.label}
                    </p>
                    <div className="space-y-1">
                        {group.items.map((item) => (
                            <SidebarNavLink
                                key={item.key}
                                href={item.href}
                                active={item.active}
                                badge={item.badge}
                                icon={item.key}
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
            <div className="flex h-16 shrink-0 items-center border-b border-slate-200/70 px-4 dark:border-slate-800 sm:h-[4.25rem]">
                <BrandMark size="header" href={route('dashboard')} />
            </div>
            <SidebarNav groups={navGroups} onNavigate={closeSidebar} />
            <div className="mt-auto space-y-2 border-t border-slate-200/70 p-3 dark:border-slate-800">
                <div className="lg:hidden">
                    <LocaleSwitcher compact />
                </div>
                <Link
                    href={route('profile.edit')}
                    onClick={closeSidebar}
                    className={
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 transition ' +
                        (route().current('profile.*')
                            ? 'bg-teal-600/10 ring-1 ring-teal-500/25 dark:bg-teal-500/15'
                            : 'hover:bg-slate-100 dark:hover:bg-slate-800/80')
                    }
                >
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-sm font-semibold text-white dark:bg-teal-500 dark:text-slate-950">
                        {(user.name || '?').charAt(0).toUpperCase()}
                    </span>
                    <span className="min-w-0">
                        <span
                            className="block truncate text-sm font-semibold text-slate-800 dark:text-slate-100"
                            dir="auto"
                        >
                            {user.name}
                        </span>
                        <span className="block truncate text-xs text-slate-500">
                            {t('profile')}
                        </span>
                    </span>
                </Link>
                <p className="bv-attribution px-1">
                    Icons by{' '}
                    <a
                        href="https://www.flaticon.com/uicons"
                        target="_blank"
                        rel="noreferrer"
                        className="underline decoration-slate-300 underline-offset-2 hover:text-teal-700 dark:hover:text-teal-300"
                    >
                        Flaticon Uicons
                    </a>
                    {' · '}
                    UX inspired by Judi Group
                </p>
            </div>
        </>
    );

    return (
        <div className="min-h-screen lg:flex">
            <aside className="bv-sidebar sticky top-0 z-30 hidden h-screen w-[17rem] shrink-0 flex-col border-e border-slate-200/80 xl:w-72 lg:flex dark:border-slate-800">
                {sidebarBody}
            </aside>

            <div
                className={
                    (sidebarOpen ? 'pointer-events-auto' : 'pointer-events-none') +
                    ' fixed inset-0 z-50 lg:hidden'
                }
                aria-hidden={!sidebarOpen}
            >
                <div
                    className={
                        'absolute inset-0 bg-slate-900/45 transition-opacity ' +
                        (sidebarOpen ? 'opacity-100' : 'opacity-0')
                    }
                    onClick={closeSidebar}
                />
                <aside
                    className={
                        'bv-sidebar absolute inset-y-0 start-0 flex w-[min(19rem,90vw)] flex-col border-e border-slate-200 shadow-judi transition-transform dark:border-slate-800 ' +
                        (sidebarOpen
                            ? 'translate-x-0'
                            : 'ltr:-translate-x-full rtl:translate-x-full')
                    }
                >
                    {sidebarBody}
                </aside>
            </div>

            <div className="flex min-w-0 flex-1 flex-col pb-[4.75rem] lg:pb-0">
                <header className="bv-app-header sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur-lg dark:border-slate-800 dark:bg-slate-950/85">
                    <div className="bv-app-header-bar">
                        <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                            <button
                                type="button"
                                onClick={() => setSidebarOpen(true)}
                                className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-teal-300 hover:bg-teal-50 hover:text-teal-800 focus:outline-none dark:border-slate-700 dark:hover:bg-slate-800 lg:hidden"
                                aria-expanded={sidebarOpen}
                                aria-label={t('toggle_navigation')}
                            >
                                <NavIcon name="menu" className="text-lg" />
                            </button>
                            <div className="min-w-0 lg:hidden">
                                <BrandMark
                                    size="header"
                                    href={route('dashboard')}
                                    titleClassName="hidden sm:block"
                                />
                            </div>
                            {header && (
                                <div className="hidden min-w-0 lg:block">{header}</div>
                            )}
                        </div>

                        <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
                            <div className="hidden lg:block">
                                <LocaleSwitcher compact />
                            </div>
                            <ThemeToggle className="h-11 w-11 shrink-0 rounded-xl p-0" />
                            <div className="relative">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-xl">
                                            <button
                                                type="button"
                                                className="inline-flex h-11 max-w-[11rem] shrink-0 items-center gap-2 truncate rounded-xl border border-slate-200 bg-white px-2 text-sm font-semibold text-slate-700 transition hover:border-teal-300 hover:text-teal-800 focus:outline-none sm:px-3 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-teal-600/50 dark:hover:text-teal-300"
                                            >
                                                <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-700 text-xs font-bold text-white dark:bg-teal-500 dark:text-slate-950">
                                                    {(user.name || '?').charAt(0).toUpperCase()}
                                                </span>
                                                <span className="hidden truncate lg:inline" dir="auto">
                                                    {user.name}
                                                </span>
                                                <span className="hidden lg:inline-flex">
                                                    <NavIcon name="more" className="text-xs opacity-60" />
                                                </span>
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
                            {flash.success && (
                                <FlashBanner tone="success">{flash.success}</FlashBanner>
                            )}
                            {flash.error && (
                                <FlashBanner tone="error">{flash.error}</FlashBanner>
                            )}
                            {flash.warning && (
                                <FlashBanner tone="warning">{flash.warning}</FlashBanner>
                            )}
                        </div>
                    )}
                    {children}
                </main>
            </div>

            <MobileDock
                catalog={catalog}
                allowedNav={allowedNav}
                menuOpen={sidebarOpen}
                onMore={() => setSidebarOpen(true)}
            />
        </div>
    );
}
