import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
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

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <section className="bv-surface grid gap-4 p-5 sm:grid-cols-2">
                        <div>
                            <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                Per-project Excel
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">
                                Sheets: attendances, payouts, insurance holds, documents.
                            </p>
                            <div className="mt-4">
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
                        </div>

                        <div>
                            <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                Per-worker PDF
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">
                                Profile, photo/ID refs, attendance, payroll summary for the month.
                            </p>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
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
                        </div>
                    </section>

                    <section className="bv-surface p-5">
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            Payout vouchers
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Dual-currency PDF vouchers (USD + IQD) with localized labels.
                        </p>
                        {payoutList.length === 0 ? (
                            <p className="mt-4 text-sm text-slate-500">No payouts yet.</p>
                        ) : (
                            <div className="bv-table-wrap mt-4">
                                <table className="bv-table min-w-[40rem]">
                                    <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                        <tr>
                                            <th className="px-3 py-2 text-start">ID</th>
                                            <th className="px-3 py-2 text-start">Project</th>
                                            <th className="px-3 py-2 text-start">Worker</th>
                                            <th className="px-3 py-2 text-start">Category</th>
                                            <th className="px-3 py-2 text-start">USD / IQD</th>
                                            <th className="px-3 py-2 text-start">Status</th>
                                            <th className="px-3 py-2 text-start"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {payoutList.map((p) => (
                                            <tr key={p.id}>
                                                <td className="px-3 py-2 tabular-nums">#{p.id}</td>
                                                <td className="px-3 py-2">{p.project?.name || '—'}</td>
                                                <td className="px-3 py-2">{p.worker?.name || '—'}</td>
                                                <td className="px-3 py-2">{p.category}</td>
                                                <td className="px-3 py-2 tabular-nums text-sm">
                                                    ${Number(p.amount_usd).toFixed(2)}
                                                    <span className="ms-1 text-slate-500">
                                                        / {Number(p.amount_iqd).toLocaleString()}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2"><StatusBadge status={p.status} /></td>
                                                <td className="px-3 py-2 text-end">
                                                    <a href={route('exports.voucher', p.id)}>
                                                        <SecondaryButton type="button">Voucher PDF</SecondaryButton>
                                                    </a>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
