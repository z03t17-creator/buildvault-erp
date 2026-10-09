import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
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
            <PageShell>
                <DataPanel>
                    <div className="flex flex-wrap items-end gap-3">
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
                </DataPanel>

                {list.length === 0 ? (
                    <EmptyState title={t('productions_empty')} />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="52rem">
                            <thead>
                                <tr>
                                    <Th>{t('worker')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('unit_type')}</Th>
                                    <Th align="end">{t('assigned')}</Th>
                                    <Th align="end">{t('completed')}</Th>
                                    <Th align="end">{t('received')}</Th>
                                    <Th align="end">{t('remaining')}</Th>
                                    <Th align="end">{t('progress_pct')}</Th>
                                    <Th>{t('date')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((row) => (
                                    <tr key={row.id}>
                                        <Td>
                                            <Link
                                                href={route('productions.show', row.id)}
                                                className="text-emerald-700 underline dark:text-emerald-400"
                                            >
                                                {row.worker?.name || '—'}
                                            </Link>
                                        </Td>
                                        <Td>{row.project?.name || '—'}</Td>
                                        <Td>{unitLabel(t, row)}</Td>
                                        <Td align="end" className="tabular-nums">
                                            {formatQty(row.assigned)}
                                        </Td>
                                        <Td align="end" className="tabular-nums">
                                            {formatQty(row.completed)}
                                        </Td>
                                        <Td align="end" className="tabular-nums">
                                            {formatQty(row.received)}
                                        </Td>
                                        <Td
                                            align="end"
                                            className="tabular-nums text-amber-700 dark:text-amber-300"
                                        >
                                            {formatQty(row.remaining)}
                                        </Td>
                                        <Td align="end" className="tabular-nums">
                                            {formatQty(row.progress_pct)}%
                                        </Td>
                                        <Td muted className="tabular-nums">
                                            {row.recorded_on}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}
                <p className="text-xs text-slate-500">{t('production_qty_hint')}</p>
            </PageShell>
        </AuthenticatedLayout>
    );
}
