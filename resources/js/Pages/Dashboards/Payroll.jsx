import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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
    const t = useTranslations();
    const iqd = t('IQD');
    const canVault = useCan('vault.view');
    const canReports = useCan('vault.exports');
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
                    title={t('payroll')}
                    subtitle={t('payroll_subtitle', { month: monthLabel || month })}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canReports && (
                                <Link href={route('reports.index')}>
                                    <SecondaryButton type="button">{t('reports')}</SecondaryButton>
                                </Link>
                            )}
                            {canVault && (
                                <Link href={route('dashboards.vault')}>
                                    <SecondaryButton type="button">{t('vault')}</SecondaryButton>
                                </Link>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={`${t('payroll')} · ${monthLabel || month}`} />

            <PageShell>
                <DataPanel>
                    <div className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                        <div className="flex flex-wrap gap-4">
                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('month')}
                                </label>
                                <input
                                    type="month"
                                    className={`mt-1.5 block ${selectClass}`}
                                    value={selectedMonth}
                                    onChange={(e) => {
                                        setSelectedMonth(e.target.value);
                                        applyFilters(e.target.value, selectedProject);
                                    }}
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('project')}
                                </label>
                                <select
                                    className={`mt-1.5 block min-w-[12rem] ${selectClass}`}
                                    value={selectedProject}
                                    onChange={(e) => {
                                        setSelectedProject(e.target.value);
                                        applyFilters(selectedMonth, e.target.value);
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
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            {t('period')} {from} → {to}
                        </p>
                    </div>
                    <p className="mt-4 text-sm text-slate-600 dark:text-slate-300">
                        {t('payroll_net_formula')}
                    </p>
                </DataPanel>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <SummaryCard label={t('workers')} value={String(sum.workers ?? 0)} />
                    <SummaryCard
                        label={`${t('col_penalties')} (${iqd})`}
                        value={<MoneyAmount value={sum.penalties_iqd} label={iqd} size="lg" showLabel={false} />}
                    />
                    <SummaryCard
                        label={`${t('col_advances')} (${iqd})`}
                        value={<MoneyAmount value={sum.advances_iqd} label={iqd} size="lg" showLabel={false} />}
                    />
                    <SummaryCard
                        label={`${t('col_insurance_holdback')} (${iqd})`}
                        value={
                            <MoneyAmount
                                value={sum.insurance_holdback_iqd}
                                label={iqd}
                                size="lg"
                                showLabel={false}
                            />
                        }
                    />
                </section>

                <section className="grid gap-4 sm:grid-cols-2">
                    <SummaryCard
                        label={`${t('gross_payroll')} (${iqd})`}
                        value={<MoneyAmount value={sum.gross_pay_iqd} label={iqd} size="xl" showLabel={false} />}
                    />
                    <SummaryCard
                        label={`${t('net_payroll')} (${iqd})`}
                        value={
                            <MoneyAmount
                                value={sum.net_pay_iqd}
                                label={iqd}
                                size="xl"
                                showLabel={false}
                                accent
                            />
                        }
                        accent
                    />
                </section>

                <DataPanel
                    title={t('payroll')}
                    subtitle={`${iqd} · ${monthLabel || month}`}
                    padded={false}
                >
                    <DataTable minWidth="48rem" caption={t('payroll')}>
                        <thead>
                            <tr>
                                <Th>{t('Worker')}</Th>
                                <Th>{t('Project')}</Th>
                                <Th align="end">{t('col_base')}</Th>
                                <Th align="end">{t('col_ot_pay')}</Th>
                                <Th align="end">{t('col_penalties')}</Th>
                                <Th align="end">{t('col_insurance_holdback')}</Th>
                                <Th align="end">{t('col_advances')}</Th>
                                <Th align="end">{t('col_net')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {list.map((row) => (
                                <tr key={row.worker_id}>
                                    <Td>
                                        <Link
                                            href={route('workers.show', row.worker_id)}
                                            className="font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                                        >
                                            {row.name}
                                        </Link>
                                        {row.role && (
                                            <span className="ms-2 inline-flex align-middle">
                                                <StatusBadge status={row.role} />
                                            </span>
                                        )}
                                    </Td>
                                    <Td muted>{row.project?.name || '—'}</Td>
                                    <Td align="end">
                                        <MoneyAmount
                                            value={row.base_pay_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                        />
                                    </Td>
                                    <Td align="end">
                                        <MoneyAmount
                                            value={row.overtime_pay_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                        />
                                    </Td>
                                    <Td align="end" className="text-rose-700 dark:text-rose-300">
                                        <MoneyAmount
                                            value={row.penalties_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            className="text-rose-700 dark:text-rose-300"
                                        />
                                    </Td>
                                    <Td align="end" className="text-amber-700 dark:text-amber-300">
                                        <MoneyAmount
                                            value={row.insurance_holdback_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            className="text-amber-700 dark:text-amber-300"
                                        />
                                        {row.insurance_holdback_pct != null && (
                                            <div className="mt-0.5 text-[11px] font-normal text-slate-400">
                                                {t('holdback_line', {
                                                    percent: Number(row.insurance_holdback_pct).toFixed(0),
                                                })}
                                            </div>
                                        )}
                                    </Td>
                                    <Td align="end" className="text-amber-700 dark:text-amber-300">
                                        <MoneyAmount
                                            value={row.advances_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            className="text-amber-700 dark:text-amber-300"
                                        />
                                    </Td>
                                    <Td align="end">
                                        <MoneyAmount
                                            value={row.net_pay_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            className="font-semibold"
                                        />
                                    </Td>
                                </tr>
                            ))}
                            {!list.length && (
                                <tr>
                                    <Td colSpan={8} align="center" muted className="py-12">
                                        {t('payroll_empty')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                        {list.length > 0 && (
                            <tfoot>
                                <tr>
                                    <Td className="font-semibold" colSpan={2}>
                                        {t('totals')}
                                    </Td>
                                    <Td />
                                    <Td />
                                    <Td align="end" className="font-semibold text-rose-700 dark:text-rose-300">
                                        <MoneyAmount
                                            value={sum.penalties_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            className="text-rose-700 dark:text-rose-300"
                                        />
                                    </Td>
                                    <Td align="end" className="font-semibold text-amber-700 dark:text-amber-300">
                                        <MoneyAmount
                                            value={sum.insurance_holdback_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            className="text-amber-700 dark:text-amber-300"
                                        />
                                    </Td>
                                    <Td align="end" className="font-semibold text-amber-700 dark:text-amber-300">
                                        <MoneyAmount
                                            value={sum.advances_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            className="text-amber-700 dark:text-amber-300"
                                        />
                                    </Td>
                                    <Td align="end">
                                        <MoneyAmount
                                            value={sum.net_pay_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            accent
                                        />
                                    </Td>
                                </tr>
                            </tfoot>
                        )}
                    </DataTable>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}

function SummaryCard({ label, value, accent }) {
    return (
        <div
            className={
                'bv-card px-5 py-4 ' +
                (accent
                    ? 'border-emerald-300/60 bg-gradient-to-br from-emerald-50/80 to-white dark:border-emerald-700/40 dark:from-emerald-950/30 dark:to-slate-900'
                    : '')
            }
        >
            <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {label}
            </p>
            <div className="mt-2">{value}</div>
        </div>
    );
}
