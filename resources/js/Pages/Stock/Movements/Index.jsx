import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, router } from '@inertiajs/react';

const selectClass =
    'rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Index({ movements, filters, items, projects }) {
    const list = movements || [];
    const t = useTranslations();

    const apply = (next) => {
        router.get(route('stock.movements.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            header={<PageHeader title={t('stock_movements')} subtitle={t('stock_movements_hint')} />}
        >
            <Head title={t('stock_movements')} />
            <PageShell>
                <DataPanel>
                    <div className="flex flex-wrap gap-3">
                        <select
                            className={selectClass}
                            value={filters?.type || ''}
                            onChange={(e) => apply({ type: e.target.value })}
                        >
                            <option value="">{t('all_types')}</option>
                            <option value="in">IN</option>
                            <option value="out">OUT</option>
                        </select>
                        <select
                            className={selectClass}
                            value={filters?.stock_item_id || ''}
                            onChange={(e) => apply({ stock_item_id: e.target.value })}
                        >
                            <option value="">{t('all_products')}</option>
                            {(items || []).map((i) => (
                                <option key={i.id} value={i.id}>
                                    {i.name}
                                </option>
                            ))}
                        </select>
                        <select
                            className={selectClass}
                            value={filters?.project_id || ''}
                            onChange={(e) => apply({ project_id: e.target.value })}
                        >
                            <option value="">{t('all_projects')}</option>
                            {(projects || []).map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </DataPanel>

                {list.length === 0 ? (
                    <EmptyState title={t('no_stock_movements')} description={t('stock_movements_hint')} />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="48rem" caption={t('stock_movements')}>
                            <thead>
                                <tr>
                                    <Th>{t('type')}</Th>
                                    <Th>{t('product')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                    <Th>{t('date')}</Th>
                                    <Th>{t('user')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('reference')}</Th>
                                    <Th align="end">{t('qty_change')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((m) => (
                                    <tr key={m.id}>
                                        <Td className="uppercase">{m.type}</Td>
                                        <Td>{m.item?.name || '—'}</Td>
                                        <Td align="end" className="tabular-nums">
                                            {m.quantity}
                                        </Td>
                                        <Td muted className="tabular-nums">
                                            {m.moved_on}
                                        </Td>
                                        <Td muted>{m.user?.name || '—'}</Td>
                                        <Td muted>{m.project?.name || '—'}</Td>
                                        <Td muted>{m.reference || m.invoice_ref || '—'}</Td>
                                        <Td align="end" className="tabular-nums">
                                            {m.previous_qty} → {m.new_qty}
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
