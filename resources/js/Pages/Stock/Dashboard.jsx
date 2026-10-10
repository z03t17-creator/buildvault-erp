import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import { StockStatCard, StockStatusBadge, stockFieldClass } from '@/Components/StockDesk';
import StockItemRowActions from '@/Components/StockItemRowActions';
import StockTabs from '@/Components/StockTabs';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Dashboard({
    summary = {},
    items = [],
    filters = {},
    lowStockItems = [],
    recentMovements = [],
}) {
    const t = useTranslations();
    const iqd = t('IQD');
    const usd = t('USD');
    const canManage = useCan('stock.manageItems');
    const [q, setQ] = useState(filters.q || '');
    const list = Array.isArray(items) ? items : [];

    const apply = (next = {}) => {
        router.get(
            route('stock.dashboard'),
            {
                q: next.q !== undefined ? next.q : q,
                status: next.status !== undefined ? next.status : filters.status || '',
            },
            { preserveState: true, replace: true },
        );
    };

    const valueHint = [
        summary.stock_value_usd > 0 ? `${usd} ${Number(summary.stock_value_usd).toLocaleString()}` : null,
        summary.stock_value_iqd > 0 ? `${iqd} ${Number(summary.stock_value_iqd).toLocaleString()}` : null,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('warehouse_title')}
                    subtitle={t('warehouse_hub_hint')}
                    icon={<NavIcon name="stock" className="text-lg text-emerald-700 dark:text-emerald-300" />}
                    actions={
                        canManage ? (
                            <Link href={route('stock.items.create')}>
                                <PrimaryButton type="button" className="!bg-emerald-600 hover:!bg-emerald-500">
                                    <NavIcon name="stock" className="text-sm" />
                                    {t('warehouse_add_item')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('warehouse_title')} />
            <PageShell className="!space-y-5">
                <StockTabs />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StockStatCard
                        label={t('warehouse_total_capital')}
                        value={
                            <span className="flex flex-col gap-0.5">
                                {(summary.stock_value_usd ?? 0) > 0 ? (
                                    <span className="inline-flex items-baseline gap-1">
                                        <MoneyAmount
                                            value={summary.stock_value_usd}
                                            label={usd}
                                            size="lg"
                                            showLabel={false}
                                        />
                                        <span className="text-xs font-medium text-slate-400">{usd}</span>
                                    </span>
                                ) : null}
                                <span className="inline-flex items-baseline gap-1">
                                    <MoneyAmount
                                        value={summary.stock_value_iqd ?? 0}
                                        label={iqd}
                                        size={(summary.stock_value_usd ?? 0) > 0 ? 'md' : 'lg'}
                                        showLabel={false}
                                    />
                                    <span className="text-xs font-medium text-slate-400">{iqd}</span>
                                </span>
                            </span>
                        }
                        hint={valueHint || iqd}
                        tone="sky"
                    />
                    <StockStatCard
                        label={t('warehouse_low_alerts')}
                        value={summary.low_stock ?? 0}
                        hint={t('warehouse_low_badge_hint')}
                        tone="amber"
                        badge={summary.low_stock}
                        href={route('stock.dashboard', { status: 'low' })}
                    />
                    <StockStatCard
                        label={t('warehouse_today_moves')}
                        value={summary.today_movements ?? 0}
                        hint={t('warehouse_today_moves_hint', {
                            in: summary.today_in_count ?? 0,
                            out: summary.today_out_count ?? 0,
                        })}
                    />
                </div>

                <DataPanel>
                    <form
                        className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q });
                        }}
                    >
                        <div className="min-w-0 flex-1">
                            <label className="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                {t('search')}
                            </label>
                            <TextInput
                                className={stockFieldClass}
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder={t('warehouse_search_placeholder')}
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {['', 'low', 'out'].map((status) => (
                                <button
                                    key={status || 'all'}
                                    type="button"
                                    onClick={() => apply({ status })}
                                    className={
                                        'min-h-[2.75rem] rounded-xl border px-3 text-sm font-semibold transition ' +
                                        ((filters.status || '') === status
                                            ? 'border-emerald-400 bg-emerald-500/15 text-emerald-50'
                                            : 'border-slate-700 bg-slate-950 text-slate-300')
                                    }
                                >
                                    {status === ''
                                        ? t('all')
                                        : status === 'low'
                                          ? t('stock_low')
                                          : t('stock_out')}
                                </button>
                            ))}
                            <PrimaryButton type="submit" className="!bg-emerald-600 hover:!bg-emerald-500">
                                {t('search')}
                            </PrimaryButton>
                        </div>
                    </form>
                </DataPanel>

                {list.length === 0 ? (
                    <EmptyState
                        icon="stock"
                        title={t('stock_products_empty_title')}
                        description={t('stock_products_empty_hint')}
                        action={
                            canManage ? (
                                <Link href={route('stock.items.create')}>
                                    <PrimaryButton type="button">{t('warehouse_add_item')}</PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="border-b border-slate-800 px-4 py-3">
                            <p className="text-sm font-semibold text-slate-100">
                                {t('warehouse_tab_balance')}
                            </p>
                            <p className="text-xs text-slate-400">
                                {t('warehouse_balance_table_hint', { count: list.length })}
                            </p>
                        </div>
                        <DataTable minWidth="48rem" caption={t('warehouse_tab_balance')}>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('sku')}</Th>
                                    <Th>{t('unit')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                    <Th align="end">{t('warehouse_avg_cost')}</Th>
                                    <Th>{t('stock_status')}</Th>
                                    <Th>{t('actions')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((item) => {
                                    const currency =
                                        (item.cost_currency || item.currency) === 'USD' ? usd : iqd;
                                    return (
                                        <tr key={item.id}>
                                            <Td>
                                                <Link
                                                    href={route('stock.items.show', item.id)}
                                                    className="font-semibold text-emerald-300 underline-offset-2 hover:underline"
                                                >
                                                    {item.name}
                                                </Link>
                                                {item.category_label ? (
                                                    <p className="mt-0.5 text-xs text-slate-500">
                                                        {item.category_label}
                                                    </p>
                                                ) : null}
                                            </Td>
                                            <Td muted>
                                                <span dir="ltr">{item.sku || item.barcode || '—'}</span>
                                            </Td>
                                            <Td muted>{item.unit}</Td>
                                            <Td align="end" money>
                                                {item.quantity}
                                            </Td>
                                            <Td align="end" money>
                                                <span className="inline-flex items-baseline gap-1">
                                                    <MoneyAmount
                                                        value={item.average_unit_cost ?? 0}
                                                        label={currency}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                    <span className="text-[11px] text-slate-500">
                                                        {currency}
                                                    </span>
                                                </span>
                                            </Td>
                                            <Td>
                                                <StockStatusBadge item={item} t={t} />
                                            </Td>
                                            <Td>
                                                <StockItemRowActions
                                                    itemId={item.id}
                                                    canManage={canManage}
                                                />
                                            </Td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}

                {lowStockItems.length > 0 && (filters.status || '') !== 'low' ? (
                    <p className="text-sm text-amber-200/90">
                        {t('warehouse_low_inline', { count: lowStockItems.length })}{' '}
                        <Link
                            href={route('stock.dashboard', { status: 'low' })}
                            className="font-semibold underline underline-offset-2"
                        >
                            {t('view')}
                        </Link>
                    </p>
                ) : null}

                {recentMovements.length > 0 ? (
                    <DataPanel padded={false}>
                        <div className="border-b border-slate-800 px-4 py-3">
                            <p className="text-sm font-semibold text-slate-100">
                                {t('stock_recent_movements')}
                            </p>
                        </div>
                        <DataTable minWidth="28rem" caption={t('stock_recent_movements')}>
                            <thead>
                                <tr>
                                    <Th>{t('date')}</Th>
                                    <Th>{t('type')}</Th>
                                    <Th>{t('product')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentMovements.map((row) => (
                                    <tr key={row.id}>
                                        <Td>
                                            <span dir="ltr">{row.moved_on || '—'}</span>
                                        </Td>
                                        <Td>
                                            <span
                                                className={
                                                    row.type === 'in'
                                                        ? 'text-emerald-300'
                                                        : 'text-amber-200'
                                                }
                                            >
                                                {row.type === 'in'
                                                    ? t('warehouse_tab_receive')
                                                    : t('warehouse_tab_dispatch')}
                                            </span>
                                        </Td>
                                        <Td>{row.item?.name || '—'}</Td>
                                        <Td align="end" money>
                                            {row.quantity} {row.item?.unit || ''}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                ) : null}
            </PageShell>
        </AuthenticatedLayout>
    );
}
