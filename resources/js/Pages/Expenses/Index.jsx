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

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        rose: 'text-rose-800 dark:text-rose-200',
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
                (active ? 'ring-2 ring-rose-500/40 dark:ring-rose-400/40' : '')
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
        approved: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
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
    const key = `expense_category_${category}`;
    const label = t(key) !== key ? t(key) : category?.replace(/_/g, ' ') || '—';

    return (
        <span className="inline-flex items-center gap-1.5 rounded-lg bg-rose-500/10 px-2 py-1 text-xs font-semibold text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
            <NavIcon name="expenses" className="text-xs" />
            {label}
        </span>
    );
}

function categoryLabel(category, t) {
    const key = `expense_category_${category}`;
    const translated = t(key);
    return translated !== key ? translated : category;
}

export default function Index({
    expenses,
    categories,
    statuses,
    filters,
    statusCounts,
    spendTotals,
}) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const canCreate = useCan('expenses.create');
    const list = expenses || [];
    const activeCategory = filters?.category || '';
    const activeStatus = filters?.status || '';
    const counts = statusCounts || {
        all: list.length,
        pending: 0,
        held: 0,
        approved: 0,
        rejected: 0,
    };
    const totals = spendTotals || {
        amount_usd: 0,
        amount_iqd: 0,
        count: list.length,
    };

    const apply = (next = {}) => {
        const params = {};
        const category =
            next.category !== undefined ? next.category : activeCategory;
        const status = next.status !== undefined ? next.status : activeStatus;
        if (category) params.category = category;
        if (status) params.status = status;
        router.get(route('expenses.index'), params, {
            preserveState: true,
            replace: true,
        });
    };

    const statusChips = [
        { key: '', label: t('expenses_filter_all'), count: counts.all },
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
                    title={t('expenses')}
                    subtitle={t('expenses_page_hint')}
                    icon={<NavIcon name="expenses" className="text-lg" />}
                    actions={
                        canCreate ? (
                            <Link href={route('expenses.create')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-rose-600 hover:!bg-rose-500 dark:!bg-rose-400 dark:!text-rose-950 dark:hover:!bg-rose-300"
                                >
                                    <NavIcon name="expenses" className="text-sm" />
                                    {t('new_expense')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('expenses')} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('expenses_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('expenses_filters_hint')}
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
                                            ? 'bg-rose-600 text-white dark:bg-rose-400 dark:text-rose-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-rose-50 hover:text-rose-900 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-rose-950/40 dark:hover:text-rose-100')
                                    }
                                >
                                    {chip.label}
                                    <span
                                        className={
                                            'rounded-md px-1.5 py-0.5 font-sans text-[10px] tabular-nums ' +
                                            (active
                                                ? 'bg-white/20 text-white dark:bg-rose-950/20 dark:text-rose-950'
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
                                    ? 'bg-rose-600/90 text-white dark:bg-rose-400 dark:text-rose-950'
                                    : 'bg-slate-100 text-slate-700 hover:bg-rose-50 dark:bg-slate-800 dark:text-slate-200')
                            }
                        >
                            {t('expenses_category_all')}
                        </button>
                        {(categories || []).map((cat) => {
                            const active = activeCategory === cat;
                            return (
                                <button
                                    key={cat}
                                    type="button"
                                    onClick={() => apply({ category: cat })}
                                    className={
                                        'inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-semibold capitalize transition ' +
                                        (active
                                            ? 'bg-rose-600/90 text-white dark:bg-rose-400 dark:text-rose-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-rose-50 dark:bg-slate-800 dark:text-slate-200')
                                    }
                                >
                                    {categoryLabel(cat, t)}
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="expenses" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('expenses_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('expenses_overview_hint', {
                                    count: totals.count,
                                })}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <CountStat
                            label={t('expenses_filter_all')}
                            value={counts.all}
                            hint={t('expenses_stat_all_hint')}
                            active={!activeStatus}
                        />
                        <CountStat
                            label={t('status_pending')}
                            value={counts.pending}
                            hint={t('expenses_stat_pending_hint')}
                            active={activeStatus === 'pending'}
                        />
                        <CountStat
                            label={t('status_approved')}
                            value={counts.approved}
                            hint={t('expenses_stat_approved_hint')}
                            active={activeStatus === 'approved'}
                        />
                        <CountStat
                            label={t('status_held')}
                            value={counts.held}
                            hint={t('expenses_stat_held_hint')}
                            active={activeStatus === 'held'}
                        />
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <MoneyStat
                            label={t('expenses_total_usd')}
                            value={totals.amount_usd}
                            currency={usd}
                            tone="rose"
                        />
                        <MoneyStat
                            label={t('expenses_total_iqd')}
                            value={totals.amount_iqd}
                            currency={iqd}
                            tone="rose"
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="expenses"
                        title={t('expenses_empty_title')}
                        description={t('expenses_empty_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('expenses.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-rose-600 hover:!bg-rose-500"
                                    >
                                        <NavIcon name="expenses" className="text-sm" />
                                        {t('new_expense')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                <NavIcon name="expenses" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('expenses_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('expenses_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable
                            minWidth="56rem"
                            caption={t('expenses')}
                            stickyFirstColumn
                        >
                            <thead>
                                <tr>
                                    <Th>{t('expense_date')}</Th>
                                    <Th>{t('category')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('supplier')}</Th>
                                    <Th
                                        align="end"
                                        className="text-rose-800 dark:text-rose-300"
                                    >
                                        {usd}
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-rose-800 dark:text-rose-300"
                                    >
                                        {iqd}
                                    </Th>
                                    <Th>{t('status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((e) => {
                                    const usdAmt = Number(e.amount_usd) || 0;
                                    const iqdAmt = Number(e.amount_iqd) || 0;
                                    const date =
                                        typeof e.expense_date === 'string'
                                            ? e.expense_date.slice(0, 10)
                                            : e.expense_date
                                              ? String(e.expense_date).slice(0, 10)
                                              : '—';

                                    return (
                                        <tr key={e.id}>
                                            <Td className="whitespace-nowrap font-sans tabular-nums">
                                                <Link
                                                    href={route(
                                                        'expenses.show',
                                                        e.id,
                                                    )}
                                                    className="inline-flex items-center gap-2"
                                                >
                                                    <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-500/15 text-rose-800 dark:text-rose-200">
                                                        <NavIcon
                                                            name="expenses"
                                                            className="text-sm"
                                                        />
                                                    </span>
                                                    <span className="font-medium text-rose-950 underline-offset-2 hover:underline dark:text-rose-100">
                                                        {date}
                                                    </span>
                                                </Link>
                                            </Td>
                                            <Td>
                                                <CategoryChip
                                                    category={e.category}
                                                    t={t}
                                                />
                                            </Td>
                                            <Td muted>
                                                {e.project?.name || '—'}
                                            </Td>
                                            <Td muted>
                                                {e.supplier ||
                                                    (e.description
                                                        ? String(
                                                              e.description,
                                                          ).slice(0, 40)
                                                        : '—')}
                                            </Td>
                                            <Td align="end" money>
                                                {usdAmt > 0 ? (
                                                    <MoneyAmount
                                                        value={e.amount_usd}
                                                        label={usd}
                                                        size="sm"
                                                        showLabel={false}
                                                        className="text-rose-800 dark:text-rose-200"
                                                    />
                                                ) : (
                                                    <span className="text-slate-300">
                                                        —
                                                    </span>
                                                )}
                                            </Td>
                                            <Td align="end" money>
                                                {iqdAmt > 0 ? (
                                                    <MoneyAmount
                                                        value={e.amount_iqd}
                                                        label={iqd}
                                                        size="sm"
                                                        showLabel={false}
                                                        className="text-rose-800 dark:text-rose-200"
                                                    />
                                                ) : (
                                                    <span className="text-slate-300">
                                                        —
                                                    </span>
                                                )}
                                            </Td>
                                            <Td>
                                                <StatusChip
                                                    status={e.approval_status}
                                                    t={t}
                                                />
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
