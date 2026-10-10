import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { StockStatCard, StockStatusBadge, stockFieldClass } from '@/Components/StockDesk';
import TextInput from '@/Components/TextInput';
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({
    items = [],
    filters = {},
    categories = [],
    overview = {},
}) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canManage = useCan('stock.manageItems');
    const [q, setQ] = useState(filters.q || '');
    const list = Array.isArray(items) ? items : [];

    const apply = (next = {}) => {
        router.get(
            route('stock.items.index'),
            {
                q: next.q !== undefined ? next.q : q,
                category_id: next.category_id !== undefined ? next.category_id : filters.category_id || '',
                status: next.status !== undefined ? next.status : filters.status || '',
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('warehouse_items')}
                    subtitle={t('warehouse_items_page_hint')}
                    icon={<NavIcon name="stock" className="text-lg text-emerald-700 dark:text-emerald-300" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('stock.dashboard')}>
                                <SecondaryButton type="button">{t('warehouse_title')}</SecondaryButton>
                            </Link>
                            {canManage ? (
                                <Link href={route('stock.items.create')}>
                                    <PrimaryButton type="button">
                                        <NavIcon name="stock" className="text-sm" />
                                        {t('warehouse_add_item')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </div>
                    }
                />
            }
        >
            <Head title={t('warehouse_items')} />
            <PageShell className="!space-y-6">
                <StockTabs />
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StockStatCard label={t('stock_total_items')} value={overview.products ?? 0} />
                    <StockStatCard
                        label={t('stock_value_iqd')}
                        value={
                            <MoneyAmount
                                value={overview.stock_value_iqd ?? 0}
                                label={iqd}
                                size="lg"
                                showLabel={false}
                            />
                        }
                        tone="sky"
                    />
                    <StockStatCard
                        label={t('stock_low')}
                        value={overview.low_stock ?? 0}
                        tone="amber"
                        badge={overview.low_stock}
                    />
                    <StockStatCard
                        label={t('stock_out')}
                        value={overview.out_of_stock ?? 0}
                        tone="rose"
                    />
                </div>

                <DataPanel>
                    <form
                        className="grid gap-3 sm:grid-cols-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ q });
                        }}
                    >
                        <div className="sm:col-span-2">
                            <TextInput
                                className={stockFieldClass}
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder={t('warehouse_search_placeholder')}
                            />
                        </div>
                        <select
                            className={stockFieldClass}
                            value={filters.category_id || ''}
                            onChange={(e) => apply({ category_id: e.target.value })}
                        >
                            <option value="">{t('stock_categories')}</option>
                            {categories.map((cat) => (
                                <option key={cat.id} value={cat.id}>
                                    {cat.name}
                                </option>
                            ))}
                        </select>
                        <select
                            className={stockFieldClass}
                            value={filters.status || ''}
                            onChange={(e) => apply({ status: e.target.value })}
                        >
                            <option value="">{t('stock_status')}</option>
                            <option value="ok">{t('stock_ok')}</option>
                            <option value="low">{t('stock_low')}</option>
                            <option value="out">{t('stock_out')}</option>
                        </select>
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
                        <DataTable minWidth="56rem" caption={t('warehouse_items')} stickyFirstColumn>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('sku')}</Th>
                                    <Th>{t('barcode')}</Th>
                                    <Th>{t('category')}</Th>
                                    <Th>{t('unit')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                    <Th align="end">{t('min_quantity')}</Th>
                                    <Th align="end">{t('warehouse_avg_cost')}</Th>
                                    <Th>{t('stock_status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((item) => (
                                    <tr key={item.id}>
                                        <Td>
                                            <Link
                                                href={route('stock.items.show', item.id)}
                                                className="font-semibold text-emerald-700 underline-offset-2 dark:text-emerald-300 hover:underline"
                                            >
                                                {item.name}
                                            </Link>
                                            {item.location ? (
                                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{item.location}</p>
                                            ) : null}
                                        </Td>
                                        <Td muted>
                                            <span dir="ltr">{item.sku || '—'}</span>
                                        </Td>
                                        <Td muted>
                                            <span dir="ltr">{item.barcode || '—'}</span>
                                        </Td>
                                        <Td muted>{item.category_label || '—'}</Td>
                                        <Td muted>{item.unit}</Td>
                                        <Td align="end" money>
                                            {item.quantity}
                                        </Td>
                                        <Td align="end" money>
                                            {item.min_quantity}
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={item.average_unit_cost ?? item.purchase_price_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td>
                                            <StockStatusBadge item={item} t={t} />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
