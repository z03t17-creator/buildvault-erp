import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fieldClass =
    'mt-1.5 block min-h-[2.5rem] min-w-[11rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        indigo: 'text-indigo-900 dark:text-indigo-100',
        rose: 'text-rose-700 dark:text-rose-300',
        amber: 'text-amber-800 dark:text-amber-200',
    };

    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className={
                    'mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums sm:text-3xl ' +
                    (tones[tone] || tones.default)
                }
            >
                {value == null || value === '' ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                        accent={tone === 'indigo'}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

function CountStat({ label, value, hint }) {
    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1.5 font-sans text-2xl font-semibold tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                {value ?? 0}
            </div>
            {hint ? (
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            ) : null}
        </div>
    );
}

function RoleChip({ role, t }) {
    const key = `worker_role_${role}`;
    const label = t(key) !== key ? t(key) : role?.replace(/_/g, ' ') || '—';
    const tones = {
        engineer: 'bg-sky-500/15 text-sky-900 dark:text-sky-300',
        supervisor: 'bg-teal-500/15 text-teal-900 dark:text-teal-300',
        subcontractor: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        laborer: 'bg-indigo-500/15 text-indigo-900 dark:text-indigo-300',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-0.5 text-[11px] font-semibold capitalize ' +
                (tones[role] || tones.laborer)
            }
        >
            {label}
        </span>
    );
}

