import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { StockStatCard, StockStatusBadge } from '@/Components/StockDesk';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ summary, recentMovements = [], lowStockItems = [] }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canIn = useCan('stock.stockIn');
    const canOut = useCan('stock.stockOut');
    const canManage = useCan('stock.manageItems');
    const categories = summary?.by_category || [];
    const lowCount = summary?.low_stock ?? 0;

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('warehouse_title')}
                    subtitle={t('warehouse_dashboard_hint')}
                    icon={<NavIcon name="stock" className="text-lg text-emerald-700 dark:text-emerald-300" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('stock.consumption')}>
                                <SecondaryButton type="button">{t('warehouse_consumption')}</SecondaryButton>
                            </Link>
                            {canIn ? (
                                <Link href={route('stock.in.create')}>
                                    <PrimaryButton type="button" className="!bg-emerald-600 hover:!bg-emerald-500">
                                        <NavIcon name="stockIn" className="text-sm" />
                                        {t('warehouse_receive')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                            {canOut ? (
                                <Link href={route('stock.out.create')}>
                                    <PrimaryButton type="button" className="!bg-amber-600 hover:!bg-amber-500">
                                        <NavIcon name="stockOut" className="text-sm" />
                                        {t('warehouse_dispatch')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </div>
                    }
                />
            }
        >
            <Head title={t('warehouse_title')} />
            <PageShell className="!space-y-6">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StockStatCard
                        label={t('stock_total_items')}
                        value={summary?.total_items ?? 0}
                        hint={t('warehouse_items_hint')}
                        href={route('stock.items.index')}
                    />
                    <StockStatCard
                        label={t('stock_value_iqd')}
                        value={
                            <MoneyAmount
                                value={summary?.stock_value_iqd ?? 0}
                                label={iqd}
                                size="lg"
                                showLabel={false}
                            />
                        }
                        hint={iqd}
                        tone="sky"
                    />
                    <StockStatCard
                        label={t('stock_low')}
                        value={lowCount}
                        hint={t('warehouse_low_badge_hint')}
                        tone="amber"
                        badge={lowCount}
                        href={route('stock.items.index', { status: 'low' })}
                    />
                    <StockStatCard
                        label={t('stock_out')}
                        value={summary?.out_of_stock ?? 0}
                        hint={t('warehouse_out_hint')}
                        tone="rose"
                        href={route('stock.items.index', { status: 'out' })}
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <StockStatCard
                        label={t('stock_today_in')}
                        value={`${summary?.today_in_qty ?? 0} · ${summary?.today_in_count ?? 0}`}
                        hint={t('warehouse_today_in_hint')}
                    />
                    <StockStatCard
                        label={t('stock_today_out')}
                        value={`${summary?.today_out_qty ?? 0} · ${summary?.today_out_count ?? 0}`}
                        hint={t('warehouse_today_out_hint')}
                        tone="amber"
                    />
                </div>

                <div className="flex flex-wrap gap-2">
                    <Link href={route('stock.items.index')}>
                        <SecondaryButton type="button">{t('stock_products')}</SecondaryButton>
                    </Link>
                    <Link href={route('stock.movements.index')}>
                        <SecondaryButton type="button">{t('stock_movements')}</SecondaryButton>
                    </Link>
                    {canManage ? (
                        <Link href={route('stock.items.create')}>
                            <SecondaryButton type="button">{t('warehouse_add_item')}</SecondaryButton>
                        </Link>
                    ) : null}
                </div>

                {lowStockItems.length > 0 ? (
                    <DataPanel padded={false}>
                        <div className="flex items-center justify-between gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <div>
                                <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{t('warehouse_low_list')}</p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">{t('warehouse_low_list_hint')}</p>
                            </div>
                            <span className="inline-flex min-w-7 items-center justify-center rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-slate-950">
                                {lowCount}
                            </span>
                        </div>
                        <DataTable minWidth="36rem" caption={t('warehouse_low_list')}>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('sku')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                    <Th align="end">{t('min_quantity')}</Th>
                                    <Th>{t('stock_status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {lowStockItems.map((item) => (
                                    <tr key={item.id}>
                                        <Td>
                                            <Link
                                                href={route('stock.items.show', item.id)}
                                                className="font-semibold text-emerald-700 underline-offset-2 dark:text-emerald-300 hover:underline"
                                            >
                                                {item.name}
                                            </Link>
                                        </Td>
                                        <Td muted>
                                            <span dir="ltr">{item.sku || item.barcode || '—'}</span>
                                        </Td>
                                        <Td align="end" money>
                                            {item.quantity} {item.unit}
                                        </Td>
                                        <Td align="end" money>
                                            {item.min_quantity}
                                        </Td>
                                        <Td>
                                            <StockStatusBadge item={item} t={t} />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <DataPanel padded={false}>
                        <div className="border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{t('warehouse_by_category')}</p>
                        </div>
                        <DataTable minWidth="28rem" caption={t('warehouse_by_category')}>
                            <thead>
                                <tr>
                                    <Th>{t('category')}</Th>
                                    <Th align="end">{t('stock_total_items')}</Th>
                                    <Th align="end">{t('stock_value_iqd')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {categories.map((row) => (
                                    <tr key={row.category}>
                                        <Td>{row.category === 'uncategorized' ? t('uncategorized') : row.category}</Td>
                                        <Td align="end" money>
                                            {row.items_count}
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={row.value_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>

                    <DataPanel padded={false}>
                        <div className="border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{t('stock_recent_movements')}</p>
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
                                                        ? 'text-emerald-700 dark:text-emerald-300'
                                                        : 'text-amber-200'
                                                }
                                            >
                                                {row.type === 'in' ? t('warehouse_receive') : t('warehouse_dispatch')}
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
                </div>
            </PageShell>
        </AuthenticatedLayout>
    );
}
