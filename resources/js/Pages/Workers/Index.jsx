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
import { Head, Link, router } from '@inertiajs/react';

function CountStat({ label, value, hint, active = false }) {
    return (
        <div
            className={
                'bv-card px-4 py-3.5 ' +
                (active
                    ? 'ring-2 ring-amber-500/40 dark:ring-amber-400/40'
                    : '')
            }
        >
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                {value ?? 0}
            </div>
            {hint ? (
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            ) : null}
        </div>
    );
}

function KindChip({ kind, t }) {
    const key = `labor_kind_${kind || 'unclassified'}`;
    const label = t(key) !== key ? t(key) : kind;
    const tones = {
        staff: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        worker: 'bg-indigo-500/15 text-indigo-900 dark:text-indigo-300',
        unclassified: 'bg-slate-500/15 text-slate-800 dark:text-slate-200',
    };

    return (
        <span
            className={
                'inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold ' +
                (tones[kind] || tones.unclassified)
            }
        >
            <NavIcon name="workers" className="text-xs" />
            {label}
        </span>
    );
}

function RoleChip({ role, t }) {
    const key = `worker_role_${role}`;
    const label = t(key) !== key ? t(key) : role?.replace(/_/g, ' ') || '—';
    const tones = {
        engineer: 'bg-sky-500/15 text-sky-900 dark:text-sky-300',
        supervisor: 'bg-teal-500/15 text-teal-900 dark:text-teal-300',
        subcontractor: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        laborer: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold capitalize ' +
                (tones[role] || tones.laborer)
            }
        >
            {label}
        </span>
    );
}

function formatUnitRate(worker, t) {
    const rate = Number(worker.unit_rate);
    if (!worker.unit_rate || Number.isNaN(rate) || rate <= 0) {
        return '—';
    }
    const unit = worker.rate_unit || '';
    const currency = worker.rate_currency || t('USD');
    return `${rate} ${currency}${unit ? ` / ${unit}` : ''}`;
}