function MoneyCell({ value, currency, className = '' }) {
    if (value == null || value === '' || Number(value) === 0) {
        return <span className="text-slate-300 dark:text-slate-600">—</span>;
    }

    return (
        <MoneyAmount
            value={value}
            label={currency}
            size="sm"
            showLabel={false}
            className={className}
        />
    );
}

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
    const usd = t('USD');
    const iqd = t('IQD');
    const canVault = useCan('vault.view');
    const canWorkers = useCan('workers.viewAny');
    const [selectedMonth, setSelectedMonth] = useState(month || '');
    const [selectedProject, setSelectedProject] = useState(
        projectId ? String(projectId) : '',
    );

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
                    subtitle={t('payroll_page_hint', {
                        month: monthLabel || month,
                    })}
                    icon={<NavIcon name="payroll" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canWorkers && (
                                <Link
                                    href={route('workers.index', {
                                        labor_kind: 'worker',
                                    })}
                                >
                                    <SecondaryButton type="button">
                                        <NavIcon name="payroll" className="text-sm" />
                                        {t('worker_directory')}
                                    </SecondaryButton>
                                </Link>
                            )}
                            {canVault && (
                                <Link href={route('dashboards.vault')}>
                                    <SecondaryButton type="button">
                                        <NavIcon name="vault" className="text-sm" />
                                        {t('vault')}
                                    </SecondaryButton>
                                </Link>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={`${t('payroll')} · ${monthLabel || month}`} />

            <PageShell className="!space-y-6">
                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                            <NavIcon name="payroll" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('payroll_filters_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('payroll_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="bv-card flex flex-col gap-4 p-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between sm:p-5">
                        <div className="flex flex-wrap gap-4">
                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('month')}
                                </label>
                                <input
                                    type="month"
                                    className={fieldClass}
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
                                    className={fieldClass}
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
                        <p className="font-sans text-xs tabular-nums text-slate-500 dark:text-slate-400">
                            {from} → {to}
                        </p>
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                            <NavIcon name="payroll" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('payroll_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('payroll_overview_hint', {
                                    month: monthLabel || month,
                                })}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <CountStat
                            label={t('workers')}
                            value={sum.workers ?? 0}
                            hint={t('payroll_workers_hint')}
                        />
                        <MoneyStat
                            label={t('gross_payroll')}
                            value={sum.gross_pay_usd}
                            currency={usd}
                            tone="indigo"
                        />
                        <MoneyStat
                            label={t('col_penalties')}
                            value={sum.penalties_usd}
                            currency={usd}
                            tone="rose"
                        />
                        <MoneyStat
                            label={t('net_payroll')}
                            value={sum.net_pay_usd}
                            currency={usd}
                            tone="indigo"
                        />
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <MoneyStat
                            label={t('monthly_salary_iqd')}
                            value={sum.monthly_salary_iqd}
                            currency={iqd}
                        />
                        <MoneyStat
                            label={`${t('col_penalties')} (${iqd})`}
                            value={sum.penalties_iqd}
                            currency={iqd}
                            tone="rose"
                        />
                        <MoneyStat
                            label={`${t('col_advances')} (${usd})`}
                            value={sum.advances_usd}
                            currency={usd}
                            tone="amber"
                        />
                        <MoneyStat
                            label={`${t('col_advances')} (${iqd})`}
                            value={sum.advances_iqd}
                            currency={iqd}
                            tone="amber"
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="payroll"
                        title={t('payroll_empty_title')}
                        description={t('payroll_empty_hint')}
                        action={
                            canWorkers ? (
                                <Link
                                    href={route('workers.index', {
                                        labor_kind: 'worker',
                                    })}
                                >
                                    <SecondaryButton type="button">
                                        <NavIcon name="payroll" className="text-sm" />
                                        {t('worker_directory')}
                                    </SecondaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <div className="flex items-center gap-3">
                                <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                                    <NavIcon name="payroll" className="text-base" />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        {t('payroll_table_title')}
                                    </p>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        {t('payroll_table_hint')}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <DataTable
                            minWidth="72rem"
                            caption={t('payroll')}
                            stickyFirstColumn
                        >
                            <thead>
                                <tr>
                                    <Th>{t('worker_name')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th
                                        align="end"
                                        className="text-indigo-800 dark:text-indigo-300"
                                    >
                                        {t('col_base')} ({usd})
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-indigo-800 dark:text-indigo-300"
                                    >
                                        {t('monthly_salary_iqd')}
                                    </Th>
                                    <Th align="end">
                                        {t('col_ot_pay')} ({usd})
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-rose-700 dark:text-rose-300"
                                    >
                                        {t('col_penalties')} ({usd})
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-rose-700 dark:text-rose-300"
                                    >
                                        {t('col_penalties')} ({iqd})
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-amber-800 dark:text-amber-300"
                                    >
                                        {t('col_insurance_holdback')} ({usd})
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-amber-800 dark:text-amber-300"
                                    >
                                        {t('col_advances')} ({usd})
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-amber-800 dark:text-amber-300"
                                    >
                                        {t('col_advances')} ({iqd})
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-indigo-800 dark:text-indigo-300"
                                    >
                                        {t('col_net')} ({usd})
                                    </Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((row) => (
                                    <tr key={row.worker_id}>
                                        <Td>
                                            <Link
                                                href={route(
                                                    'workers.show',
                                                    row.worker_id,
                                                )}
                                                className="inline-flex items-start gap-2.5"
                                            >
                                                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                                                    <NavIcon
                                                        name="payroll"
                                                        className="text-sm"
                                                    />
                                                </span>
                                                <span>
                                                    <span className="block font-medium text-indigo-950 underline-offset-2 hover:underline dark:text-indigo-100">
                                                        {row.name}
                                                    </span>
                                                    {row.role ? (
                                                        <span className="mt-1 inline-flex">
                                                            <RoleChip
                                                                role={row.role}
                                                                t={t}
                                                            />
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </Link>
                                        </Td>
                                        <Td muted>
                                            {row.project?.name || '—'}
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyCell
                                                value={row.base_pay_usd}
                                                currency={usd}
                                            />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyCell
                                                value={
                                                    Number(row.monthly_salary_iqd) >
                                                    0
                                                        ? row.monthly_salary_iqd
                                                        : null
                                                }
                                                currency={iqd}
                                            />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyCell
                                                value={row.overtime_pay_usd}
                                                currency={usd}
                                            />
                                            {Number(row.overtime_hours) > 0 ? (
                                                <div className="mt-0.5 text-[11px] font-normal text-slate-400">
                                                    {t('manual_ot_hours_line', {
                                                        hours: Number(
                                                            row.overtime_hours,
                                                        ).toFixed(2),
                                                    })}
                                                </div>
                                            ) : null}
                                        </Td>
                                        <Td
                                            align="end"
                                            money
                                            className="text-rose-700 dark:text-rose-300"
                                        >
                                            <MoneyCell
                                                value={row.penalties_usd}
                                                currency={usd}
                                                className="text-rose-700 dark:text-rose-300"
                                            />
                                        </Td>
                                        <Td
                                            align="end"
                                            money
                                            className="text-rose-700 dark:text-rose-300"
                                        >
                                            <MoneyCell
                                                value={row.penalties_iqd}
                                                currency={iqd}
                                                className="text-rose-700 dark:text-rose-300"
                                            />
                                        </Td>
                                        <Td
                                            align="end"
                                            money
                                            className="text-amber-800 dark:text-amber-300"
                                        >
                                            <MoneyCell
                                                value={
                                                    row.insurance_holdback_usd
                                                }
                                                currency={usd}
                                                className="text-amber-800 dark:text-amber-300"
                                            />
                                        </Td>
                                        <Td
                                            align="end"
                                            money
                                            className="text-amber-800 dark:text-amber-300"
                                        >
                                            <MoneyCell
                                                value={row.advances_usd}
                                                currency={usd}
                                                className="text-amber-800 dark:text-amber-300"
                                            />
                                        </Td>
                                        <Td
                                            align="end"
                                            money
                                            className="text-amber-800 dark:text-amber-300"
                                        >
                                            <MoneyCell
                                                value={row.advances_iqd}
                                                currency={iqd}
                                                className="text-amber-800 dark:text-amber-300"
                                            />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={row.net_pay_usd}
                                                label={usd}
                                                size="sm"
                                                showLabel={false}
                                                className="font-semibold text-indigo-950 dark:text-indigo-100"
                                            />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <Td className="font-semibold" colSpan={2}>
                                        {t('totals')}
                                    </Td>
                                    <Td align="end" money>
                                        <MoneyCell
                                            value={sum.base_pay_usd}
                                            currency={usd}
                                        />
                                    </Td>
                                    <Td align="end" money>
                                        <MoneyCell
                                            value={sum.monthly_salary_iqd}
                                            currency={iqd}
                                        />
                                    </Td>
                                    <Td align="end" money>
                                        <MoneyCell
                                            value={sum.overtime_pay_usd}
                                            currency={usd}
                                        />
                                    </Td>
                                    <Td
                                        align="end"
                                        money
                                        className="font-semibold text-rose-700 dark:text-rose-300"
                                    >
                                        <MoneyCell
                                            value={sum.penalties_usd}
                                            currency={usd}
                                            className="text-rose-700 dark:text-rose-300"
                                        />
                                    </Td>
                                    <Td
                                        align="end"
                                        money
                                        className="font-semibold text-rose-700 dark:text-rose-300"
                                    >
                                        <MoneyCell
                                            value={sum.penalties_iqd}
                                            currency={iqd}
                                            className="text-rose-700 dark:text-rose-300"
                                        />
                                    </Td>
                                    <Td
                                        align="end"
                                        money
                                        className="font-semibold text-amber-800 dark:text-amber-300"
                                    >
                                        <MoneyCell
                                            value={sum.insurance_holdback_usd}
                                            currency={usd}
                                            className="text-amber-800 dark:text-amber-300"
                                        />
                                    </Td>
                                    <Td
                                        align="end"
                                        money
                                        className="font-semibold text-amber-800 dark:text-amber-300"
                                    >
                                        <MoneyCell
                                            value={sum.advances_usd}
                                            currency={usd}
                                            className="text-amber-800 dark:text-amber-300"
                                        />
                                    </Td>
                                    <Td
                                        align="end"
                                        money
                                        className="font-semibold text-amber-800 dark:text-amber-300"
                                    >
                                        <MoneyCell
                                            value={sum.advances_iqd}
                                            currency={iqd}
                                            className="text-amber-800 dark:text-amber-300"
                                        />
                                    </Td>
                                    <Td align="end" money>
                                        <MoneyAmount
                                            value={sum.net_pay_usd}
                                            label={usd}
                                            size="sm"
                                            showLabel={false}
                                            accent
                                            className="font-semibold"
                                        />
                                    </Td>
                                </tr>
                            </tfoot>
                        </DataTable>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
