import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { formatIqd } from '@/lib/numberFormat';
import { Head, Link, usePage } from '@inertiajs/react';

function CashFlowChart({ series, t }) {
    const data = series || [];
    const width = 720;
    const height = 220;
    const pad = { top: 16, right: 12, bottom: 36, left: 56 };
    const innerW = width - pad.left - pad.right;
    const innerH = height - pad.top - pad.bottom;

    const maxVal = Math.max(
        1,
        ...data.map((d) => Math.max(d.inflow_iqd || 0, d.outflow_iqd || 0)),
    );

    const n = Math.max(data.length, 1);
    const groupW = innerW / n;
    const barW = Math.max(2, Math.min(10, groupW * 0.35));

    const hasActivity = data.some((d) => (d.inflow_iqd || 0) + (d.outflow_iqd || 0) > 0);

    return (
        <div className="overflow-x-auto">
            {!hasActivity && (
                <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">
                    {t('cash_flow_empty', { days: data.length })}
                </p>
            )}
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="min-w-full text-slate-400"
                role="img"
                aria-label={t('cash_flow_chart')}
            >
                {[0, 0.25, 0.5, 0.75, 1].map((tick) => {
                    const y = pad.top + innerH * (1 - tick);
                    return (
                        <g key={tick}>
                            <line
                                x1={pad.left}
                                x2={width - pad.right}
                                y1={y}
                                y2={y}
                                className="stroke-slate-200 dark:stroke-slate-700"
                                strokeWidth="1"
                            />
                            <text
                                x={pad.left - 8}
                                y={y + 3}
                                textAnchor="end"
                                className="fill-slate-400 text-[10px]"
                            >
                                {Math.round(maxVal * tick)}
                            </text>
                        </g>
                    );
                })}

                {data.map((d, i) => {
                    const x0 = pad.left + i * groupW + groupW / 2;
                    const inH = ((d.inflow_iqd || 0) / maxVal) * innerH;
                    const outH = ((d.outflow_iqd || 0) / maxVal) * innerH;
                    const showLabel = i % Math.ceil(n / 8) === 0 || i === n - 1;

                    return (
                        <g key={d.date}>
                            <rect
                                x={x0 - barW - 1}
                                y={pad.top + innerH - inH}
                                width={barW}
                                height={inH}
                                className="fill-emerald-500/90"
                            >
                                <title>{`${d.label}: +${d.inflow_iqd} ${t('IQD')}`}</title>
                            </rect>
                            <rect
                                x={x0 + 1}
                                y={pad.top + innerH - outH}
                                width={barW}
                                height={outH}
                                className="fill-rose-500/80"
                            >
                                <title>{`${d.label}: −${d.outflow_iqd} ${t('IQD')}`}</title>
                            </rect>
                            {showLabel && (
                                <text
                                    x={x0}
                                    y={height - 12}
                                    textAnchor="middle"
                                    className="fill-slate-500 text-[9px]"
                                >
                                    {d.label}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>
            <div className="mt-2 flex flex-wrap gap-4 text-sm font-medium text-slate-700 dark:text-slate-300">
                <span className="inline-flex items-center gap-1.5">
                    <span className="inline-block h-2.5 w-2.5 bg-emerald-500" /> {t('inflow')}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="inline-block h-2.5 w-2.5 bg-rose-500" /> {t('outflow')}
                </span>
            </div>
        </div>
    );
}

function HealthBadge({ item }) {
    return (
        <div className="bv-card flex flex-col gap-1 rounded-sm px-4 py-3">
            <div className="flex items-start justify-between gap-2">
                <p className="font-display text-base font-semibold tracking-wide text-slate-900 dark:text-white">
                    {item.label}
                </p>
                <StatusBadge status={item.status} />
            </div>
            <p className="text-sm text-slate-700 dark:text-slate-300">{item.detail}</p>
        </div>
    );
}

export default function Vault({
    vault,
    liquidity,
    insurance,
    pools,
    health,
    cashFlow,
}) {
    const t = useTranslations();
    const iqd = t('IQD');
    const liq = liquidity || {};
    const ins = insurance || {};
    const pool = pools || {};
    const { flash, insuranceSettings } = usePage().props;
    const canAudit = useCan('vault.audit');
    const canRetention = useCan('vault.retention');
    const canLedger = useCan('vault.ledger');
    const holdbackPct = insuranceSettings?.holdback_pct ?? 10;
    const maturityMonths = insuranceSettings?.maturity_months ?? 6;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('zhako_vault')}
                    subtitle={t('vault_subtitle')}
                    actions={
                        <>
                            <Link href={route('dashboard')}>
                                <SecondaryButton type="button">{t('home')}</SecondaryButton>
                            </Link>
                            {canLedger && (
                                <Link href={route('vault.transactions')}>
                                    <SecondaryButton type="button">{t('vault_ledger')}</SecondaryButton>
                                </Link>
                            )}
                            {canAudit && (
                                <Link href={route('audit.index')}>
                                    <SecondaryButton type="button">{t('audit_log')}</SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={t('vault_dashboard')} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <p className="border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {flash.success}
                        </p>
                    )}
                    {!vault && (
                        <p className="border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
                            {t('no_vault_found')}
                        </p>
                    )}

                    <section>
                        <div className="bv-card relative overflow-hidden bg-gradient-to-br from-white via-emerald-50/40 to-slate-50 p-5 sm:p-6 dark:from-slate-900 dark:via-emerald-950/30 dark:to-slate-950">
                            <p className="font-display text-sm font-medium uppercase tracking-[0.18em] text-slate-700 dark:text-slate-300">
                                {t('balance_iqd')}
                            </p>
                            <p className="mt-3">
                                <MoneyAmount value={vault?.balance_iqd} label={iqd} size="hero" />
                            </p>
                            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                {t('available_pending', {
                                    available: formatIqd(liq.available_iqd, iqd),
                                    pending: formatIqd(liq.pending_payouts_iqd, iqd),
                                })}
                            </p>
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                <div className="flex flex-col gap-1">
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-400">
                                        {t('current_balance')}
                                    </p>
                                    <MoneyAmount value={vault?.balance_iqd} label={iqd} size="md" />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-400">
                                        {t('available_balance')}
                                    </p>
                                    <MoneyAmount value={liq.available_iqd} label={iqd} size="md" />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-400">
                                        {t('reserved_balance')}
                                    </p>
                                    <MoneyAmount value={liq.reserved_insurance_iqd} label={iqd} size="md" />
                                </div>
                            </div>
                            {canLedger && (
                                <p className="mt-3">
                                    <Link
                                        href={route('vault.transactions')}
                                        className="text-sm font-medium text-emerald-700 underline dark:text-emerald-400"
                                    >
                                        {t('view_full_ledger')}
                                    </Link>
                                </p>
                            )}
                        </div>
                    </section>

                    <section>
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('health')}
                        </h3>
                        <div className="mt-3 grid gap-3 sm:grid-cols-3">
                            {(health || []).map((h) => (
                                <HealthBadge key={h.key} item={h} />
                            ))}
                        </div>
                    </section>

                    <section className="bv-surface p-5">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                    {t('insurance_reserve')}
                                </h3>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {t('insurance_reserve_hint', {
                                        percent: holdbackPct,
                                        months: maturityMonths,
                                    })}
                                </p>
                            </div>
                            {canRetention && (
                                <Link
                                    href={route('retention-holds.index')}
                                    className="text-sm font-medium text-emerald-700 underline dark:text-emerald-400"
                                >
                                    {t('manage_holds')}
                                </Link>
                            )}
                        </div>
                        <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Stat
                                label={t('retention_pool')}
                                amount={ins.retention_pool_iqd}
                                iqd={iqd}
                            />
                            <Stat
                                label={t('holding')}
                                amount={ins.holding_iqd}
                                count={ins.holding_count || 0}
                                iqd={iqd}
                            />
                            <Stat
                                label={t('matured')}
                                amount={ins.matured_iqd}
                                count={ins.matured_count || 0}
                                iqd={iqd}
                                accent={ins.matured_count > 0 ? 'amber' : null}
                            />
                            <Stat
                                label={t('reserved_liq')}
                                amount={liq.reserved_insurance_iqd}
                                iqd={iqd}
                            />
                        </div>
                    </section>

                    <section>
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('allocation_pools')}
                        </h3>
                        <div className="mt-3 grid gap-3 sm:grid-cols-5">
                            {[
                                [t('pool_expenses'), pool.expenses_iqd],
                                [t('pool_payroll'), pool.payroll_iqd],
                                [t('pool_retention'), pool.retention_iqd],
                                [t('pool_penalty'), pool.penalty_iqd],
                                [t('pool_profit'), pool.profit_iqd],
                            ].map(([label, value]) => (
                                <Stat
                                    key={label}
                                    label={label}
                                    amount={value}
                                    iqd={iqd}
                                    className="bv-card bg-slate-50/80 px-3 py-3 dark:bg-slate-900/50"
                                />
                            ))}
                        </div>
                    </section>

                    <section className="bv-surface p-5">
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('cash_flow_30')}
                        </h3>
                        <p className="mt-1 mb-4 text-sm text-slate-500 dark:text-slate-400">
                            {t('cash_flow_hint')}
                        </p>
                        <CashFlowChart series={cashFlow} t={t} />
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

/**
 * Metric cell: label above amount as one unit.
 * Count stays outside MoneyAmount so RTL never reorders digits into the IQD label.
 */
function Stat({ label, amount, count, iqd, accent, className = '' }) {
    const accentClass =
        accent === 'amber' ? 'text-amber-800 dark:text-amber-200' : undefined;

    return (
        <div className={`flex flex-col gap-1 ${className}`.trim()}>
            <p className="text-sm font-medium uppercase tracking-wide text-slate-700 dark:text-slate-300">
                {label}
            </p>
            <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <MoneyAmount
                    value={amount}
                    label={iqd}
                    size="lg"
                    className={accentClass}
                />
                {count != null && (
                    <span
                        dir="ltr"
                        className="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        · {Number(count) || 0}
                    </span>
                )}
            </div>
        </div>
    );
}
