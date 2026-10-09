import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link } from '@inertiajs/react';

function CountStat({ label, value, hint }) {
    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                {value}
            </div>
            {hint ? (
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            ) : null}
        </div>
    );
}

function MoneyStat({ label, value, currency }) {
    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className="mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white sm:text-3xl"
            >
                {value == null ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

function StatusChip({ status, t }) {
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status;
    const tones = {
        active: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        planning: 'bg-slate-500/15 text-slate-800 dark:text-slate-200',
        on_hold: 'bg-amber-500/15 text-amber-900 dark:text-amber-200',
        completed: 'bg-sky-500/15 text-sky-900 dark:text-sky-300',
        archived: 'bg-slate-500/10 text-slate-600 dark:text-slate-400',
    };

    return (
        <span
            className={
                'inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold ' +
                (tones[status] || tones.planning)
            }
        >
            <NavIcon name="projects" className="text-xs" />
            {label}
        </span>
    );
}

export default function Index({ projects, canViewFinancials }) {
    const t = useTranslations();
    const canCreate = useCan('projects.create');
    const canFinanceAbility = useCan('projects.viewFinancials');
    const list = projects || [];
    const usd = t('USD');
    const iqd = t('IQD');
    const showFinance = canViewFinancials ?? canFinanceAbility;

    const activeCount = list.filter((p) => p.status === 'active').length;
    const planningCount = list.filter((p) => p.status === 'planning').length;
    const towersTotal = list.reduce((sum, p) => sum + (Number(p.towers_count) || 0), 0);
    const workersTotal = list.reduce((sum, p) => sum + (Number(p.workers_count) || 0), 0);

    const budgetUsd = list.reduce((sum, p) => sum + (Number(p.total_budget_usd) || 0), 0);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('projects')}
                    subtitle={t('projects_page_hint')}
                    icon={<NavIcon name="projects" className="text-lg" />}
                    actions={
                        canCreate ? (
                            <Link href={route('projects.create')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-slate-800 hover:!bg-slate-700 dark:!bg-slate-200 dark:!text-slate-900 dark:hover:!bg-white"
                                >
                                    <NavIcon name="projects" className="text-sm" />
                                    {t('create_project')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('projects')} />
            <PageShell className="!space-y-6">
                {list.length > 0 && (
                    <section>
                        <div className="mb-3">
                            <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                {t('projects_overview')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('projects_overview_hint', { count: list.length })}
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <CountStat
                                label={t('status_active')}
                                value={activeCount}
                                hint={t('projects_stat_active_hint')}
                            />
                            <CountStat
                                label={t('status_planning')}
                                value={planningCount}
                                hint={t('projects_stat_planning_hint')}
                            />
                            <CountStat
                                label={t('towers_count')}
                                value={towersTotal}
                                hint={t('projects_stat_towers_hint', {
                                    people: workersTotal,
                                })}
                            />
                            <CountStat
                                label={t('workers_count')}
                                value={workersTotal}
                                hint={t('projects_stat_people_hint')}
                            />
                        </div>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <MoneyStat
                                label={t('projects_budget_usd')}
                                value={budgetUsd > 0 ? budgetUsd : null}
                                currency={usd}
                            />
                        </div>
                    </section>
                )}

                {list.length === 0 ? (
                    <EmptyState
                        icon="projects"
                        title={t('projects_empty_title')}
                        description={t('projects_empty_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('projects.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-slate-800 hover:!bg-slate-700 dark:!bg-slate-200 dark:!text-slate-900 dark:hover:!bg-white"
                                    >
                                        <NavIcon name="projects" className="text-sm" />
                                        {t('create_project')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800/10 text-slate-800 dark:bg-slate-200/10 dark:text-slate-200">
                                <NavIcon name="projects" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('projects_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('projects_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="56rem" caption={t('projects')}>
                            <thead>
                                <tr>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('status')}</Th>
                                    <Th>{t('location')}</Th>
                                    {showFinance && (
                                        <>
                                            <Th align="end">{usd}</Th>
                                            <Th align="end">{t('money_received')}</Th>
                                        </>
                                    )}
                                    <Th align="end">{t('towers_count')}</Th>
                                    <Th align="end">{t('workers_count')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((project) => {
                                    const fin = project.financial_summary;
                                    const budgetUsdRow = Number(project.total_budget_usd) || 0;
                                    const receivedIqd =
                                        Number(fin?.money_received_iqd) || 0;

                                    return (
                                        <tr key={project.id}>
                                            <Td>
                                                <Link
                                                    href={route('projects.show', project.id)}
                                                    className="inline-flex items-start gap-2.5"
                                                >
                                                    <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-800/10 text-slate-800 dark:bg-slate-200/10 dark:text-slate-200">
                                                        <NavIcon
                                                            name="projects"
                                                            className="text-sm"
                                                        />
                                                    </span>
                                                    <span>
                                                        <span className="block font-medium text-slate-900 underline-offset-2 hover:underline dark:text-white">
                                                            {project.name}
                                                        </span>
                                                        {project.client && (
                                                            <span className="mt-0.5 block text-xs text-slate-400">
                                                                {project.client}
                                                            </span>
                                                        )}
                                                    </span>
                                                </Link>
                                            </Td>
                                            <Td>
                                                <StatusChip status={project.status} t={t} />
                                            </Td>
                                            <Td muted>{project.location || '—'}</Td>
                                            {showFinance && (
                                                <>
                                                    <Td align="end" money>
                                                        {budgetUsdRow > 0 ? (
                                                            <MoneyAmount
                                                                value={budgetUsdRow}
                                                                label={usd}
                                                                size="sm"
                                                                showLabel={false}
                                                            />
                                                        ) : (
                                                            <span className="text-slate-300">—</span>
                                                        )}
                                                    </Td>
                                                    <Td align="end" money>
                                                        {receivedIqd > 0 ? (
                                                            <MoneyAmount
                                                                value={receivedIqd}
                                                                label={iqd}
                                                                size="sm"
                                                                showLabel={false}
                                                            />
                                                        ) : (
                                                            <span className="text-slate-300">—</span>
                                                        )}
                                                    </Td>
                                                </>
                                            )}
                                            <Td align="end" className="font-sans tabular-nums">
                                                {project.towers_count ?? 0}
                                            </Td>
                                            <Td align="end" className="font-sans tabular-nums">
                                                {project.workers_count ?? 0}
                                            </Td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
