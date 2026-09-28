import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

function Stat({ label, value }) {
    return (
        <div className="rounded-lg border border-slate-200/80 bg-white/90 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
            <div className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className="mt-1 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white"
            >
                {value}
            </div>
        </div>
    );
}

export default function Dashboard({ summary, recentMovements }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canIn = useCan('stock.stockIn');
    const canOut = useCan('stock.stockOut');
    const canManage = useCan('stock.manageItems');
    const movements = recentMovements || [];
    const categories = summary?.by_category || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_dashboard')}
                    subtitle={t('stock_dashboard_hint')}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canIn && (
                                <Link href={route('stock.in.create')}>
                                    <PrimaryButton type="button">{t('stock_in')}</PrimaryButton>
                                </Link>
                            )}
                            {canOut && (
                                <Link href={route('stock.out.create')}>
                                    <PrimaryButton type="button">{t('stock_out_action')}</PrimaryButton>
                                </Link>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={t('stock_dashboard')} />
            <PageShell className="!space-y-8">
                <DataPanel>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat label={t('stock_total_items')} value={summary?.total_items ?? 0} />
                        <Stat
                            label={t('stock_value_iqd')}
                            value={
                                <MoneyAmount
                                    value={summary?.stock_value_iqd ?? 0}
                                    label={iqd}
                                    size="xl"
                                    showLabel={false}
                                />
                            }
                        />
                        <Stat label={t('stock_low')} value={summary?.low_stock ?? 0} />
                        <Stat label={t('stock_out')} value={summary?.out_of_stock ?? 0} />
                        <Stat label={t('stock_today_in')} value={summary?.today_in_qty ?? 0} />
                        <Stat label={t('stock_today_out')} value={summary?.today_out_qty ?? 0} />
                        <Stat label={t('stock_today_in_count')} value={summary?.today_in_count ?? 0} />
                        <Stat label={t('stock_today_out_count')} value={summary?.today_out_count ?? 0} />
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Link href={route('stock.items.index')}>
                            <SecondaryButton type="button">{t('stock_products')}</SecondaryButton>
                        </Link>
                        <Link href={route('stock.suppliers.index')}>
                            <SecondaryButton type="button">{t('suppliers')}</SecondaryButton>
                        </Link>
                        <Link href={route('stock.movements.index')}>
                            <SecondaryButton type="button">{t('stock_movements')}</SecondaryButton>
                        </Link>
                        {canManage && (
                            <Link href={route('stock.items.create')}>
                                <SecondaryButton type="button">{t('new_product')}</SecondaryButton>
                            </Link>
                        )}
                    </div>
                </DataPanel>

                <div className="grid gap-6 lg:grid-cols-2">
                    <DataPanel title={t('role_panel_stock_by_category')} padded={false}>
                        <DataTable minWidth="24rem" caption={t('role_panel_stock_by_category')}>
                            <thead>
                                <tr>
                                    <Th>{t('category')}</Th>
                                    <Th align="end">{t('role_stat_items')}</Th>
                                    <Th align="end">{t('stock_value_iqd')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {categories.map((c) => (
                                    <tr key={c.category}>
                                        <Td>
                                            {c.category === 'uncategorized'
                                                ? t('uncategorized')
                                                : c.category}
                                        </Td>
                                        <Td align="end">{c.items_count}</Td>
                                        <Td align="end">
                                            <MoneyAmount
                                                value={c.value_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                    </tr>
                                ))}
                                {!categories.length && (
                                    <tr>
                                        <Td colSpan={3} muted className="py-8 text-center">
                                            {t('no_stock_movements')}
                                        </Td>
                                    </tr>
                                )}
                            </tbody>
                        </DataTable>
                    </DataPanel>

                    <DataPanel title={t('stock_recent_movements')} padded={false}>
                        <DataTable minWidth="28rem" caption={t('stock_recent_movements')}>
                            <thead>
                                <tr>
                                    <Th>{t('type')}</Th>
                                    <Th>{t('product')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                    <Th>{t('date')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {movements.map((m) => (
                                    <tr key={m.id}>
                                        <Td className="uppercase">{m.type}</Td>
                                        <Td>{m.item?.name || '—'}</Td>
                                        <Td align="end">{m.quantity}</Td>
                                        <Td muted>{m.moved_on || '—'}</Td>
                                    </tr>
                                ))}
                                {!movements.length && (
                                    <tr>
                                        <Td colSpan={4} muted className="py-8 text-center">
                                            {t('no_stock_movements')}
                                        </Td>
                                    </tr>
                                )}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                </div>
            </PageShell>
        </AuthenticatedLayout>
    );
}
