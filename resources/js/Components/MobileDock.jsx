import { NavIcon } from '@/lib/navIcons';
import useTranslations from '@/hooks/useTranslations';
import { Link } from '@inertiajs/react';

const DOCK_ITEMS = [
    { key: 'dashboard', icon: 'home', labelKey: 'home' },
    { key: 'vault', icon: 'vault', labelKey: 'vault' },
    { key: 'attendance', icon: 'attendance', labelKey: 'attendance' },
    { key: 'stock', icon: 'stock', labelKey: 'stock' },
];

export default function MobileDock({ catalog, allowedNav, onMore, menuOpen = false }) {
    const t = useTranslations();
    const items = DOCK_ITEMS.filter((item) => allowedNav.has(item.key) && catalog[item.key]).map(
        (item) => ({
            ...catalog[item.key],
            icon: item.icon,
            dockLabel: t(item.labelKey),
        }),
    );

    return (
        <nav className="bv-mobile-dock" aria-label={t('menu')}>
            <div className="mx-auto flex max-w-lg items-stretch gap-0.5">
                {items.map((item) => (
                    <Link
                        key={item.key}
                        href={item.href}
                        className={'bv-dock-item ' + (item.active ? 'bv-dock-item-active' : '')}
                    >
                        <NavIcon name={item.icon} solid={item.active} className="text-lg" />
                        <span className="truncate">{item.dockLabel}</span>
                    </Link>
                ))}
                <button
                    type="button"
                    onClick={onMore}
                    className={'bv-dock-item ' + (menuOpen ? 'bv-dock-item-active' : '')}
                    aria-label={t('toggle_navigation')}
                    aria-expanded={menuOpen}
                >
                    <NavIcon name="more" solid={menuOpen} className="text-lg" />
                    <span>{t('menu')}</span>
                </button>
            </div>
        </nav>
    );
}
