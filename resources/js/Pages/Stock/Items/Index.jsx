import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ items, filters, categories }) {
    const list = items || [];
    const canManage = useCan('stock.manageItems');
    const t = useTranslations();
    const iqd = t('IQD');

    const applyFilters = (next) => {
        router.get(route('stock.items.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_products')}
                    subtitle={t('stock_products_hint')}
                    actions={
                        canManage ? (
                            <Link href={route('stock.items.create')}>
                                <PrimaryButton type="button">{t('new_product')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('stock_products')} />
            <PageShell>
                <DataPanel>
                    <div className="flex flex-wrap gap-3">
                        <TextInput
                            className="max-w-xs"
                            placeholder={t('search')}
                            defaultValue={filters?.q || ''}
                            onBlur={(e) => applyFilters({ q: e.target.value })}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') applyFilters({ q: e.target.value });
                            }}
                        />
                        <select
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                            value={filters?.category || ''}
                            onChange={(e) => applyFilters({ category: e.target.value })}
                        >
                            <option value="">{t('all_categories')}</option>
                            {(categories || []).map((c) => (
                                <option key={c} value={c}>
                                    {c}
                                </option>
                            ))}
                        </select>
                    </div>
                </DataPanel>

                {list.length === 0 ? (
                    <EmptyState title={t('no_products')} description={t('stock_products_hint')} />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="56rem" caption={t('stock_products')}>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('sku')}</Th>
                                    <Th>{t('category')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                    <Th align="end">{t('min_quantity')}</Th>
                                    <Th align="end">{t('purchase_price_iqd')}</Th>
                                    <Th align="end">{t('stock_value_iqd')}</Th>
                                    <Th>{t('supplier')}</Th>
                                    <Th>{t('location')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((item) => (
                                    <tr
                                        key={item.id}
                                        className={
                                            item.is_out_of_stock
                                                ? 'bg-rose-50/60 dark:bg-rose-950/20'
                                                : item.is_low_stock
                                                  ? 'bg-amber-50/60 dark:bg-amber-950/20'
                                                  : ''
                                        }
                                    >
                                        <Td>
                                            <Link
                                                href={route('stock.items.show', item.id)}
                                                className="font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                            >
                                                {item.name}
                                            </Link>
                                        </Td>
                                        <Td muted className="tabular-nums">
                                            {item.sku || '—'}
                                        </Td>
                                        <Td muted>{item.category || '—'}</Td>
                                        <Td align="end" className="tabular-nums">
                                            {item.quantity} {item.unit}
                                        </Td>
                                        <Td align="end" className="tabular-nums">
                                            {item.min_quantity}
                                        </Td>
                                        <Td align="end">
                                            <MoneyAmount
                                                value={item.purchase_price_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td align="end">
                                            <MoneyAmount
                                                value={item.stock_value_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td muted>{item.supplier?.name || '—'}</Td>
                                        <Td muted>{item.location || '—'}</Td>
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
