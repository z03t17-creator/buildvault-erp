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
        teal: 'text-teal-900 dark:text-teal-200',
        rose: 'text-rose-800 dark:text-rose-200',
        amber: 'text-amber-900 dark:text-amber-200',
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
                (active ? 'ring-2 ring-teal-500/40 dark:ring-teal-400/40' : '')
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
        held: 'bg-orange-500/15 text-orange-950 dark:text-orange-200',
        approved: 'bg-teal-500/15 text-teal-900 dark:text-teal-200',
        reconciled: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        rejected: 'bg-rose-500/15 text-rose-900 dark:text-rose-300',
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

function CategoryChip({ category, t }) {
    const key = `payout_category_${category}`;
    const label = t(key) !== key ? t(key) : category?.replace(/_/g, ' ') || '—';

    return (
        <span className="inline-flex items-center gap-1.5 rounded-lg bg-teal-500/10 px-2 py-1 text-xs font-semibold capitalize text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
            <NavIcon name="payouts" className="text-xs" />
            {label}
        </span>
    );
}

function categoryLabel(category, t) {
    const key = `payout_category_${category}`;
    const translated = t(key);
    return translated !== key ? translated : category?.replace(/_/g, ' ');
}

export default function Index({
    payouts,
    statuses,
    categories,
    filters,
    statusCounts,
    categoryCounts,
    overview,
    availableCash,
    ability,
}) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const canCreate = useCan('payouts.create');
    const list = payouts || [];
    const activeStatus = filters?.status || '';
    const activeCategory = filters?.category || '';
    const counts = statusCounts || {
        all: list.length,
        pending: 0,
        held: 0,
        approved: 0,
        reconciled: 0,
        rejected: 0,
    };
    const totals = overview || {
        count: list.length,
        pending: 0,
        held: 0,
        approved: 0,
        reconciled: 0,
        rejected: 0,
        open_usd: 0,
        open_iqd: 0,
        pending_usd: 0,
        pending_iqd: 0,
    };
    const cash = availableCash || {
        available_usd: 0,
        available_iqd: 0,
        pending_usd: 0,
        pending_iqd: 0,
    };
    const payAbility = ability || {
        available_usd: 0,
        available_iqd: 0,
        blocks_usd: false,
        blocks_iqd: false,
    };

    const apply = (next = {}) => {
        const params = {};
        const status = next.status !== undefined ? next.status : activeStatus;
        const category =
            next.category !== undefined ? next.category : activeCategory;
        if (status) params.status = status;
        if (category) params.category = category;
        router.get(route('payouts.index'), params, {
            preserveState: true,
            replace: true,
        });
    };

    const statusChips = [
        { key: '', label: t('payouts_filter_all'), count: counts.all },
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
                    title={t('payouts')}
                    subtitle={t('payouts_page_hint')}
                    icon={<NavIcon name="payouts" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('settlements.index')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="settlements" className="text-sm" />
                                    {t('settlements')}
                                </SecondaryButton>
                            </Link>
                            <Link href={route('vault.index')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="vault" className="text-sm" />
                                    {t('vault_dashboard')}
                                </SecondaryButton>
                            </Link>
                            {canCreate ? (
                                <Link href={route('payouts.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-teal-600 hover:!bg-teal-500 dark:!bg-teal-400 dark:!text-teal-950 dark:hover:!bg-teal-300"
                                    >
                                        <NavIcon name="payouts" className="text-sm" />
                                        {t('new_payout')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </div>
                    }
                />
            }
        >
            <Head title={t('payouts')} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('payouts_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('payouts_filters_hint')}
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
                                            ? 'bg-teal-600 text-white dark:bg-teal-400 dark:text-teal-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-teal-50 hover:text-teal-900 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-teal-950/40 dark:hover:text-teal-100')
                                    }
                                >
                                    {chip.label}
                                    <span
                                        className={
                                            'rounded-md px-1.5 py-0.5 font-sans text-[10px] tabular-nums ' +
                                            (active
                                                ? 'bg-white/20 text-white dark:bg-teal-950/20 dark:text-teal-950'
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
                            onClick={() => apply({ category: '' })}
                            className={
                                'inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ' +
                                (!activeCategory
                                    ? 'bg-teal-600/90 text-white dark:bg-teal-400 dark:text-teal-950'
                                    : 'bg-slate-100 text-slate-700 hover:bg-teal-50 dark:bg-slate-800 dark:text-slate-200')
                            }
                        >
                            {t('payouts_category_all')}
                        </button>
                        {(categories || []).map((cat) => {
                            const active = activeCategory === cat;
                            const count = categoryCounts?.[cat] ?? 0;
                            return (
                                <button
                                    key={cat}
                                    type="button"
                                    onClick={() => apply({ category: cat })}
                                    className={
                                        'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold capitalize transition ' +
                                        (active
                                            ? 'bg-teal-600/90 text-white dark:bg-teal-400 dark:text-teal-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-teal-50 dark:bg-slate-800 dark:text-slate-200')
                                    }
                                >
                                    {categoryLabel(cat, t)}
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
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                            <NavIcon name="vault" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('payouts_ability_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('payouts_ability_hint')}
                            </p>
                        </div>
                    </div>
                    <div
                        className={
                            'bv-card grid gap-4 p-4 sm:grid-cols-2 sm:p-5 ' +
                            (payAbility.blocks_usd || payAbility.blocks_iqd
                                ? 'ring-2 ring-amber-500/35 dark:ring-amber-400/35'
                                : 'ring-2 ring-teal-500/30 dark:ring-teal-400/30')
                        }
                    >
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {t('available_money_for_payment')} · {iqd}
                            </p>
                            <div className="mt-1">
                                <MoneyAmount
                                    value={cash.available_iqd}
                                    label={iqd}
                                    size="xl"
                                    showLabel={false}
                                    className="text-teal-900 dark:text-teal-200"
                                />
                            </div>
                            <p className="mt-1 text-xs text-slate-500">
                                {t('pending_commitments')}:{' '}
                                <MoneyAmount
                                    value={cash.pending_iqd}
                                    label={iqd}
                                    size="sm"
                                    showLabel={false}
                                />
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {t('available_money_for_payment')} · {usd}
                            </p>
                            <div className="mt-1">
                                <MoneyAmount
                                    value={cash.available_usd}
                                    label={usd}
                                    size="xl"
                                    showLabel={false}
                                    className="text-teal-900 dark:text-teal-200"
                                />
                            </div>
                            <p className="mt-1 text-xs text-slate-500">
                                {t('pending_commitments')}:{' '}
                                <MoneyAmount
                                    value={cash.pending_usd}
                                    label={usd}
                                    size="sm"
                                    showLabel={false}
                                />
                            </p>
                        </div>
                    </div>
                    {(payAbility.blocks_usd || payAbility.blocks_iqd) && (
                        <p className="mt-2 text-sm font-medium text-amber-800 dark:text-amber-200">
                            {t('payouts_ability_warning')}
                        </p>
                    )}
                </section>

                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                            <NavIcon name="payouts" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('payouts_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('payouts_overview_hint', {
                                    count: totals.count,
                                })}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <CountStat
                            label={t('payouts_filter_all')}
                            value={totals.count}
                            hint={t('payouts_stat_all_hint')}
                            active={!activeStatus}
                        />
                        <CountStat
                            label={t('status_pending')}
                            value={totals.pending}
                            hint={t('payouts_stat_pending_hint')}
                            active={activeStatus === 'pending'}
                        />
                        <CountStat
                            label={t('status_held')}
                            value={totals.held}
                            hint={t('payouts_stat_held_hint')}
                            active={activeStatus === 'held'}
                        />
                        <CountStat
                            label={t('status_approved')}
                            value={totals.approved}
                            hint={t('payouts_stat_approved_hint')}
                            active={activeStatus === 'approved'}
                        />
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <MoneyStat
                            label={t('payouts_open_usd')}
                            value={totals.open_usd}
                            currency={usd}
                            tone="teal"
                        />
                        <MoneyStat
                            label={t('payouts_open_iqd')}
                            value={totals.open_iqd}
                            currency={iqd}
                            tone="teal"
                        />
                        <MoneyStat
                            label={t('payouts_pending_usd')}
                            value={totals.pending_usd}
                            currency={usd}
                            tone="amber"
                        />
                        <MoneyStat
                            label={t('payouts_pending_iqd')}
                            value={totals.pending_iqd}
                            currency={iqd}
                            tone="amber"
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="payouts"
                        title={t('payouts_empty')}
                        description={t('payouts_empty_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('payouts.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-teal-600 hover:!bg-teal-500"
                                    >
                                        <NavIcon name="payouts" className="text-sm" />
                                        {t('new_payout')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                                <NavIcon name="payouts" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('payouts_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('payouts_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable
                            minWidth="56rem"
                            caption={t('payouts')}
                            stickyFirstColumn
                        >
                            <thead>
                                <tr>
                                    <Th>{t('payouts_col_payout')}</Th>
                                    <Th>{t('category')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th
                                        align="end"
                                        className="text-teal-900 dark:text-teal-200"
                                    >
                                        {usd}
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-teal-900 dark:text-teal-200"
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
                                                : p.status === 'held'
                                                  ? 'bg-orange-50/40 dark:bg-orange-950/10'
                                                  : p.status === 'rejected'
                                                    ? 'opacity-70'
                                                    : ''
                                        }
                                    >
                                        <Td>
                                            <div className="flex items-start gap-2.5">
                                                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                                                    <NavIcon
                                                        name="payouts"
                                                        className="text-sm"
                                                    />
                                                </span>
                                                <span>
                                                    <Link
                                                        href={route(
                                                            'payouts.show',
                                                            p.id,
                                                        )}
                                                        className="font-medium text-teal-950 underline-offset-2 hover:underline dark:text-teal-100"
                                                    >
                                                        #{p.id}
                                                        {p.worker?.name
                                                            ? ` · ${p.worker.name}`
                                                            : ''}
                                                    </Link>
                                                    {p.notes ? (
                                                        <span className="mt-0.5 block max-w-xs truncate text-xs text-slate-500 dark:text-slate-400">
                                                            {p.notes}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </div>
                                        </Td>
                                        <Td>
                                            <CategoryChip
                                                category={p.category}
                                                t={t}
                                            />
                                        </Td>
                                        <Td muted>{p.project?.name || '—'}</Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={p.amount_usd}
                                                label={usd}
                                                size="sm"
                                                showLabel={false}
                                                className="text-teal-900 dark:text-teal-200"
                                            />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={p.amount_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                                className="text-teal-900 dark:text-teal-200"
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
