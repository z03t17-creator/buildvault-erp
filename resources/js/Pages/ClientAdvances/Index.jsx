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

function RetentionChip({ holds, t }) {
    const count = (holds || []).length;
    if (!count) {
        return <span className="text-xs text-slate-400">—</span>;
    }

    const matured = (holds || []).some((h) => h.status === 'matured');

    return (
        <span
            className={
                'inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold ' +
                (matured
                    ? 'bg-amber-500/15 text-amber-900 dark:text-amber-200'
                    : 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300')
            }
        >
            <NavIcon name="insurance" className="text-xs" />
            {matured ? t('status_matured') : t('client_retention_locked')}
        </span>
    );
}

export default function Index({ advances }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const canCreate = useCan('clientAdvances.create');
    const list = advances || [];

    const totalUsd = list.reduce((sum, row) => sum + (Number(row.amount_usd) || 0), 0);
    const totalIqd = list.reduce((sum, row) => sum + (Number(row.amount_iqd) || 0), 0);
    const lockedCount = list.filter((row) => (row.retention_holds || []).length > 0).length;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('client_advances')}
                    subtitle={t('client_advances_page_hint')}
                    icon={<NavIcon name="clientAdvances" className="text-lg" />}
                    actions={
                        canCreate ? (
                            <Link href={route('client-advances.create')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-emerald-600 hover:!bg-emerald-500"
                                >
                                    <NavIcon name="moneyIn" className="text-sm" />
                                    {t('new_client_advance')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('client_advances')} />
            <PageShell className="!space-y-6">
                {list.length > 0 && (
                    <section>
                        <div className="mb-3">
                            <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                {t('client_advances_totals')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('client_advances_totals_hint', {
                                    count: list.length,
                                    locked: lockedCount,
                                })}
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <MoneyStat
                                label={`${t('client_money_in')} ${usd}`}
                                value={totalUsd}
                                currency={usd}
                                accent
                            />
                            <MoneyStat
                                label={`${t('client_money_in')} ${iqd}`}
                                value={totalIqd}
                                currency={iqd}
                                accent
                            />
                        </div>
                    </section>
                )}

                {list.length === 0 ? (
                    <EmptyState
                        icon="clientAdvances"
                        title={t('client_advances_empty_title')}
                        description={t('client_advances_empty_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('client-advances.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-emerald-600 hover:!bg-emerald-500"
                                    >
                                        <NavIcon name="moneyIn" className="text-sm" />
                                        {t('new_client_advance')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <NavIcon name="clientAdvances" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('client_advances_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('client_advances_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="56rem" caption={t('client_advances')}>
                            <thead>
                                <tr>
                                    <Th>{t('date')}</Th>
                                    <Th>{t('client_name')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th align="end" className="text-emerald-700 dark:text-emerald-400">
                                        {usd}
                                    </Th>
                                    <Th align="end" className="text-emerald-700 dark:text-emerald-400">
                                        {iqd}
                                    </Th>
                                    <Th>{t('client_retention_col')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((row) => {
                                    const usdAmt = Number(row.amount_usd) || 0;
                                    const iqdAmt = Number(row.amount_iqd) || 0;

                                    return (
                                        <tr key={row.id}>
                                            <Td className="whitespace-nowrap font-sans tabular-nums">
                                                <span className="inline-flex items-center gap-2">
                                                    <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                                                        <NavIcon
                                                            name="moneyIn"
                                                            className="text-sm"
                                                        />
                                                    </span>
                                                    {row.received_on || '—'}
                                                </span>
                                            </Td>
                                            <Td>
                                                <Link
                                                    href={route('client-advances.show', row.id)}
                                                    className="font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                                >
                                                    {row.client_name}
                                                </Link>
                                                {row.reference && (
                                                    <span className="mt-0.5 block text-xs text-slate-400">
                                                        {row.reference}
                                                    </span>
                                                )}
                                            </Td>
                                            <Td muted>{row.project?.name || '—'}</Td>
                                            <Td align="end" money>
                                                {usdAmt > 0 ? (
                                                    <span className="text-emerald-700 dark:text-emerald-300">
                                                        <MoneyAmount
                                                            value={usdAmt}
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
                                                {iqdAmt > 0 ? (
                                                    <span className="text-emerald-700 dark:text-emerald-300">
                                                        <MoneyAmount
                                                            value={iqdAmt}
                                                            label={iqd}
                                                            size="sm"
                                                            showLabel={false}
                                                        />
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-300">—</span>
                                                )}
                                            </Td>
                                            <Td>
                                                <RetentionChip
                                                    holds={row.retention_holds}
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
