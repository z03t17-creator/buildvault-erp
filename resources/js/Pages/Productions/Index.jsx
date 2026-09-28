import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const selectClass =
    'rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

function formatQty(n) {
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
    }).format(Number(n) || 0);
}

function unitLabel(t, row) {
    const key = `unit_type_${row.unit_type}`;
    const base = t(key) !== key ? t(key) : row.unit_type;
    if (row.unit_type === 'other' && row.unit_label) {
        return `${base}: ${row.unit_label}`;
    }
    return base;
}

export default function Index({ productions, filters, projects, workers }) {
    const t = useTranslations();
    const list = productions || [];
    const canCreate = useCan('productions.create');
    const [projectId, setProjectId] = useState(filters?.project_id ? String(filters.project_id) : '');
    const [workerId, setWorkerId] = useState(filters?.worker_id ? String(filters.worker_id) : '');

    const applyFilters = () => {
        router.get(
            route('productions.index'),
            {
                project_id: projectId || undefined,
                worker_id: workerId || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setProjectId('');
        setWorkerId('');
        router.get(route('productions.index'), {}, { preserveState: true, replace: true });
    };

    const filteredWorkers = (workers || []).filter(
        (w) => !projectId || String(w.project_id) === String(projectId),
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('productions')}
                    subtitle={t('productions_subtitle')}
                    actions={
                        canCreate ? (
                            <Link href={route('productions.create')}>
                                <PrimaryButton type="button">{t('record_production')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('productions')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-end gap-3 border border-slate-200/80 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/70">
                        <div>
                            <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                {t('project')}
                            </label>
                            <select
                                className={selectClass}
                                value={projectId}
                                onChange={(e) => {
                                    setProjectId(e.target.value);
                                    setWorkerId('');
                                }}
                            >
                                <option value="">{t('all_projects')}</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                {t('worker')}
                            </label>
                            <select
                                className={selectClass}
                                value={workerId}
                                onChange={(e) => setWorkerId(e.target.value)}
                            >
                                <option value="">{t('all_workers')}</option>
                                {filteredWorkers.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <PrimaryButton type="button" onClick={applyFilters}>
                            {t('filter')}
                        </PrimaryButton>
                        <SecondaryButton type="button" onClick={clearFilters}>
                            {t('clear_filters')}
                        </SecondaryButton>
                    </div>

                    <div className="bv-surface">
                        <div className="bv-table-wrap">
                            <table className="bv-table min-w-[52rem]">
                                <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                    <tr>
                                        <th className="px-3 py-2 text-start">{t('worker')}</th>
                                        <th className="px-3 py-2 text-start">{t('project')}</th>
                                        <th className="px-3 py-2 text-start">{t('unit_type')}</th>
                                        <th className="px-3 py-2 text-end">{t('assigned')}</th>
                                        <th className="px-3 py-2 text-end">{t('completed')}</th>
                                        <th className="px-3 py-2 text-end">{t('received')}</th>
                                        <th className="px-3 py-2 text-end">{t('remaining')}</th>
                                        <th className="px-3 py-2 text-end">{t('progress_pct')}</th>
                                        <th className="px-3 py-2 text-start">{t('date')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {list.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-3 py-2">
                                                <Link
                                                    href={route('productions.show', row.id)}
                                                    className="text-emerald-700 underline dark:text-emerald-400"
                                                >
                                                    {row.worker?.name || '—'}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2">{row.project?.name || '—'}</td>
                                            <td className="px-3 py-2">{unitLabel(t, row)}</td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                {formatQty(row.assigned)}
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                {formatQty(row.completed)}
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                {formatQty(row.received)}
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums text-amber-700 dark:text-amber-300">
                                                {formatQty(row.remaining)}
                                            </td>
                                            <td className="px-3 py-2 text-end tabular-nums">
                                                {formatQty(row.progress_pct)}%
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">{row.recorded_on}</td>
                                        </tr>
                                    ))}
                                    {!list.length && (
                                        <tr>
                                            <td colSpan={9} className="px-3 py-8 text-center text-slate-500">
                                                {t('productions_empty')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <p className="text-xs text-slate-500">{t('production_qty_hint')}</p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
