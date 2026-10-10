import { NavIcon } from '@/lib/navIcons';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Link } from '@inertiajs/react';

const TAB_DEFS = [
    {
        key: 'overview',
        href: () => route('stock.dashboard'),
        active: () => route().current('stock.dashboard'),
        labelKey: 'warehouse_tab_overview',
        icon: 'stock',
        can: () => true,
    },
    {
        key: 'items',
        href: () => route('stock.items.index'),
        active: () => route().current('stock.items.*'),
        labelKey: 'warehouse_items',
        icon: 'stock',
        can: () => true,
    },
    {
        key: 'receive',
        href: () => route('stock.in.create'),
        active: () => route().current('stock.in.*'),
        labelKey: 'warehouse_receive',
        icon: 'stockIn',
        can: (can) => can.stockIn,
    },
    {
        key: 'dispatch',
        href: () => route('stock.out.create'),
        active: () => route().current('stock.out.*'),
        labelKey: 'warehouse_dispatch',
        icon: 'stockOut',
        can: (can) => can.stockOut,
    },
    {
        key: 'movements',
        href: () => route('stock.movements.index'),
        active: () => route().current('stock.movements.*'),
        labelKey: 'stock_movements',
        icon: 'stockMovements',
        can: () => true,
    },
    {
        key: 'consumption',
        href: () => route('stock.consumption'),
        active: () => route().current('stock.consumption'),
        labelKey: 'warehouse_consumption',
        icon: 'stockOut',
        can: () => true,
    },
    {
        key: 'suppliers',
        href: () => route('stock.suppliers.index'),
        active: () => route().current('stock.suppliers.*'),
        labelKey: 'suppliers',
        icon: 'stock',
        can: (can) => can.manageSuppliers,
    },
];

/**
 * Horizontal tab bar shared across warehouse / stock pages.
 */
export default function StockTabs({ className = '' }) {
    const t = useTranslations();
    const can = {
        stockIn: useCan('stock.stockIn'),
        stockOut: useCan('stock.stockOut'),
        manageSuppliers: useCan('stock.manageSuppliers'),
    };

    const tabs = TAB_DEFS.filter((tab) => tab.can(can));

    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav className={'bv-data-tabs ' + className} aria-label={t('warehouse_title')}>
            <div className="bv-data-tabs-track" role="tablist">
                {tabs.map((tab) => {
                    const active = tab.active();
                    return (
                        <Link
                            key={tab.key}
                            href={tab.href()}
                            role="tab"
                            aria-selected={active}
                            className={'bv-data-tab ' + (active ? 'bv-data-tab-active' : '')}
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
