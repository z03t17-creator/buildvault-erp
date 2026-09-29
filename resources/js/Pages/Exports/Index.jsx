import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Index({ projects, workers, payouts, default_month }) {
    const projectList = projects || [];
    const workerList = workers || [];
    const payoutList = payouts || [];

    const [projectId, setProjectId] = useState(projectList[0] ? String(projectList[0].id) : '');
    const [workerId, setWorkerId] = useState(workerList[0] ? String(workerList[0].id) : '');
    const [month, setMonth] = useState(default_month || '');

    const selectedWorker = useMemo(
        () => workerList.find((w) => String(w.id) === String(workerId)),
        [workerList, workerId],
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Exports"
                    subtitle="Project Excel · worker PDF · payout vouchers"
                />
            }
        >
            <Head title="Exports" />

            <PageShell>
                <div className="grid gap-6 lg:grid-cols-2">
                    <DataPanel
                        title="Per-project Excel"
                        subtitle="Sheets: payouts, insurance holds, documents."
                    >
                        <div>
                            <InputLabel value="Project" />
                            <select
                                className={selectClass}
                                value={projectId}
                                onChange={(e) => setProjectId(e.target.value)}
                            >
                                {projectList.length === 0 && <option value="">No projects</option>}
                                {projectList.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="mt-4">
                            <a
                                href={projectId ? route('exports.project', projectId) : '#'}
                                className={!projectId ? 'pointer-events-none opacity-50' : ''}
                            >
                                <PrimaryButton type="button" disabled={!projectId}>
                                    Download Excel
                                </PrimaryButton>
                            </a>
                        </div>
                    </DataPanel>

                    <DataPanel
                        title="Per-worker PDF"
                        subtitle="Profile, photo/ID refs, payroll summary for the month."
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Worker" />
                                <select
                                    className={selectClass}
                                    value={workerId}
                                    onChange={(e) => setWorkerId(e.target.value)}
                                >
                                    {workerList.length === 0 && <option value="">No workers</option>}
                                    {workerList.map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name}
                                            {w.project ? ` · ${w.project.name}` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Month" />
                                <input
                                    type="month"
                                    className={selectClass}
                                    value={month}
                                    onChange={(e) => setMonth(e.target.value)}
                                />
                            </div>
                        </div>
                        {selectedWorker && (
                            <p className="mt-2 text-xs text-slate-500">
                                Role: {selectedWorker.role}
                            </p>
                        )}
                        <div className="mt-4">
                            <a
                                href={
                                    workerId
                                        ? `${route('exports.worker', workerId)}${month ? `?month=${month}` : ''}`
                                        : '#'
                                }
                                className={!workerId ? 'pointer-events-none opacity-50' : ''}
                            >
                                <PrimaryButton type="button" disabled={!workerId}>
                                    Download PDF
                                </PrimaryButton>
                            </a>
                        </div>
                    </DataPanel>
                </div>

                <DataPanel
                    title="Payout vouchers"
                    subtitle="Dual-currency PDF vouchers (USD + IQD) with localized labels."
                    padded={false}
                >
                    {payoutList.length === 0 ? (
                        <div className="p-5">
                            <EmptyState title="No payouts yet." />
                        </div>
                    ) : (
                        <DataTable minWidth="40rem" caption="Payout vouchers">
                            <thead>
                                <tr>
                                    <Th>ID</Th>
                                    <Th>Project</Th>
                                    <Th>Worker</Th>
                                    <Th>Category</Th>
                                    <Th align="end">IQD</Th>
                                    <Th>Status</Th>
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
                                                label="IQD"
                                                size="sm"
                                                showLabel={false}
                                            />
                                            <div
                                                dir="ltr"
                                                className="text-xs tabular-nums text-slate-500"
                                            >
                                                ${Number(p.amount_usd).toFixed(2)} USD
                                            </div>
                                        </Td>
                                        <Td>
                                            <StatusBadge status={p.status} />
                                        </Td>
                                        <Td align="end">
                                            <a href={route('exports.voucher', p.id)}>
                                                <SecondaryButton type="button">
                                                    Voucher PDF
                                                </SecondaryButton>
                                            </a>
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
