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
import { Head, Link, router } from '@inertiajs/react';

export default function Transactions({
    vault,
    balances,
    transactions,
    filters,
    types,
    projects,
    canManage,
}) {
    const t = useTranslations();
    const manage = canManage || useCan('vault.ledgerManage');
    const rows = transactions?.data || [];
    const bal = balances || {};

    const apply = (next) => {
        router.get(
            route('vault.transactions'),
            { ...filters, ...next },
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
                    title={t('vault')}
                    subtitle={t('qasa_ledger_hint')}
                    actions={
                        <>
                            <Link href={route('dashboards.vault')}>
                                <SecondaryButton type="button">{t('vault_dashboard')}</SecondaryButton>
                            </Link>
                            {manage && (
                                <Link href={route('vault.transactions.create')}>
                                    <PrimaryButton type="button">{t('add_ledger_entry')}</PrimaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={t('vault')} />

            <PageShell>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <BalanceCard label={`${t('available_cash')} USD`} value={bal.available_usd} currency="USD" />
                    <BalanceCard label={`${t('available_cash')} IQD`} value={bal.available_iqd} currency="IQD" />
                    <BalanceCard label={`${t('current_balance')} USD`} value={bal.current_usd} currency="USD" />
                    <BalanceCard label={`${t('current_balance')} IQD`} value={bal.current_iqd} currency="IQD" />
                </div>

                {!bal.balance_matches_ledger && (
                    <FlashBanner tone="warning">
                        {t('ledger_balance_mismatch', {
                            vault: bal.current_iqd,
                            ledger: bal.ledger_cash_iqd,
                        })}
                    </FlashBanner>
                )}

                <DataPanel title={t('filters')}>
                    <div className="flex flex-wrap gap-3">
                        <select
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                            value={filters?.type || ''}
                            onChange={(e) => apply({ type: e.target.value })}
                        >
                            <option value="">{t('all_types')}</option>
                            {(types || []).map((type) => (
                                <option key={type} value={type}>
                                    {typeLabel(type)}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                            value={filters?.project_id || ''}
                            onChange={(e) => apply({ project_id: e.target.value || '' })}
                        >
                            <option value="">{t('all_projects')}</option>
                            {(projects || []).map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <input
                            type="search"
                            className="min-w-[12rem] flex-1 rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                            placeholder={t('search_ledger')}
                            defaultValue={filters?.q || ''}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    apply({ q: e.currentTarget.value });
                                }
                            }}
                        />
                    </div>
                </DataPanel>

                <DataPanel padded={false}>
                    {rows.length === 0 ? (
                        <div className="p-5">
                            <EmptyState title={t('no_ledger_rows')} />
                        </div>
                    ) : (
                        <DataTable minWidth="64rem" caption={t('vault_ledger')}>
                            <thead>
                                <tr>
                                    <Th>{t('date')}</Th>
                                    <Th>{t('description')}</Th>
                                    <Th align="center">{t('direction')}</Th>
                                    <Th align="end">{t('money_in')} USD</Th>
                                    <Th align="end">{t('money_out')} USD</Th>
                                    <Th align="end">{t('money_in')} IQD</Th>
                                    <Th align="end">{t('money_out')} IQD</Th>
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
                                            <Td className="whitespace-nowrap font-mono text-sm tabular-nums">
                                                {row.date || '—'}
                                            </Td>
                                            <Td className="text-left max-w-xs truncate" title={row.description || ''}>
                                                {row.description || typeLabel(row.type)}
                                            </Td>
                                            <Td align="center">
                                                <span className="inline-flex rounded px-2 py-0.5 text-xs font-medium">
                                                    {row.direction === 'in'
                                                        ? t('money_in')
                                                        : row.direction === 'out'
                                                          ? t('money_out')
                                                          : '—'}
                                                </span>
                                            </Td>
                                            <Td align="end" className="font-mono tabular-nums">
                                                {inUsd ? <MoneyAmount value={inUsd} label="USD" size="sm" showLabel={false} /> : '—'}
                                            </Td>
                                            <Td align="end" className="font-mono tabular-nums">
                                                {outUsd ? <MoneyAmount value={outUsd} label="USD" size="sm" showLabel={false} /> : '—'}
                                            </Td>
                                            <Td align="end" className="font-mono tabular-nums">
                                                {inIqd ? <MoneyAmount value={inIqd} label="IQD" size="sm" showLabel={false} /> : '—'}
                                            </Td>
                                            <Td align="end" className="font-mono tabular-nums">
                                                {outIqd ? <MoneyAmount value={outIqd} label="IQD" size="sm" showLabel={false} /> : '—'}
                                            </Td>
                                            <Td className="text-left">{row.project?.name || '—'}</Td>
                                            {manage && (
                                                <Td align="center">
                                                    <div className="inline-flex gap-2">
                                                        <Link
                                                            href={route('vault.transactions.edit', row.id)}
                                                            className="text-xs text-emerald-700 underline dark:text-emerald-400"
                                                        >
                                                            {t('edit')}
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            className="text-xs text-rose-700 underline dark:text-rose-400"
                                                            onClick={() => {
                                                                if (confirm(t('confirm_soft_delete'))) {
                                                                    router.delete(route('vault.transactions.destroy', row.id));
                                                                }
                                                            }}
                                                        >
                                                            {t('delete')}
                                                        </button>
                                                    </div>
                                                </Td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>

                {vault?.name && (
                    <p className="text-xs text-slate-500">{vault.name}</p>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}

function BalanceCard({ label, value, currency }) {
    return (
        <div className="bv-panel flex flex-col gap-1 px-4 py-3">
            <p className="text-sm font-medium uppercase tracking-wide text-slate-700 dark:text-slate-300">
                {label}
            </p>
            <MoneyAmount value={value} label={currency} size="lg" />
        </div>
    );
}
