import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import FlashBanner from '@/Components/FlashBanner';
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

const selectClass =
    'min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

const inputClass =
    'min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm placeholder:text-slate-400 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder:text-slate-500';

function MoneyStat({ label, value, currency, accent = false }) {
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
                        accent={accent}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

function DirectionChip({ direction, t }) {
    if (direction === 'in') {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500/15 px-2 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                <NavIcon name="moneyIn" className="text-xs" />
                {t('money_in')}
            </span>
        );
    }
    if (direction === 'out') {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-lg bg-rose-500/15 px-2 py-1 text-xs font-semibold text-rose-800 dark:text-rose-300">
                <NavIcon name="moneyOut" className="text-xs" />
                {t('money_out')}
            </span>
        );
    }
    return <span className="text-xs text-slate-400">—</span>;
}

function LedgerPagination({ page, onPage }) {
    const t = useTranslations();
    if (!page?.links?.length || (page.last_page ?? 1) <= 1) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 px-4 py-3 dark:border-slate-800">
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400">
                {t('ledger_page_of', {
                    current: page.current_page,
                    last: page.last_page,
                    total: page.total,
                })}
            </p>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    disabled={!page.prev_page_url}
                    onClick={() => page.current_page > 1 && onPage(page.current_page - 1)}
                    className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition enabled:hover:border-teal-300 enabled:hover:bg-teal-50 disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:enabled:hover:bg-slate-800"
                    aria-label={t('previous')}
                >
                    <NavIcon name="angleLeft" className="text-sm" />
                </button>
                <button
                    type="button"
                    disabled={!page.next_page_url}
                    onClick={() =>
                        page.current_page < page.last_page && onPage(page.current_page + 1)
                    }
                    className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition enabled:hover:border-teal-300 enabled:hover:bg-teal-50 disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:enabled:hover:bg-slate-800"
                    aria-label={t('next')}
                >
                    <NavIcon name="angleRight" className="text-sm" />
                </button>
            </div>
        </div>
    );
}

