import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

function formatUsd(n) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(Number(n) || 0);
}

function formatIqd(n) {
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 0,
    }).format(Number(n) || 0) + ' IQD';
}

function CashFlowChart({ series }) {
    const data = series || [];
    const width = 720;
    const height = 220;
    const pad = { top: 16, right: 12, bottom: 36, left: 48 };
    const innerW = width - pad.left - pad.right;
    const innerH = height - pad.top - pad.bottom;

    const maxVal = Math.max(
        1,
        ...data.map((d) => Math.max(d.inflow_usd || 0, d.outflow_usd || 0)),
    );

    const n = Math.max(data.length, 1);
    const groupW = innerW / n;
    const barW = Math.max(2, Math.min(10, groupW * 0.35));

    const hasActivity = data.some((d) => (d.inflow_usd || 0) + (d.outflow_usd || 0) > 0);

    return (
        <div className="overflow-x-auto">
            {!hasActivity && (
                <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">
                    No vault transactions in the last {data.length} days yet.
                </p>
            )}
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="min-w-full text-slate-400"
                role="img"
                aria-label="Cash-flow chart for the last 30 days"
            >
                {[0, 0.25, 0.5, 0.75, 1].map((t) => {
                    const y = pad.top + innerH * (1 - t);
                    return (
                        <g key={t}>
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
                                {Math.round(maxVal * t)}
                            </text>
                        </g>
                    );
                })}

                {data.map((d, i) => {
                    const x0 = pad.left + i * groupW + groupW / 2;
                    const inH = ((d.inflow_usd || 0) / maxVal) * innerH;
                    const outH = ((d.outflow_usd || 0) / maxVal) * innerH;
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
                                <title>{`${d.label}: +${d.inflow_usd} USD in`}</title>
                            </rect>
                            <rect
                                x={x0 + 1}
                                y={pad.top + innerH - outH}
                                width={barW}
                                height={outH}
                                className="fill-rose-500/80"
                            >
                                <title>{`${d.label}: −${d.outflow_usd} USD out`}</title>
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
            <div className="mt-2 flex gap-4 text-xs text-slate-500 dark:text-slate-400">
                <span className="inline-flex items-center gap-1.5">
                    <span className="inline-block h-2.5 w-2.5 bg-emerald-500" /> Inflow (deposit / adjustment)
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="inline-block h-2.5 w-2.5 bg-rose-500" /> Outflow (withdrawal)
                </span>
            </div>
        </div>
    );
}

function HealthBadge({ item }) {
    return (
        <div className="bv-card rounded-sm px-4 py-3">
            <div className="flex items-center justify-between gap-2">
                <p className="font-display text-sm font-semibold tracking-wide text-slate-900 dark:text-white">
                    {item.label}
                </p>
                <StatusBadge status={item.status} />
            </div>
            <p className="mt-1 text-xs text-slate-600 dark:text-slate-400">{item.detail}</p>
        </div>
    );
}

export default function Vault({
    vault,
    liquidity,
    fx,
    insurance,
    pools,
    health,
    cashFlow,
}) {
    const liq = liquidity || {};
    const fxData = fx || {};
    const ins = insurance || {};
    const pool = pools || {};
    const { flash } = usePage().props;

    const overrideForm = useForm({
        rate: fxData.rate || '',
        note: '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Zhako Vault"
                    subtitle="Dual-currency ledger · FX · insurance reserve"
                    actions={
                        <>
                            <Link href={route('dashboard')}>
                                <SecondaryButton type="button">Home</SecondaryButton>
                            </Link>
                            <Link href={route('audit.index')}>
                                <SecondaryButton type="button">Audit log</SecondaryButton>
                            </Link>
                            <PrimaryButton
                                type="button"
                                onClick={() => router.post(route('dashboards.vault.refresh-fx'))}
                            >
                                Refresh FX
                            </PrimaryButton>
                        </>
                    }
                />
            }
        >
            <Head title="Vault dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <p className="border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {flash.success}
                        </p>
                    )}
                    {!vault && (
                        <p className="border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
                            No vault found. Run seeders to create the Zhako vault.
                        </p>
                    )}

                    {/* Dual balances */}
                    <section className="grid gap-4 sm:grid-cols-2">
                        <div className="bv-card relative overflow-hidden bg-gradient-to-br from-white via-emerald-50/40 to-slate-50 p-5 sm:p-6 dark:from-slate-900 dark:via-emerald-950/30 dark:to-slate-950">
                            <p className="font-display text-xs font-semibold uppercase tracking-[0.28em] text-slate-500 dark:text-slate-400">
                                Balance USD
                            </p>
                            <p className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-900 tabular-nums dark:text-white sm:text-4xl">
                                {formatUsd(vault?.balance_usd)}
                            </p>
                            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                Available {formatUsd(liq.available_usd)} · Pending {formatUsd(liq.pending_payouts_usd)}
                            </p>
                        </div>
                        <div className="bv-card relative overflow-hidden bg-gradient-to-br from-white via-slate-50 to-emerald-50/30 p-5 sm:p-6 dark:from-slate-900 dark:via-slate-950 dark:to-emerald-950/20">
                            <p className="font-display text-xs font-semibold uppercase tracking-[0.28em] text-slate-500 dark:text-slate-400">
                                Balance IQD
                            </p>
                            <p className="mt-3 font-display text-3xl font-semibold tracking-tight text-slate-900 tabular-nums dark:text-white sm:text-4xl">
                                {formatIqd(vault?.balance_iqd)}
                            </p>
                            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                Book balance at last ledger FX
                            </p>
                        </div>
                    </section>

                    {/* Health + FX */}
                    <section className="grid gap-4 lg:grid-cols-3">
                        <div className="space-y-3 lg:col-span-2">
                            <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                Health
                            </h3>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {(health || []).map((h) => (
                                    <HealthBadge key={h.key} item={h} />
                                ))}
                            </div>
                        </div>
                        <div className="bv-card space-y-4 p-5">
                            <div>
                                <p className="font-display text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
                                    Live FX
                                </p>
                                <p className="mt-3 font-display text-3xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">
                                    {Number(fxData.rate || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}
                                </p>
                                <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                    IQD per 1 USD
                                </p>
                                <dl className="mt-4 space-y-1 text-xs text-slate-500 dark:text-slate-400">
                                    <div className="flex justify-between gap-2">
                                        <dt>Source</dt>
                                        <dd className="font-medium capitalize text-slate-700 dark:text-slate-200">{fxData.source}</dd>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <dt>Fetched</dt>
                                        <dd className="tabular-nums">
                                            {fxData.fetched_at
                                                ? new Date(fxData.fetched_at).toLocaleString()
                                                : '—'}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <dt>Fallback</dt>
                                        <dd className="tabular-nums">{fxData.fallback_rate}</dd>
                                    </div>
                                </dl>
                            </div>
                            <form
                                className="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    overrideForm.post(route('dashboards.vault.override-fx'));
                                }}
                            >
                                <InputLabel value="Override rate (IQD/USD)" />
                                <TextInput
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    className="mt-1 block w-full"
                                    value={overrideForm.data.rate}
                                    onChange={(e) => overrideForm.setData('rate', e.target.value)}
                                />
                                <TextInput
                                    type="text"
                                    className="mt-1 block w-full"
                                    placeholder="Optional note"
                                    value={overrideForm.data.note}
                                    onChange={(e) => overrideForm.setData('note', e.target.value)}
                                />
                                <SecondaryButton type="submit" disabled={overrideForm.processing}>
                                    Override FX
                                </SecondaryButton>
                            </form>
                        </div>
                    </section>

                    {/* Insurance */}
                    <section className="bv-surface p-5">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                    Insurance reserve
                                </h3>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Shared 10% — returned to staff payroll after 6 months
                                </p>
                            </div>
                            <Link
                                href={route('retention-holds.index')}
                                className="text-sm font-medium text-emerald-700 underline dark:text-emerald-400"
                            >
                                Manage holds
                            </Link>
                        </div>
                        <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Stat label="Retention pool" value={formatUsd(ins.retention_pool_usd)} />
                            <Stat label="Holding" value={`${formatUsd(ins.holding_usd)} · ${ins.holding_count || 0}`} />
                            <Stat label="Matured" value={`${formatUsd(ins.matured_usd)} · ${ins.matured_count || 0}`} accent={ins.matured_count > 0 ? 'amber' : null} />
                            <Stat label="Reserved (liq.)" value={formatUsd(liq.reserved_insurance_usd)} />
                        </div>
                    </section>

                    {/* Pools strip */}
                    <section>
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            Allocation pools
                        </h3>
                        <div className="mt-3 grid gap-3 sm:grid-cols-5">
                            {[
                                ['Expenses', pool.expenses_usd],
                                ['Payroll', pool.payroll_usd],
                                ['Retention', pool.retention_usd],
                                ['Penalty', pool.penalty_usd],
                                ['Profit', pool.profit_usd],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="bv-card bg-slate-50/80 px-3 py-3 dark:bg-slate-900/50"
                                >
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{label}</p>
                                    <p className="mt-1 font-display text-xl font-semibold tabular-nums text-slate-900 dark:text-white">
                                        {formatUsd(value)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Cash flow */}
                    <section className="bv-surface p-5">
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            Cash flow · 30 days
                        </h3>
                        <p className="mt-1 mb-4 text-sm text-slate-500 dark:text-slate-400">
                            Daily vault inflows vs withdrawals
                        </p>
                        <CashFlowChart series={cashFlow} />
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Stat({ label, value, accent }) {
    const accentClass =
        accent === 'amber'
            ? 'text-amber-800 dark:text-amber-200'
            : 'text-slate-900 dark:text-white';

    return (
        <div>
            <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{label}</p>
            <p className={`mt-1 font-display text-xl font-semibold tabular-nums ${accentClass}`}>{value}</p>
        </div>
    );
}
