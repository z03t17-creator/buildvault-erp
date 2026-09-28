import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, router } from '@inertiajs/react';

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
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap gap-3">
                        <select
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                            value={filters?.type || ''}
                            onChange={(e) => apply({ type: e.target.value })}
                        >
                            <option value="">{t('all_types')}</option>
                            <option value="in">IN</option>
                            <option value="out">OUT</option>
                        </select>
                        <select
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
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
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
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
                    <div className="overflow-x-auto border border-slate-200/80 bg-white/80 dark:border-slate-700 dark:bg-slate-900/70">
                        <table className="min-w-full text-sm">
                            <thead className="border-b text-xs uppercase text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-start">{t('type')}</th>
                                    <th className="px-3 py-2 text-start">{t('product')}</th>
                                    <th className="px-3 py-2 text-start">{t('quantity')}</th>
                                    <th className="px-3 py-2 text-start">{t('date')}</th>
                                    <th className="px-3 py-2 text-start">{t('user')}</th>
                                    <th className="px-3 py-2 text-start">{t('project')}</th>
                                    <th className="px-3 py-2 text-start">{t('reference')}</th>
                                    <th className="px-3 py-2 text-start">{t('qty_change')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {list.map((m) => (
                                    <tr key={m.id}>
                                        <td className="px-3 py-2 uppercase">{m.type}</td>
                                        <td className="px-3 py-2">{m.item?.name || '—'}</td>
                                        <td className="px-3 py-2 tabular-nums">{m.quantity}</td>
                                        <td className="px-3 py-2">{m.moved_on}</td>
                                        <td className="px-3 py-2">{m.user?.name || '—'}</td>
                                        <td className="px-3 py-2">{m.project?.name || '—'}</td>
                                        <td className="px-3 py-2">{m.reference || m.invoice_ref || '—'}</td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {m.previous_qty} → {m.new_qty}
                                        </td>
                                    </tr>
                                ))}
                                {!list.length && (
                                    <tr>
                                        <td colSpan={8} className="px-3 py-8 text-center text-slate-500">
                                            {t('no_stock_movements')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
