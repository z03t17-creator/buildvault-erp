import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import FlashBanner from '@/Components/FlashBanner';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

export default function Transactions({ vault, balances, transactions, filters, types, projects }) {
    const t = useTranslations();
    const iqd = t('IQD');
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
                    title={t('vault_ledger')}
                    subtitle={t('vault_ledger_subtitle')}
                    actions={
                        <Link href={route('dashboards.vault')}>
                            <SecondaryButton type="button">{t('vault_dashboard')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('vault_ledger')} />

            <PageShell>
                <div className="grid gap-3 sm:grid-cols-3">
                    <BalanceCard
                        label={t('current_balance')}
                        value={bal.current_iqd}
                        iqd={iqd}
                    />
                    <BalanceCard
                        label={t('available_balance')}
                        value={bal.available_iqd}
                        iqd={iqd}
                    />
                    <BalanceCard
                        label={t('reserved_balance')}
                        value={bal.reserved_iqd}
                        iqd={iqd}
                    />
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
                            type="date"
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                            value={filters?.from || ''}
                            onChange={(e) => apply({ from: e.target.value })}
                            aria-label={t('from_date')}
                        />
                        <input
                            type="date"
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                            value={filters?.to || ''}
                            onChange={(e) => apply({ to: e.target.value })}
                            aria-label={t('to_date')}
                        />
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
                        <label className="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input
                                type="checkbox"
                                checked={!!filters?.include_non_cash}
                                onChange={(e) => apply({ include_non_cash: e.target.checked ? 1 : 0 })}
                            />
                            {t('include_pool_rows')}
                        </label>
                    </div>
                </DataPanel>

                <DataPanel padded={false}>
                    {rows.length === 0 ? (
                        <div className="p-5">
                            <EmptyState title={t('no_ledger_rows')} />
                        </div>
                    ) : (
                        <DataTable minWidth="56rem" caption={t('vault_ledger')}>
                            <thead>
                                <tr>
                                    <Th>{t('date')}</Th>
                                    <Th>{t('type')}</Th>
                                    <Th align="end">{iqd}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('user')}</Th>
                                    <Th>{t('reference')}</Th>
                                    <Th>{t('description')}</Th>
                                    <Th>{t('created_at')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <Td className="whitespace-nowrap tabular-nums">
                                            {row.date || '—'}
                                        </Td>
                                        <Td>
                                            <span
                                                className={
                                                    row.direction === 'in'
                                                        ? 'text-emerald-700 dark:text-emerald-400'
                                                        : row.direction === 'out'
                                                          ? 'text-rose-700 dark:text-rose-400'
                                                          : 'text-slate-500'
                                                }
                                            >
                                                {typeLabel(row.type)}
                                            </span>
                                        </Td>
                                        <Td align="end">
                                            <span dir="ltr" className="inline-block">
                                                {row.direction === 'out'
                                                    ? '−'
                                                    : row.direction === 'in'
                                                      ? '+'
                                                      : ''}
                                                <MoneyAmount
                                                    value={row.amount_iqd}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            </span>
                                        </Td>
                                        <Td>{row.project?.name || '—'}</Td>
                                        <Td muted>{row.user?.name || '—'}</Td>
                                        <Td muted className="font-mono text-xs">
                                            {row.reference || '—'}
                                        </Td>
                                        <Td className="max-w-xs truncate" title={row.description || ''}>
                                            {row.description || '—'}
                                        </Td>
                                        <Td muted className="whitespace-nowrap tabular-nums text-xs">
                                            {row.created_at
                                                ? new Date(row.created_at).toLocaleString()
                                                : '—'}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>

                {transactions?.links?.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {transactions.links.map((link, idx) => (
                            <button
                                key={`${link.label}-${idx}`}
                                type="button"
                                disabled={!link.url}
                                className={`rounded border px-3 py-1 text-sm ${
                                    link.active
                                        ? 'border-emerald-600 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40'
                                        : 'border-slate-300 text-slate-600 dark:border-slate-600'
                                } disabled:opacity-40`}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}

                {vault?.name && (
                    <p className="text-xs text-slate-500">
                        {vault.name} · {t('pool_helper_note')}
                    </p>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}

function BalanceCard({ label, value, iqd }) {
    return (
        <div className="bv-panel flex flex-col gap-1 px-4 py-3">
            <p className="text-sm font-medium uppercase tracking-wide text-slate-700 dark:text-slate-300">
                {label}
            </p>
            <MoneyAmount value={value} label={iqd} size="lg" />
        </div>
    );
}
