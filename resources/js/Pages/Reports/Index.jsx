import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const selectClass =
    'mt-1.5 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

const CATEGORY_ORDER = ['financial', 'payroll', 'stock', 'project'];

export default function Index({ catalog, categories, can_financial, legacy }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const projectList = legacy?.projects || [];
    const workerList = legacy?.workers || [];
    const payoutList = legacy?.payouts || [];

    const [projectId, setProjectId] = useState(projectList[0] ? String(projectList[0].id) : '');
    const [workerId, setWorkerId] = useState(workerList[0] ? String(workerList[0].id) : '');
    const [month, setMonth] = useState(legacy?.default_month || '');

    const selectedWorker = useMemo(
        () => workerList.find((w) => String(w.id) === String(workerId)),
        [workerList, workerId],
    );

    const orderedCategories = (categories || CATEGORY_ORDER).filter((c) => catalog?.[c]?.length);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('reports')}
                    subtitle={t('reports_suite_subtitle')}
                />
            }
        >
            <Head title={t('reports')} />

            <PageShell>
                {orderedCategories.map((category) => (
                    <DataPanel
                        key={category}
                        title={t(`report_category_${category}`)}
                        subtitle={t(`report_category_${category}_hint`)}
                        padded={false}
                    >
                        <div className="grid gap-px bg-slate-200/70 dark:bg-slate-800 sm:grid-cols-2 lg:grid-cols-3">
                            {(catalog[category] || []).map((item) => (
                                <Link
                                    key={item.type}
                                    href={item.href}
                                    className="group bg-white px-5 py-5 transition hover:bg-emerald-50/60 dark:bg-slate-950 dark:hover:bg-emerald-950/30"
                                >
                                    <h4 className="font-display text-base font-semibold text-slate-900 group-hover:text-emerald-800 dark:text-white dark:group-hover:text-emerald-300">
                                        {t(`report_${item.type}`)}
                                    </h4>
                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {t(`report_${item.type}_hint`)}
                                    </p>
                                    <p className="mt-3 text-xs font-medium uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                                        {t('open_report')} →
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </DataPanel>
                ))}

                {can_financial && legacy && (
                    <>
                        <DataPanel
                            title={t('report_quick_exports')}
                            subtitle={t('report_quick_exports_hint')}
                        >
                            <div className="grid gap-6 lg:grid-cols-2">
                                <div>
                                    <InputLabel value={t('report_project_excel')} />
                                    <select
                                        className={selectClass}
                                        value={projectId}
                                        onChange={(e) => setProjectId(e.target.value)}
                                    >
                                        {projectList.length === 0 && (
                                            <option value="">{t('no_projects')}</option>
                                        )}
                                        {projectList.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                    <div className="mt-3">
                                        <a
                                            href={projectId ? route('exports.project', projectId) : '#'}
                                            className={!projectId ? 'pointer-events-none opacity-50' : ''}
                                        >
                                            <PrimaryButton type="button" disabled={!projectId}>
                                                {t('download_excel')}
                                            </PrimaryButton>
                                        </a>
                                    </div>
                                </div>

                                <div>
                                    <InputLabel value={t('report_worker_pdf')} />
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <select
                                            className={selectClass}
                                            value={workerId}
                                            onChange={(e) => setWorkerId(e.target.value)}
                                        >
                                            {workerList.length === 0 && (
                                                <option value="">{t('no_workers')}</option>
                                            )}
                                            {workerList.map((w) => (
                                                <option key={w.id} value={w.id}>
                                                    {w.name}
                                                    {w.project ? ` · ${w.project.name}` : ''}
                                                </option>
                                            ))}
                                        </select>
                                        <input
                                            type="month"
                                            className={selectClass}
                                            value={month}
                                            onChange={(e) => setMonth(e.target.value)}
                                        />
                                    </div>
                                    {selectedWorker && (
                                        <p className="mt-2 text-xs text-slate-500">
                                            {t('role')}: {selectedWorker.role}
                                        </p>
                                    )}
                                    <div className="mt-3">
                                        <a
                                            href={
                                                workerId
                                                    ? `${route('exports.worker', workerId)}${month ? `?month=${month}` : ''}`
                                                    : '#'
                                            }
                                            className={!workerId ? 'pointer-events-none opacity-50' : ''}
                                        >
                                            <PrimaryButton type="button" disabled={!workerId}>
                                                {t('download_pdf')}
                                            </PrimaryButton>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </DataPanel>

                        <DataPanel
                            title={t('report_vouchers')}
                            subtitle={t('report_vouchers_hint')}
                            padded={false}
                        >
                            {payoutList.length === 0 ? (
                                <p className="px-5 py-10 text-center text-sm text-slate-500">
                                    {t('no_payouts_yet')}
                                </p>
                            ) : (
                                <DataTable minWidth="44rem">
                                    <thead>
                                        <tr>
                                            <Th>ID</Th>
                                            <Th>{t('project')}</Th>
                                            <Th>{t('worker')}</Th>
                                            <Th>{t('category')}</Th>
                                            <Th align="end">{iqd}</Th>
                                            <Th>{t('status')}</Th>
                                            <Th align="end" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {payoutList.map((p) => (
                                            <tr key={p.id}>
                                                <Td muted className="tabular-nums">
                                                    #{p.id}
                                                </Td>
                                                <Td>{p.project?.name || '—'}</Td>
                                                <Td>{p.worker?.name || '—'}</Td>
                                                <Td muted>{p.category}</Td>
                                                <Td align="end">
                                                    <MoneyAmount
                                                        value={p.amount_iqd}
                                                        label={iqd}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                </Td>
                                                <Td>
                                                    <StatusBadge status={p.status} />
                                                </Td>
                                                <Td align="end">
                                                    <a href={route('exports.voucher', p.id)}>
                                                        <SecondaryButton type="button">
                                                            {t('voucher_pdf')}
                                                        </SecondaryButton>
                                                    </a>
                                                </Td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </DataTable>
                            )}
                        </DataPanel>
                    </>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
