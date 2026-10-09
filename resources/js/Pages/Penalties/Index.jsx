import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        indigo: 'text-indigo-900 dark:text-indigo-200',
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

function CountStat({ label, value, hint, active = false }) {
    return (
        <div
            className={
                'bv-card px-4 py-3.5 ' +
                (active ? 'ring-2 ring-indigo-500/40 dark:ring-indigo-400/40' : '')
            }
        >
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

function StatusChip({ status, t }) {
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status?.replace(/_/g, ' ') || '—';
    const tones = {
        pending: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        applied: 'bg-indigo-500/15 text-indigo-900 dark:text-indigo-200',
        waived: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold capitalize ' +
                (tones[status] || tones.pending)
            }
        >
            {label}
        </span>
    );
}

function TypeChip({ type, t }) {
    const key = `penalty_type_${type}`;
    const label = t(key) !== key ? t(key) : type?.replace(/_/g, ' ') || '—';

    return (
        <span className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-500/10 px-2 py-1 text-xs font-semibold text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
            <NavIcon name="penalties" className="text-xs" />
            {label}
        </span>
    );
}

function typeLabel(type, t) {
    const key = `penalty_type_${type}`;
    const translated = t(key);
    return translated !== key ? translated : type?.replace(/_/g, ' ');
}

function formatDate(value) {
    if (!value) return '—';
    const s = String(value);
    return s.length >= 10 ? s.slice(0, 10) : s;
}

export default function Index({
    penalties,
    types,
    statuses,
    filters,
    statusCounts,
    typeCounts,
    overview,
}) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const canCreate = useCan('penalties.create');
    const canAddStaff = useCan('vault.ledgerManage');
    const list = penalties || [];
    const activeStatus = filters?.status || '';
    const activeType = filters?.type || '';
    const counts = statusCounts || {
        all: list.length,
        pending: 0,
        applied: 0,
        waived: 0,
    };
    const totals = overview || {
        count: list.length,
        pending: 0,
        applied: 0,
        waived: 0,
        amount_usd: 0,
        amount_iqd: 0,
        pending_usd: 0,
        pending_iqd: 0,
    };

    const apply = (next = {}) => {
        const params = {};
        const status = next.status !== undefined ? next.status : activeStatus;
        const type = next.type !== undefined ? next.type : activeType;
        if (status) params.status = status;
        if (type) params.type = type;
        router.get(route('penalties.index'), params, {
            preserveState: true,
            replace: true,
        });
    };

    const statusChips = [
        { key: '', label: t('penalties_filter_all'), count: counts.all },
        ...(statuses || []).map((s) => ({
            key: s,
            label: t(`status_${s}`) !== `status_${s}` ? t(`status_${s}`) : s,
            count: counts[s] ?? 0,
        })),
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('penalties')}
                    subtitle={t('penalties_page_hint')}
                    icon={<NavIcon name="penalties" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canAddStaff ? (
                                <Link
                                    href={route('staff.create')}
                                >
                                    <SecondaryButton type="button">
                                        <NavIcon name="payroll" className="text-sm" />
                                        {t('vault_form_add_staff')}
                                    </SecondaryButton>
                                </Link>
                            ) : null}
                            <Link href={route('vault.lines.salary.create')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="payroll" className="text-sm" />
                                    {t('vault_form_salary')}
                                </SecondaryButton>
                            </Link>
                            {canCreate ? (
                                <Link href={route('penalties.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-indigo-600 hover:!bg-indigo-500 dark:!bg-indigo-400 dark:!text-indigo-950 dark:hover:!bg-indigo-300"
                                    >
                                        <NavIcon name="penalties" className="text-sm" />
                                        {t('record_penalty')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </div>
                    }
                />
            }
        >
            <Head title={t('penalties')} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('penalties_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('penalties_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-1.5">
                        {statusChips.map((chip) => {
                            const active = activeStatus === chip.key;
                            return (
                                <button
                                    key={chip.key || 'all'}
                                    type="button"
                                    onClick={() => apply({ status: chip.key })}
                                    className={
                                        'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ' +
                                        (active
                                            ? 'bg-indigo-600 text-white dark:bg-indigo-400 dark:text-indigo-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-indigo-50 hover:text-indigo-900 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-100')
                                    }
                                >
                                    {chip.label}
                                    <span
                                        className={
                                            'rounded-md px-1.5 py-0.5 font-sans text-[10px] tabular-nums ' +
                                            (active
                                                ? 'bg-white/20 text-white dark:bg-indigo-950/20 dark:text-indigo-950'
                                                : 'bg-white text-slate-500 dark:bg-slate-900 dark:text-slate-400')
                                        }
                                    >
                                        {chip.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    <div className="mt-3 flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            onClick={() => apply({ type: '' })}
                            className={
                                'inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ' +
                                (!activeType
                                    ? 'bg-indigo-600/90 text-white dark:bg-indigo-400 dark:text-indigo-950'
                                    : 'bg-slate-100 text-slate-700 hover:bg-indigo-50 dark:bg-slate-800 dark:text-slate-200')
                            }
                        >
                            {t('penalties_type_all')}
                        </button>
                        {(types || []).map((type) => {
                            const active = activeType === type;
                            const count = typeCounts?.[type] ?? 0;
                            return (
                                <button
                                    key={type}
                                    type="button"
                                    onClick={() => apply({ type })}
                                    className={
                                        'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold capitalize transition ' +
                                        (active
                                            ? 'bg-indigo-600/90 text-white dark:bg-indigo-400 dark:text-indigo-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-indigo-50 dark:bg-slate-800 dark:text-slate-200')
                                    }
                                >
                                    {typeLabel(type, t)}
                                    <span
                                        className={
                                            'rounded-md px-1.5 py-0.5 font-sans text-[10px] tabular-nums ' +
                                            (active
                                                ? 'bg-white/20'
                                                : 'bg-white text-slate-500 dark:bg-slate-900 dark:text-slate-400')
                                        }
                                    >
                                        {count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                            <NavIcon name="penalties" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('penalties_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('penalties_overview_hint', {
                                    count: totals.count,
                                })}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <CountStat
                            label={t('penalties_filter_all')}
                            value={totals.count}
                            hint={t('penalties_stat_all_hint')}
                            active={!activeStatus}
                        />
                        <CountStat
                            label={t('status_pending')}
                            value={totals.pending}
                            hint={t('penalties_stat_pending_hint')}
                            active={activeStatus === 'pending'}
                        />
                        <CountStat
                            label={t('status_applied')}
                            value={totals.applied}
                            hint={t('penalties_stat_applied_hint')}
                            active={activeStatus === 'applied'}
                        />
                        <CountStat
                            label={t('status_waived')}
                            value={totals.waived}
                            hint={t('penalties_stat_waived_hint')}
                            active={activeStatus === 'waived'}
                        />
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <MoneyStat
                            label={t('penalties_total_usd')}
                            value={totals.amount_usd}
                            currency={usd}
                            tone="indigo"
                        />
                        <MoneyStat
                            label={t('penalties_total_iqd')}
                            value={totals.amount_iqd}
                            currency={iqd}
                            tone="indigo"
                        />
                        <MoneyStat
                            label={t('penalties_pending_usd')}
                            value={totals.pending_usd}
                            currency={usd}
                        />
                        <MoneyStat
                            label={t('penalties_pending_iqd')}
                            value={totals.pending_iqd}
                            currency={iqd}
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="penalties"
                        title={t('penalties_empty')}
                        description={t('penalties_empty_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('penalties.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-indigo-600 hover:!bg-indigo-500"
                                    >
                                        <NavIcon name="penalties" className="text-sm" />
                                        {t('record_penalty')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                                <NavIcon name="penalties" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('penalties_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('penalties_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable
                            minWidth="56rem"
                            caption={t('penalties')}
                            stickyFirstColumn
                        >
                            <thead>
                                <tr>
                                    <Th>{t('staff')}</Th>
                                    <Th>{t('penalty_type')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('date')}</Th>
                                    <Th
                                        align="end"
                                        className="text-indigo-900 dark:text-indigo-200"
                                    >
                                        {usd}
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-indigo-900 dark:text-indigo-200"
                                    >
                                        {iqd}
                                    </Th>
                                    <Th>{t('status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((p) => (
                                    <tr
                                        key={p.id}
                                        className={
                                            p.status === 'pending'
                                                ? 'bg-amber-50/40 dark:bg-amber-950/10'
                                                : p.status === 'waived'
                                                  ? 'opacity-70'
                                                  : ''
                                        }
                                    >
                                        <Td>
                                            <div className="flex items-start gap-2.5">
                                                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                                                    <NavIcon
                                                        name="payroll"
                                                        className="text-sm"
                                                    />
                                                </span>
                                                <span>
                                                    <Link
                                                        href={route(
                                                            'penalties.show',
                                                            p.id,
                                                        )}
                                                        className="font-medium text-indigo-950 underline-offset-2 hover:underline dark:text-indigo-100"
                                                    >
                                                        {p.staff?.name || '—'}
                                                    </Link>
                                                    {p.reason ? (
                                                        <span className="mt-0.5 block max-w-xs truncate text-xs text-slate-500 dark:text-slate-400">
                                                            {p.reason}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </div>
                                        </Td>
                                        <Td>
                                            <TypeChip type={p.type} t={t} />
                                        </Td>
                                        <Td muted>{p.project?.name || '—'}</Td>
                                        <Td
                                            muted
                                            className="font-sans tabular-nums"
                                        >
                                            {formatDate(p.occurred_on)}
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={
                                                    p.amount_usd_display ??
                                                    p.amount_usd
                                                }
                                                label={usd}
                                                size="sm"
                                                showLabel={false}
                                                className="text-indigo-900 dark:text-indigo-200"
                                            />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={
                                                    p.amount_iqd_display ??
                                                    p.amount_iqd
                                                }
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                                className="text-indigo-900 dark:text-indigo-200"
                                            />
                                        </Td>
                                        <Td>
                                            <StatusChip
                                                status={p.status}
                                                t={t}
                                            />
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
