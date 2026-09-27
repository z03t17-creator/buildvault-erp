import PageHeader from '@/Components/PageHeader';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function formatUsd(n) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(Number(n) || 0);
}

const selectClass =
    'rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Payroll({
    month,
    monthLabel,
    from,
    to,
    projectId,
    projects,
    rows,
    totals,
}) {
    const [selectedMonth, setSelectedMonth] = useState(month || '');
    const [selectedProject, setSelectedProject] = useState(projectId ? String(projectId) : '');

    const applyFilters = (nextMonth = selectedMonth, nextProject = selectedProject) => {
        const params = { month: nextMonth };
        if (nextProject) {
            params.project_id = nextProject;
        }
        router.get(route('dashboards.payroll'), params, {
            preserveState: true,
            replace: true,
        });
    };

    const list = rows || [];
    const sum = totals || {};

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Payroll"
                    subtitle={`${monthLabel || month} · present days, OT, penalties, net`}
                    actions={
                        <Link href={route('dashboards.vault')}>
                            <SecondaryButton type="button">Vault</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={`Payroll · ${monthLabel || month}`} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="bv-surface flex flex-col gap-4 p-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                        <div className="flex flex-wrap gap-4">
                            <div>
                                <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                    Month
                                </label>
                                <input
                                    type="month"
                                    className={`mt-1 block ${selectClass}`}
                                    value={selectedMonth}
                                    onChange={(e) => {
                                        setSelectedMonth(e.target.value);
                                        applyFilters(e.target.value, selectedProject);
                                    }}
                                />
                            </div>
                            <div>
                                <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                    Project
                                </label>
                                <select
                                    className={`mt-1 block min-w-[12rem] ${selectClass}`}
                                    value={selectedProject}
                                    onChange={(e) => {
                                        setSelectedProject(e.target.value);
                                        applyFilters(selectedMonth, e.target.value);
                                    }}
                                >
                                    <option value="">All projects</option>
                                    {(projects || []).map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Period {from} → {to}
                        </p>
                    </section>

                    <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <SummaryCard label="Workers" value={String(sum.workers ?? 0)} />
                        <SummaryCard label="Present days" value={String(sum.days_present ?? 0)} />
                        <SummaryCard label="OT hours" value={Number(sum.overtime_hours || 0).toFixed(2)} />
                        <SummaryCard label="Net payroll" value={formatUsd(sum.net_pay_usd)} accent />
                    </section>

                    <section className="bv-surface">
                        <div className="bv-table-wrap">
                        <table className="bv-table min-w-[48rem]">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                <tr>
                                    <th className="px-3 py-2 text-start">Worker</th>
                                    <th className="px-3 py-2 text-start">Project</th>
                                    <th className="px-3 py-2 text-end">Present</th>
                                    <th className="px-3 py-2 text-end">OT hrs</th>
                                    <th className="px-3 py-2 text-end">Penalties</th>
                                    <th className="px-3 py-2 text-end">Base</th>
                                    <th className="px-3 py-2 text-end">OT pay</th>
                                    <th className="px-3 py-2 text-end">Net</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {list.map((row) => (
                                    <tr key={row.worker_id}>
                                        <td className="px-3 py-2">
                                            <Link
                                                href={route('workers.show', row.worker_id)}
                                                className="font-medium text-emerald-700 underline dark:text-emerald-400"
                                            >
                                                {row.name}
                                            </Link>
                                            {row.role && (
                                                <span className="ms-2 text-xs capitalize text-slate-400">{row.role}</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-slate-600 dark:text-slate-300">
                                            {row.project?.name || '—'}
                                        </td>
                                        <td className="px-3 py-2 text-end tabular-nums">{row.days_present}</td>
                                        <td className="px-3 py-2 text-end tabular-nums">{Number(row.overtime_hours).toFixed(2)}</td>
                                        <td className="px-3 py-2 text-end tabular-nums text-rose-700 dark:text-rose-300">
                                            {formatUsd(row.penalties_usd)}
                                            {(row.late_minutes > 0 || row.unexcused_absences > 0) && (
                                                <div className="text-[10px] text-slate-400">
                                                    {row.late_minutes > 0 ? `${row.late_minutes}m late` : ''}
                                                    {row.late_minutes > 0 && row.unexcused_absences > 0 ? ' · ' : ''}
                                                    {row.unexcused_absences > 0 ? `${row.unexcused_absences} absent` : ''}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-end tabular-nums">{formatUsd(row.base_pay_usd)}</td>
                                        <td className="px-3 py-2 text-end tabular-nums">{formatUsd(row.overtime_pay_usd)}</td>
                                        <td className="px-3 py-2 text-end font-display text-base font-semibold tabular-nums text-slate-900 dark:text-white">
                                            {formatUsd(row.net_pay_usd)}
                                        </td>
                                    </tr>
                                ))}
                                {!list.length && (
                                    <tr>
                                        <td colSpan={8} className="px-3 py-10 text-center text-slate-500">
                                            No workers for this filter. Add crew or widen the project filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            {list.length > 0 && (
                                <tfoot className="border-t border-slate-200 bg-slate-50/80 text-sm font-semibold dark:border-slate-800 dark:bg-slate-950/50">
                                    <tr>
                                        <td className="px-3 py-2" colSpan={2}>Totals</td>
                                        <td className="px-3 py-2 text-end tabular-nums">{sum.days_present}</td>
                                        <td className="px-3 py-2 text-end tabular-nums">{Number(sum.overtime_hours || 0).toFixed(2)}</td>
                                        <td className="px-3 py-2 text-end tabular-nums text-rose-700 dark:text-rose-300">
                                            {formatUsd(sum.penalties_usd)}
                                        </td>
                                        <td className="px-3 py-2" colSpan={2} />
                                        <td className="px-3 py-2 text-end font-display text-base tabular-nums text-emerald-700 dark:text-emerald-400">
                                            {formatUsd(sum.net_pay_usd)}
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function SummaryCard({ label, value, accent }) {
    return (
        <div className="bv-card bg-gradient-to-br from-white to-slate-50/80 px-4 py-3 dark:from-slate-900 dark:to-slate-950">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{label}</p>
            <p
                className={`mt-1 font-display text-2xl font-semibold tabular-nums ${
                    accent
                        ? 'text-emerald-700 dark:text-emerald-400'
                        : 'text-slate-900 dark:text-white'
                }`}
            >
                {value}
            </p>
        </div>
    );
}
