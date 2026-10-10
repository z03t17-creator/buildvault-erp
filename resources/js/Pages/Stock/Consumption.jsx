import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import { StockStatCard, stockFieldClass } from '@/Components/StockDesk';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';

export default function Consumption({
    places = [],
    projects = [],
    filters = {},
    overview = {},
}) {
    const t = useTranslations();
    const iqd = t('IQD');
    const list = Array.isArray(places) ? places : [];

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('warehouse_consumption')}
                    subtitle={t('warehouse_consumption_hint')}
                    icon={<NavIcon name="stock" className="text-lg text-sky-700 dark:text-sky-300" />}
                    actions={
                        <Link href={route('stock.dashboard')}>
                            <SecondaryButton type="button">{t('warehouse_title')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('warehouse_consumption')} />
            <PageShell className="!space-y-6">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StockStatCard label={t('warehouse_places')} value={overview.places ?? 0} tone="sky" />
                    <StockStatCard label={t('warehouse_lines')} value={overview.lines ?? 0} />
                    <StockStatCard label={t('quantity')} value={overview.total_qty ?? 0} tone="amber" />
                    <StockStatCard
                        label={t('stock_value_iqd')}
                        value={
                            <MoneyAmount
                                value={overview.total_cost_iqd ?? 0}
                                label={iqd}
                                size="lg"
                                showLabel={false}
                            />
                        }
                    />
                </div>

                <DataPanel>
                    <select
                        className={stockFieldClass}
                        value={filters.project_id || ''}
                        onChange={(e) =>
                            router.get(
                                route('stock.consumption'),
                                { project_id: e.target.value || undefined },
                                { preserveState: true, replace: true },
                            )
                        }
                    >
                        <option value="">{t('project')}</option>
                        {projects.map((project) => (
                            <option key={project.id} value={project.id}>
                                {project.name}
                            </option>
                        ))}
                    </select>
                </DataPanel>

                {list.length === 0 ? (
                    <EmptyState
                        icon="stock"
                        title={t('warehouse_consumption_empty')}
                        description={t('warehouse_consumption_empty_hint')}
                    />
                ) : (
                    list.map((place) => (
                        <DataPanel key={place.key} padded={false}>
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                                <div>
                                    <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        {place.place_label || '—'}
                                    </p>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        {place.project_name || '—'}
                                        {place.site_kind
                                            ? ` · ${t(`warehouse_site_${place.site_kind}`)}`
                                            : ''}
                                    </p>
                                </div>
                                <div className="text-end text-xs text-slate-600 dark:text-slate-300">
                                    <p dir="ltr" className="font-semibold tabular-nums">
                                        {place.total_qty}
                                    </p>
                                    <p dir="ltr" className="tabular-nums text-slate-400">
                                        <MoneyAmount
                                            value={place.total_cost_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                        />{' '}
                                        {iqd}
                                    </p>
                                </div>
                            </div>
                            <DataTable minWidth="48rem" caption={place.place_label}>
                                <thead>
                                    <tr>
                                        <Th>{t('date')}</Th>
                                        <Th>{t('product')}</Th>
                                        <Th>{t('category')}</Th>
                                        <Th align="end">{t('quantity')}</Th>
                                        <Th align="end">{t('warehouse_avg_cost')}</Th>
                                        <Th align="end">{t('warehouse_total_cost')}</Th>
                                        <Th>{t('warehouse_receiver')}</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(place.lines || []).map((line) => (
                                        <tr key={line.id}>
                                            <Td>
                                                <span dir="ltr">{line.moved_on || '—'}</span>
                                            </Td>
                                            <Td>
                                                <p className="font-semibold text-slate-900 dark:text-slate-100">{line.item_name}</p>
                                                <p className="text-xs text-slate-500 dark:text-slate-400" dir="ltr">
                                                    {line.sku || '—'}
                                                </p>
                                            </Td>
                                            <Td muted>{line.category || '—'}</Td>
                                            <Td align="end" money>
                                                {line.quantity} {line.unit}
                                            </Td>
                                            <Td align="end" money>
                                                <MoneyAmount
                                                    value={line.unit_cost_iqd}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            </Td>
                                            <Td align="end" money>
                                                <MoneyAmount
                                                    value={line.line_cost_iqd}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            </Td>
                                            <Td muted>{line.receiver || '—'}</Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </DataTable>
                        </DataPanel>
                    ))
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