export default function Transactions({
    balances,
    transactions,
    filters,
    types,
    projects,
    canManage,
}) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const manage = canManage || useCan('vault.ledgerManage');
    const rows = transactions?.data || [];
    const bal = balances || {};

    const apply = (next) => {
        router.get(
            route('vault.transactions'),
            { ...filters, page: undefined, ...next },
            { preserveState: true, replace: true },
        );
    };

    const typeLabel = (type) => {
        const key = `txn_type_${type}`;
        const translated = t(key);
        return translated !== key ? translated : type;
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('vault_ledger')}
                    subtitle={t('vault_ledger_page_hint')}
                    icon={<NavIcon name="vault" className="text-lg" />}
                    actions={
                        <>
                            <Link href={route('dashboards.vault')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="vault" className="text-sm" />
                                    {t('vault_dashboard')}
                                </SecondaryButton>
                            </Link>
                            {manage && (
                                <>
                                    <Link
                                        href={route('vault.transactions.create', {
                                            direction: 'in',
                                        })}
                                    >
                                        <PrimaryButton
                                            type="button"
                                            className="!bg-emerald-600 hover:!bg-emerald-500"
                                        >
                                            <NavIcon name="moneyIn" className="text-sm" />
                                            {t('money_in')}
                                        </PrimaryButton>
                                    </Link>
                                    <Link
                                        href={route('vault.transactions.create', {
                                            direction: 'out',
                                        })}
                                    >
                                        <PrimaryButton
                                            type="button"
                                            className="!bg-rose-600 hover:!bg-rose-500"
                                        >
                                            <NavIcon name="moneyOut" className="text-sm" />
                                            {t('money_out')}
                                        </PrimaryButton>
                                    </Link>
                                </>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={t('vault_ledger')} />

            <PageShell className="!space-y-6">
                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('available_cash')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('vault_available_hint')}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <MoneyStat
                            label={`${t('available_cash')} ${usd}`}
                            value={bal.available_usd}
                            currency={usd}
                            accent
                        />
                        <MoneyStat
                            label={`${t('available_cash')} ${iqd}`}
                            value={bal.available_iqd}
                            currency={iqd}
                            accent
                        />
                    </div>
                </section>

                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('current_balance')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('vault_qasa_hint')}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <MoneyStat
                            label={`${t('current_balance')} ${usd}`}
                            value={bal.current_usd}
                            currency={usd}
                        />
                        <MoneyStat
                            label={`${t('current_balance')} ${iqd}`}
                            value={bal.current_iqd}
                            currency={iqd}
                        />
                    </div>
                </section>

                {!bal.balance_matches_ledger && (
                    <FlashBanner tone="warning">
                        {t('ledger_balance_mismatch', {
                            vault: bal.current_iqd,
                            ledger: bal.ledger_cash_iqd,
                        })}
                    </FlashBanner>
                )}

                <DataPanel padded={false}>
                    <div className="flex flex-col gap-3 border-b border-slate-200/80 px-4 py-4 dark:border-slate-800 sm:flex-row sm:flex-wrap sm:items-center">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-600/10 text-teal-800 dark:bg-teal-500/15 dark:text-teal-300">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <select
                            className={selectClass}
                            value={filters?.type || ''}
                            onChange={(e) => apply({ type: e.target.value })}
                            aria-label={t('all_types')}
                        >
                            <option value="">{t('all_types')}</option>
                            {(types || []).map((type) => (
                                <option key={type} value={type}>
                                    {typeLabel(type)}
                                </option>
                            ))}
                        </select>
                        <select
                            className={selectClass}
                            value={filters?.project_id || ''}
                            onChange={(e) => apply({ project_id: e.target.value || '' })}
                            aria-label={t('all_projects')}
                        >
                            <option value="">{t('all_projects')}</option>
                            {(projects || []).map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <label className="relative min-w-[12rem] flex-1">
                            <span className="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400">
                                <NavIcon name="search" className="text-sm" />
                            </span>
                            <input
                                type="search"
                                className={`${inputClass} w-full ps-9`}
                                placeholder={t('search_ledger')}
                                defaultValue={filters?.q || ''}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        apply({ q: e.currentTarget.value });
                                    }
                                }}
                            />
                        </label>
                        <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <NavIcon name="calendar" className="text-sm text-slate-400" />
                            <input
                                type="date"
                                className={inputClass}
                                value={filters?.from || ''}
                                onChange={(e) => apply({ from: e.target.value })}
                                aria-label={t('from_date')}
                            />
                            <span className="text-slate-400">–</span>
                            <input
                                type="date"
                                className={inputClass}
                                value={filters?.to || ''}
                                onChange={(e) => apply({ to: e.target.value })}
                                aria-label={t('to_date')}
                            />
                        </label>
                    </div>

                    {rows.length === 0 ? (
                        <div className="p-5">
                            <EmptyState
                                icon="vault"
                                title={t('no_ledger_rows')}
                                description={t('ledger_empty_hint')}
                                action={
                                    manage ? (
                                        <Link
                                            href={route('vault.transactions.create', {
                                                direction: 'in',
                                            })}
                                        >
                                            <PrimaryButton type="button">
                                                <NavIcon name="moneyIn" className="text-sm" />
                                                {t('money_in')}
                                            </PrimaryButton>
                                        </Link>
                                    ) : null
                                }
                            />
                        </div>
                    ) : (
                        <>
                            <DataTable minWidth="68rem" caption={t('vault_ledger')}>
                                <thead>
                                    <tr>
                                        <Th>{t('date')}</Th>
                                        <Th>{t('description')}</Th>
                                        <Th>{t('direction')}</Th>
                                        <Th align="end" className="text-emerald-700 dark:text-emerald-400">
                                            {t('money_in')} {usd}
                                        </Th>
                                        <Th align="end" className="text-rose-700 dark:text-rose-400">
                                            {t('money_out')} {usd}
                                        </Th>
                                        <Th align="end" className="text-emerald-700 dark:text-emerald-400">
                                            {t('money_in')} {iqd}
                                        </Th>
                                        <Th align="end" className="text-rose-700 dark:text-rose-400">
                                            {t('money_out')} {iqd}
                                        </Th>
                                        <Th>{t('project')}</Th>
                                        {manage && <Th align="center">{t('actions')}</Th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => {
                                        const inUsd = row.direction === 'in' ? row.amount_usd : 0;
                                        const outUsd = row.direction === 'out' ? row.amount_usd : 0;
                                        const inIqd = row.direction === 'in' ? row.amount_iqd : 0;
                                        const outIqd = row.direction === 'out' ? row.amount_iqd : 0;

                                        return (
                                            <tr key={row.id}>
                                                <Td className="whitespace-nowrap font-sans text-sm tabular-nums">
                                                    <span className="inline-flex items-center gap-2">
                                                        <span
                                                            className={
                                                                'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ' +
                                                                (row.direction === 'in'
                                                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                                    : row.direction === 'out'
                                                                      ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
                                                                      : 'bg-slate-100 text-slate-500 dark:bg-slate-800')
                                                            }
                                                        >
                                                            <NavIcon
                                                                name={
                                                                    row.direction === 'out'
                                                                        ? 'moneyOut'
                                                                        : 'moneyIn'
                                                                }
                                                                className="text-sm"
                                                            />
                                                        </span>
                                                        {row.date || '—'}
                                                    </span>
                                                </Td>
                                                <Td>
                                                    <span className="block max-w-[16rem] truncate font-medium">
                                                        {row.description || typeLabel(row.type)}
                                                    </span>
                                                    {row.reference && (
                                                        <span className="mt-0.5 block truncate text-xs text-slate-400">
                                                            {row.reference}
                                                        </span>
                                                    )}
                                                </Td>
                                                <Td>
                                                    <DirectionChip
                                                        direction={row.direction}
                                                        t={t}
                                                    />
                                                </Td>
                                                <Td align="end" money>
                                                    {inUsd ? (
                                                        <span className="text-emerald-700 dark:text-emerald-300">
                                                            <MoneyAmount
                                                                value={inUsd}
                                                                label={usd}
                                                                size="sm"
                                                                showLabel={false}
                                                            />
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-300">—</span>
                                                    )}
                                                </Td>
                                                <Td align="end" money>
                                                    {outUsd ? (
                                                        <span className="text-rose-700 dark:text-rose-300">
                                                            <MoneyAmount
                                                                value={outUsd}
                                                                label={usd}
                                                                size="sm"
                                                                showLabel={false}
                                                            />
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-300">—</span>
                                                    )}
                                                </Td>
                                                <Td align="end" money>
                                                    {inIqd ? (
                                                        <span className="text-emerald-700 dark:text-emerald-300">
                                                            <MoneyAmount
                                                                value={inIqd}
                                                                label={iqd}
                                                                size="sm"
                                                                showLabel={false}
                                                            />
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-300">—</span>
                                                    )}
                                                </Td>
                                                <Td align="end" money>
                                                    {outIqd ? (
                                                        <span className="text-rose-700 dark:text-rose-300">
                                                            <MoneyAmount
                                                                value={outIqd}
                                                                label={iqd}
                                                                size="sm"
                                                                showLabel={false}
                                                            />
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-300">—</span>
                                                    )}
                                                </Td>
                                                <Td muted>{row.project?.name || '—'}</Td>
                                                {manage && (
                                                    <Td align="center">
                                                        <div className="inline-flex items-center gap-1">
                                                            <Link
                                                                href={route(
                                                                    'vault.transactions.edit',
                                                                    row.id,
                                                                )}
                                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-teal-700 transition hover:bg-teal-50 dark:text-teal-300 dark:hover:bg-slate-800"
                                                                aria-label={t('edit')}
                                                            >
                                                                <NavIcon
                                                                    name="edit"
                                                                    className="text-sm"
                                                                />
                                                            </Link>
                                                            <button
                                                                type="button"
                                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-rose-700 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                                                aria-label={t('delete')}
                                                                onClick={() => {
                                                                    if (
                                                                        confirm(
                                                                            t('confirm_soft_delete'),
                                                                        )
                                                                    ) {
                                                                        router.delete(
                                                                            route(
                                                                                'vault.transactions.destroy',
                                                                                row.id,
                                                                            ),
                                                                        );
                                                                    }
                                                                }}
                                                            >
                                                                <NavIcon
                                                                    name="trash"
                                                                    className="text-sm"
                                                                />
                                                            </button>
                                                        </div>
                                                    </Td>
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </DataTable>
                            <LedgerPagination
                                page={transactions}
                                onPage={(page) => apply({ page })}
                            />
                        </>
                    )}
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
