import { NavIcon } from '@/lib/navIcons';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Link } from '@inertiajs/react';

const TAB_DEFS = [
    {
        key: 'balance',
        href: () => route('stock.dashboard'),
        active: () =>
            route().current('stock.dashboard') ||
            route().current('stock.items.*') ||
            route().current('stock.movements.*') ||
            route().current('stock.consumption'),
        labelKey: 'warehouse_tab_balance',
        hintKey: 'warehouse_tab_balance_hint',
        icon: 'stock',
        tone: 'emerald',
        can: () => true,
    },
    {
        key: 'receive',
        href: () => route('stock.in.create'),
        active: () => route().current('stock.in.*'),
        labelKey: 'warehouse_tab_receive',
        hintKey: 'warehouse_tab_receive_hint',
        icon: 'stockIn',
        tone: 'sky',
        can: (can) => can.stockIn,
    },
    {
        key: 'dispatch',
        href: () => route('stock.out.create'),
        active: () => route().current('stock.out.*'),
        labelKey: 'warehouse_tab_dispatch',
        hintKey: 'warehouse_tab_dispatch_hint',
        icon: 'stockOut',
        tone: 'amber',
        can: (can) => can.stockOut,
    },
];

const toneActive = {
    emerald:
        'border-emerald-400/60 bg-emerald-500/15 text-emerald-50 ring-2 ring-emerald-400/30',
    sky: 'border-sky-400/60 bg-sky-500/15 text-sky-50 ring-2 ring-sky-400/30',
    amber: 'border-amber-400/60 bg-amber-500/15 text-amber-50 ring-2 ring-amber-400/30',
};

/**
 * Three large warehouse views — balance, receive (+ سلفە), dispatch to project.
 */
export default function StockTabs({ className = '' }) {
    const t = useTranslations();
    const can = {
        stockIn: useCan('stock.stockIn'),
        stockOut: useCan('stock.stockOut'),
    };

    const tabs = TAB_DEFS.filter((tab) => tab.can(can));

    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav className={className} aria-label={t('warehouse_title')}>
            <div className="grid gap-2 sm:grid-cols-3" role="tablist">
                {tabs.map((tab) => {
                    const active = tab.active();
                    return (
                        <Link
                            key={tab.key}
                            href={tab.href()}
                            role="tab"
                            aria-selected={active}
                            className={
                                'flex min-h-[4.25rem] flex-col justify-center gap-0.5 rounded-2xl border px-4 py-3 transition ' +
                                (active
                                    ? toneActive[tab.tone]
                                    : 'border-slate-700/80 bg-slate-950/50 text-slate-300 hover:border-slate-500 hover:bg-slate-900/70')
                            }
                        >
                            <span className="flex items-center gap-2 text-base font-semibold">
                                <NavIcon name={tab.icon} solid={active} className="text-base" />
                                <span className="truncate">{t(tab.labelKey)}</span>
                            </span>
                            <span className="truncate text-xs opacity-70">{t(tab.hintKey)}</span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
