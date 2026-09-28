import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
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

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="grid gap-3 sm:grid-cols-3">
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
                    </section>

                    {!bal.balance_matches_ledger && (
                        <p className="border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
                            {t('ledger_balance_mismatch', {
                                vault: bal.current_iqd,
                                ledger: bal.ledger_cash_iqd,
                            })}
                        </p>
                    )}

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

                    <div className="overflow-x-auto border border-slate-200/80 bg-white/80 dark:border-slate-700 dark:bg-slate-900/70">
                        <table className="min-w-full text-sm">
                            <thead className="border-b text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 text-start">{t('date')}</th>
                                    <th className="px-3 py-2 text-start">{t('type')}</th>
                                    <th className="px-3 py-2 text-end">{t('amount_iqd')}</th>
                                    <th className="px-3 py-2 text-start">{t('project')}</th>
                                    <th className="px-3 py-2 text-start">{t('user')}</th>
                                    <th className="px-3 py-2 text-start">{t('reference')}</th>
                                    <th className="px-3 py-2 text-start">{t('description')}</th>
                                    <th className="px-3 py-2 text-start">{t('created_at')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-3 py-2 tabular-nums whitespace-nowrap">{row.date || '—'}</td>
                                        <td className="px-3 py-2">
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
                                        </td>
                                        <td className="px-3 py-2 text-end">
                                            <span dir="ltr" className="inline-block">
                                                {row.direction === 'out' ? '−' : row.direction === 'in' ? '+' : ''}
                                                <MoneyAmount value={row.amount_iqd} label={iqd} size="md" />
                                            </span>
                                        </td>
                                        <td className="px-3 py-2">{row.project?.name || '—'}</td>
                                        <td className="px-3 py-2">{row.user?.name || '—'}</td>
                                        <td className="px-3 py-2 font-mono text-xs">{row.reference || '—'}</td>
                                        <td className="px-3 py-2 max-w-xs truncate" title={row.description || ''}>
                                            {row.description || '—'}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums text-xs text-slate-500 whitespace-nowrap">
                                            {row.created_at
                                                ? new Date(row.created_at).toLocaleString()
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                                {!rows.length && (
                                    <tr>
                                        <td colSpan={8} className="px-3 py-8 text-center text-slate-500">
                                            {t('no_ledger_rows')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function BalanceCard({ label, value, iqd }) {
    return (
        <div className="bv-card flex flex-col gap-1 rounded-sm px-4 py-3">
            <p className="text-sm font-medium uppercase tracking-wide text-slate-700 dark:text-slate-300">
                {label}
            </p>
            <MoneyAmount value={value} label={iqd} size="lg" />
        </div>
    );
}
