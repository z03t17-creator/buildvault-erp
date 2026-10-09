import { NavIcon } from '@/lib/navIcons';
import useTranslations from '@/hooks/useTranslations';
import { Link, usePage } from '@inertiajs/react';

const TAB_DEFS = [
    {
        key: 'docs',
        href: () => route('documents.index'),
        active: () => route().current('documents.*'),
        labelKey: 'nav_docs',
        hintKey: 'nav_docs_hint',
        icon: 'docs',
    },
    {
        key: 'imports',
        href: () => route('imports.index'),
        active: () => route().current('imports.*'),
        labelKey: 'nav_imports',
        hintKey: 'nav_imports_hint',
        icon: 'imports',
    },
    {
        key: 'reports',
        href: () => route('reports.index'),
        active: () => route().current('reports.*') || route().current('exports.*'),
        labelKey: 'nav_reports',
        hintKey: 'nav_reports_hint',
        icon: 'reports',
    },
    {
        key: 'backups',
        href: () => route('backups.index'),
        active: () => route().current('backups.*'),
        labelKey: 'nav_backups',
        hintKey: 'nav_backups_hint',
        icon: 'backups',
    },
];

/**
 * Horizontal segmented control for the Data & Records suite pages.
 * Only shows tabs the user is allowed to open.
 */
export default function DataRecordsTabs({ className = '' }) {
    const t = useTranslations();
    const allowedNav = new Set(usePage().props.auth?.nav || []);
    const tabs = TAB_DEFS.filter((tab) => allowedNav.has(tab.key));

    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav
            className={'bv-data-tabs ' + className}
            aria-label={t('nav_hub_data_records')}
        >
            <div className="bv-data-tabs-track" role="tablist">
                {tabs.map((tab) => {
                    const active = tab.active();
                    return (
                        <Link
                            key={tab.key}
                            href={tab.href()}
                            role="tab"
                            aria-selected={active}
                            title={t(tab.hintKey)}
                            className={
                                'bv-data-tab ' + (active ? 'bv-data-tab-active' : '')
                            }
                        >
                            <NavIcon name={tab.icon} solid={active} className="text-sm" />
                            <span className="truncate">{t(tab.labelKey)}</span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