export default function Index({ workers, filters, laborKinds, kindCounts }) {
    const t = useTranslations();
    const canCreate = useCan('workers.create');
    const list = workers || [];
    const activeKind = filters?.labor_kind || '';
    const isStaffView = activeKind === 'staff';
    const isWorkerView = activeKind === 'worker';
    const counts = kindCounts || {
        all: list.length,
        unclassified: 0,
        staff: 0,
        worker: 0,
    };

    const apply = (labor_kind) => {
        router.get(
            route('workers.index'),
            labor_kind ? { labor_kind } : {},
            { preserveState: true, replace: true },
        );
    };

    const kindLabel = (kind) => {
        const key = `labor_kind_${kind || 'unclassified'}`;
        const translated = t(key);
        return translated !== key ? translated : kind;
    };

    const pageTitle = isStaffView
        ? t('staff_directory')
        : isWorkerView
          ? t('labor_kind_worker')
          : t('people');
    const pageHint = isStaffView
        ? t('staff_page_hint')
        : isWorkerView
          ? t('workers_page_hint')
          : t('people_page_hint');

    const filterChips = [
        { key: '', label: t('labor_kind_all'), count: counts.all },
        ...(laborKinds || []).map((k) => ({
            key: k,
            label: kindLabel(k),
            count: counts[k] ?? 0,
        })),
    ];

    const showUnitRate = isStaffView || !activeKind || activeKind === 'unclassified';
    const showSalary = isWorkerView || !activeKind;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={pageTitle}
                    subtitle={pageHint}
                    icon={<NavIcon name="workers" className="text-lg" />}
                    actions={
                        canCreate ? (
                            <Link href={route('workers.create')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-amber-600 hover:!bg-amber-500 dark:!bg-amber-400 dark:!text-amber-950 dark:hover:!bg-amber-300"
                                >
                                    <NavIcon name="workers" className="text-sm" />
                                    {isStaffView ? t('staff_add') : t('create_person')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={pageTitle} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('staff_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('staff_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-1.5">
                        {filterChips.map((chip) => {
                            const active = activeKind === chip.key;
                            return (
                                <button
                                    key={chip.key || 'all'}
                                    type="button"
                                    onClick={() => apply(chip.key)}
                                    className={
                                        'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ' +
                                        (active
                                            ? 'bg-amber-600 text-white dark:bg-amber-400 dark:text-amber-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-amber-50 hover:text-amber-900 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-amber-950/40 dark:hover:text-amber-100')
                                    }
                                >
                                    {chip.label}
                                    <span
                                        className={
                                            'rounded-md px-1.5 py-0.5 font-sans text-[10px] tabular-nums ' +
                                            (active
                                                ? 'bg-white/20 text-white dark:bg-amber-950/20 dark:text-amber-950'
                                                : 'bg-white text-slate-500 dark:bg-slate-900 dark:text-slate-400')
                                        }
                                    >
                                        {chip.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('staff_overview')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('staff_overview_hint', {
                                staff: counts.staff,
                                unclassified: counts.unclassified,
                            })}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <CountStat
                            label={t('labor_kind_all')}
                            value={counts.all}
                            hint={t('staff_stat_all_hint')}
                            active={!activeKind}
                        />
                        <CountStat
                            label={t('labor_kind_staff')}
                            value={counts.staff}
                            hint={t('staff_stat_staff_hint')}
                            active={isStaffView}
                        />
                        <CountStat
                            label={t('labor_kind_worker')}
                            value={counts.worker}
                            hint={t('staff_stat_worker_hint')}
                            active={isWorkerView}
                        />
                        <CountStat
                            label={t('labor_kind_unclassified')}
                            value={counts.unclassified}
                            hint={t('staff_stat_unclassified_hint')}
                            active={activeKind === 'unclassified'}
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="workers"
                        title={
                            isStaffView
                                ? t('staff_empty_title')
                                : t('people_empty_title')
                        }
                        description={
                            isStaffView
                                ? t('staff_empty_hint')
                                : t('people_empty_hint')
                        }
                        action={
                            canCreate ? (
                                <Link href={route('workers.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-amber-600 hover:!bg-amber-500"
                                    >
                                        <NavIcon name="workers" className="text-sm" />
                                        {isStaffView
                                            ? t('staff_add')
                                            : t('create_person')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <NavIcon name="workers" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {isStaffView
                                        ? t('staff_table_title')
                                        : t('people_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {isStaffView
                                        ? t('staff_table_hint')
                                        : t('people_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="52rem" caption={pageTitle}>
                            <thead>
                                <tr>
                                    <Th>{isStaffView ? t('staff_name') : t('name')}</Th>
                                    {!isStaffView && !isWorkerView ? (
                                        <Th>{t('labor_kind')}</Th>
                                    ) : null}
                                    <Th>{t('role')}</Th>
                                    <Th>{t('project')}</Th>
                                    {showUnitRate ? (
                                        <Th align="end">{t('unit_rate')}</Th>
                                    ) : null}
                                    {showSalary ? (
                                        <>
                                            <Th
                                                align="end"
                                                className="text-amber-800 dark:text-amber-300"
                                            >
                                                {t('monthly_salary_usd')}
                                            </Th>
                                            <Th
                                                align="end"
                                                className="text-amber-800 dark:text-amber-300"
                                            >
                                                {t('monthly_salary_iqd')}
                                            </Th>
                                        </>
                                    ) : null}
                                    <Th>{t('phone')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((worker) => (
                                    <tr key={worker.id}>
                                        <Td>
                                            <Link
                                                href={route('workers.show', worker.id)}
                                                className="inline-flex items-start gap-2.5"
                                            >
                                                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                                    <NavIcon
                                                        name="workers"
                                                        className="text-sm"
                                                    />
                                                </span>
                                                <span className="font-medium text-amber-950 underline-offset-2 hover:underline dark:text-amber-100">
                                                    {worker.name}
                                                </span>
                                            </Link>
                                        </Td>
                                        {!isStaffView && !isWorkerView ? (
                                            <Td>
                                                <KindChip
                                                    kind={worker.labor_kind || 'unclassified'}
                                                    t={t}
                                                />
                                            </Td>
                                        ) : null}
                                        <Td>
                                            <RoleChip role={worker.role} t={t} />
                                        </Td>
                                        <Td muted>{worker.project?.name || '—'}</Td>
                                        {showUnitRate ? (
                                            <Td
                                                align="end"
                                                className="font-sans text-sm tabular-nums"
                                            >
                                                {worker.labor_kind === 'staff' ||
                                                isStaffView ? (
                                                    formatUnitRate(worker, t)
                                                ) : (
                                                    <span className="text-slate-300">—</span>
                                                )}
                                            </Td>
                                        ) : null}
                                        {showSalary ? (
                                            <>
                                                <Td align="end" money>
                                                    {Number(worker.monthly_salary_usd) > 0 ? (
                                                        <MoneyAmount
                                                            value={worker.monthly_salary_usd}
                                                            label="USD"
                                                            size="sm"
                                                            showLabel={false}
                                                        />
                                                    ) : (
                                                        <span className="text-slate-300">—</span>
                                                    )}
                                                </Td>
                                                <Td align="end" money>
                                                    {Number(worker.monthly_salary_iqd) > 0 ? (
                                                        <MoneyAmount
                                                            value={worker.monthly_salary_iqd}
                                                            label="IQD"
                                                            size="sm"
                                                            showLabel={false}
                                                        />
                                                    ) : (
                                                        <span className="text-slate-300">—</span>
                                                    )}
                                                </Td>
                                            </>
                                        ) : null}
                                        <Td muted className="font-sans tabular-nums">
                                            {worker.phone || '—'}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
