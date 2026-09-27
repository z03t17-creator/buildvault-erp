import BrandMark from '@/Components/BrandMark';
import Dropdown from '@/Components/Dropdown';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import useTranslations from '@/hooks/useTranslations';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const t = useTranslations();

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="min-h-screen">
            <nav className="border-b border-slate-200/80 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-20 justify-between gap-4">
                        <div className="flex min-w-0 items-center gap-6">
                            <BrandMark size="header" href={route('dashboard')} />

                            <div className="hidden space-x-5 sm:-my-px sm:ms-2 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    {t('dashboard')}
                                </NavLink>
                                <NavLink
                                    href={route('projects.index')}
                                    active={route().current('projects.*') || route().current('towers.*') || route().current('floors.*')}
                                >
                                    {t('projects')}
                                </NavLink>
                                <NavLink
                                    href={route('workers.index')}
                                    active={route().current('workers.*')}
                                >
                                    {t('workers')}
                                </NavLink>
                                <NavLink
                                    href={route('attendance.index')}
                                    active={route().current('attendance.*')}
                                >
                                    {t('attendance')}
                                </NavLink>
                            </div>
                        </div>

                        <div className="hidden items-center gap-3 sm:flex">
                            <LocaleSwitcher />
                            <ThemeToggle />
                            <div className="relative ms-1">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-transparent px-3 py-2 text-sm font-medium leading-4 text-slate-600 transition duration-150 ease-in-out hover:text-emerald-700 focus:outline-none dark:text-slate-300 dark:hover:text-emerald-400"
                                            >
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
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
                        </div>

                        <div className="-me-2 flex items-center gap-2 sm:hidden">
                            <ThemeToggle />
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-slate-400 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-600 focus:outline-none dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            {t('dashboard')}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('projects.index')}
                            active={route().current('projects.*') || route().current('towers.*') || route().current('floors.*')}
                        >
                            {t('projects')}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('workers.index')}
                            active={route().current('workers.*')}
                        >
                            {t('workers')}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('attendance.index')}
                            active={route().current('attendance.*')}
                        >
                            {t('attendance')}
                        </ResponsiveNavLink>
                    </div>

                    <div className="border-t border-slate-200 px-4 py-3 dark:border-slate-800">
                        <LocaleSwitcher />
                    </div>

                    <div className="border-t border-slate-200 pb-1 pt-4 dark:border-slate-800">
                        <div className="px-4">
                            <div className="text-base font-medium text-slate-800 dark:text-slate-100">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-slate-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                {t('profile')}
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                {t('log_out')}
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="border-b border-slate-200/60 bg-white/60 dark:border-slate-800 dark:bg-slate-900/40">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
