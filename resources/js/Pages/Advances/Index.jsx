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
import { Head, Link } from '@inertiajs/react';

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        open: 'text-amber-800 dark:text-amber-200',
        muted: 'text-slate-400',
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
                    tones[tone]
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

function StatusChip({ status, t }) {
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status?.replace(/_/g, ' ') || '—';
    const tones = {
        open: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        repaid: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        cancelled: 'bg-slate-500/15 text-slate-600 dark:text-slate-400',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold capitalize ' +
                (tones[status] || tones.open)
            }
        >
            {label}
        </span>
    );
}

function KindChip({ kind, t }) {
    const key = `labor_kind_${kind || 'unclassified'}`;
    const label = t(key) !== key ? t(key) : kind;
    const tones = {
        staff: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        worker: 'bg-indigo-500/15 text-indigo-900 dark:text-indigo-300',
        unclassified: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ' +
                (tones[kind] || tones.unclassified)
            }
        >
            {label}
        </span>
    );
}

export default function Index({ advances, totals, staffHolds }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const list = advances || [];
    const canCreate = useCan('advances.create');
    const canViewRetention = useCan('vault.retention') || useCan('vault.retentionManage');
    const sums = totals || {};
    const holds = staffHolds || { holding: 0, matured: 0 };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('staff_pay_title')}
                    subtitle={t('staff_pay_hint')}
                    icon={<NavIcon name="advances" className="text-lg" />}
                    actions={
                        <>
                            {canViewRetention ? (
                                <Link href={route('retention-holds.index')}>
                                    <SecondaryButton type="button">
                                        <NavIcon name="insurance" className="text-sm" />
                                        {t('staff_pay_holds_link')}
                                        {(holds.holding || 0) + (holds.matured || 0) > 0
                                            ? ` (${(holds.holding || 0) + (holds.matured || 0)})`
                                            : ''}
                                    </SecondaryButton>
                                </Link>
                            ) : null}
                            {canCreate ? (
                                <Link href={route('advances.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-amber-600 hover:!bg-amber-500 dark:!bg-amber-400 dark:!text-amber-950 dark:hover:!bg-amber-300"
                                    >
                                        <NavIcon name="advances" className="text-sm" />
                                        {t('staff_pay_record')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </>
                    }
                />
            }
        >
            <Head title={t('staff_pay_title')} />
            <PageShell className="!space-y-6">
                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('staff_pay_overview')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('staff_pay_overview_hint', {
                                open: sums.open ?? 0,
                                holding: holds.holding ?? 0,
                            })}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <MoneyStat
                            label={t('staff_pay_total_usd')}
                            value={sums.amount_usd}
                            currency={usd}
                        />
                        <MoneyStat
                            label={t('staff_pay_total_iqd')}
                            value={sums.amount_iqd}
                            currency={iqd}
                        />
                        <MoneyStat
                            label={t('staff_pay_remaining_usd')}
                            value={sums.remaining_usd}
                            currency={usd}
                            tone="open"
                        />
                        <MoneyStat
                            label={t('staff_pay_remaining_iqd')}
                            value={sums.remaining_iqd}
                            currency={iqd}
                            tone="open"
                        />
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        <CountStat
                            label={t('status_open')}
                            value={sums.open}
                            hint={t('staff_pay_open_hint')}
                        />
                        <CountStat
                            label={t('status_repaid')}
                            value={sums.repaid}
                            hint={t('staff_pay_repaid_hint')}
                        />
                        <CountStat
                            label={t('staff_pay_holds_stat')}
                            value={(holds.holding || 0) + (holds.matured || 0)}
                            hint={t('staff_pay_holds_stat_hint', {
                                holding: holds.holding ?? 0,
                                matured: holds.matured ?? 0,
                            })}
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="advances"
                        title={t('staff_pay_empty_title')}
                        description={t('staff_pay_empty_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('advances.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-amber-600 hover:!bg-amber-500"
                                    >
                                        <NavIcon name="advances" className="text-sm" />
                                        {t('staff_pay_record')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <NavIcon name="advances" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('staff_pay_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('staff_pay_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="56rem" caption={t('staff_pay_title')}>
                            <thead>
                                <tr>
                                    <Th>{t('person')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('date')}</Th>
                                    <Th align="end" className="text-amber-800 dark:text-amber-300">
                                        {usd}
                                    </Th>
                                    <Th align="end" className="text-amber-800 dark:text-amber-300">
                                        {iqd}
                                    </Th>
                                    <Th align="end">{t('staff_pay_col_remaining')}</Th>
                                    <Th>{t('repayment_method')}</Th>
                                    <Th>{t('status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((a) => {
                                    const remUsd = Number(a.remaining_usd) || 0;
                                    const remIqd = Number(a.remaining_iqd) || 0;
                                    const remCurrency = remUsd > 0 && remIqd <= 0 ? usd : iqd;
                                    const remValue = remUsd > 0 && remIqd <= 0 ? remUsd : remIqd;

                                    return (
                                        <tr key={a.id}>
                                            <Td>
                                                <Link
                                                    href={route('advances.show', a.id)}
                                                    className="inline-flex items-start gap-2.5"
                                                >
                                                    <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                                        <NavIcon
                                                            name="workers"
                                                            className="text-sm"
                                                        />
                                                    </span>
                                                    <span>
                                                        <span className="block font-medium text-amber-950 underline-offset-2 hover:underline dark:text-amber-100">
                                                            {a.worker?.name || '—'}
                                                        </span>
                                                        <span className="mt-0.5 inline-block">
                                                            <KindChip
                                                                kind={
                                                                    a.worker?.labor_kind ||
                                                                    'unclassified'
                                                                }
                                                                t={t}
                                                            />
                                                        </span>
                                                    </span>
                                                </Link>
                                            </Td>
                                            <Td muted>{a.project?.name || '—'}</Td>
                                            <Td className="font-sans tabular-nums">
                                                {a.advanced_on
                                                    ? String(a.advanced_on).slice(0, 10)
                                                    : '—'}
                                            </Td>
                                            <Td align="end" money>
                                                {Number(a.amount_usd) > 0 ? (
                                                    <MoneyAmount
                                                        value={a.amount_usd}
                                                        label={usd}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                ) : (
                                                    <span className="text-slate-300">—</span>
                                                )}
                                            </Td>
                                            <Td align="end" money>
                                                {Number(a.amount_iqd) > 0 ? (
                                                    <MoneyAmount
                                                        value={a.amount_iqd}
                                                        label={iqd}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                ) : (
                                                    <span className="text-slate-300">—</span>
                                                )}
                                            </Td>
                                            <Td align="end" money>
                                                {remValue > 0 ? (
                                                    <span className="text-amber-800 dark:text-amber-300">
                                                        <MoneyAmount
                                                            value={remValue}
                                                            label={remCurrency}
                                                            size="sm"
                                                            showLabel={false}
                                                        />
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-300">—</span>
                                                )}
                                            </Td>
                                            <Td muted>
                                                {t(`repay_${a.repayment_method}`) !==
                                                `repay_${a.repayment_method}`
                                                    ? t(`repay_${a.repayment_method}`)
                                                    : a.repayment_method}
                                            </Td>
                                            <Td>
                                                <StatusChip status={a.status} t={t} />
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
