import DataPanel from '@/Components/DataPanel';
import FlashBanner from '@/Components/FlashBanner';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
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

function DualBarChart({ title, subtitle, bars, ariaLabel, format = 'money' }) {
    const max = Math.max(1, ...bars.map((b) => Number(b.value) || 0));

    return (
        <DataPanel title={title} subtitle={subtitle}>
            <div className="space-y-3" role="img" aria-label={ariaLabel || title}>
                {bars.map((bar) => {
                    const pct = Math.max(2, ((Number(bar.value) || 0) / max) * 100);

                    return (
                        <div key={bar.key}>
                            <div className="mb-1 flex items-center justify-between gap-3 text-sm">
                                <span className="font-medium text-slate-700 dark:text-slate-200">
                                    {bar.label}
                                </span>
                                <span
                                    dir="ltr"
                                    className="font-sans tabular-nums text-slate-600 dark:text-slate-300"
                                >
                                    {format === 'money' ? (
                                        <>
                                            <MoneyAmount
                                                value={bar.value}
                                                label={bar.currency}
                                                size="sm"
                                                showLabel={false}
                                            />{' '}
                                            <span className="text-xs text-slate-400">
                                                {bar.currency}
                                            </span>
                                        </>
                                    ) : (
                                        Number(bar.value || 0).toLocaleString()
                                    )}
                                </span>
                            </div>
                            <div className="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    className={`h-full rounded-full ${bar.color}`}
                                    style={{ width: `${pct}%` }}
                                />
                            </div>
                        </div>
                    );
                })}
            </div>
        </DataPanel>
    );
}

function CashFlowChart({ series, currency, t }) {
    const data = series || [];
    const width = 720;
    const height = 220;
    const pad = { top: 16, right: 12, bottom: 36, left: 56 };
    const innerW = width - pad.left - pad.right;
    const innerH = height - pad.top - pad.bottom;
    const inKey = currency === 'USD' ? 'inflow_usd' : 'inflow_iqd';
    const outKey = currency === 'USD' ? 'outflow_usd' : 'outflow_iqd';

    const maxVal = Math.max(
        1,
        ...data.map((d) => Math.max(d[inKey] || 0, d[outKey] || 0)),
    );

    const n = Math.max(data.length, 1);
    const groupW = innerW / n;
    const barW = Math.max(2, Math.min(10, groupW * 0.35));
    const hasActivity = data.some((d) => (d[inKey] || 0) + (d[outKey] || 0) > 0);

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
                aria-label={`${t('cash_flow_chart')} ${currency}`}
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
                                {Math.round(maxVal * tick).toLocaleString()}
                            </text>
                        </g>
                    );
                })}

                {data.map((d, i) => {
                    const x0 = pad.left + i * groupW + groupW / 2;
                    const inH = ((d[inKey] || 0) / maxVal) * innerH;
                    const outH = ((d[outKey] || 0) / maxVal) * innerH;
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
                                <title>{`${d.label}: +${d[inKey]} ${currency}`}</title>
                            </rect>
                            <rect
                                x={x0 + 1}
                                y={pad.top + innerH - outH}
                                width={barW}
                                height={outH}
                                className="fill-rose-500/80"
                            >
                                <title>{`${d.label}: −${d[outKey]} ${currency}`}</title>
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
                    <span className="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500" />{' '}
                    {t('inflow')}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="inline-block h-2.5 w-2.5 rounded-sm bg-rose-500" />{' '}
                    {t('outflow')}
                </span>
            </div>
        </div>
    );
}

const VAULT_BOX_STYLES = {
    ledger: {
        shell: 'bg-teal-600 text-white shadow-teal-900/20 hover:bg-teal-500 dark:bg-teal-500 dark:text-slate-950 dark:hover:bg-teal-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    moneyIn: {
        shell: 'bg-emerald-600 text-white shadow-emerald-900/20 hover:bg-emerald-500 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    moneyOut: {
        shell: 'bg-rose-600 text-white shadow-rose-900/20 hover:bg-rose-500 dark:bg-rose-500 dark:text-slate-950 dark:hover:bg-rose-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    insurance: {
        shell: 'bg-amber-500 text-amber-950 shadow-amber-900/20 hover:bg-amber-400 dark:bg-amber-400 dark:hover:bg-amber-300',
        icon: 'bg-amber-950/15 text-amber-950',
    },
    settlements: {
        shell: 'bg-sky-600 text-white shadow-sky-900/20 hover:bg-sky-500 dark:bg-sky-500 dark:text-slate-950 dark:hover:bg-sky-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    reports: {
        shell: 'bg-slate-700 text-white shadow-slate-900/25 hover:bg-slate-600 dark:bg-slate-600 dark:hover:bg-slate-500',
        icon: 'bg-white/15 text-white',
    },
};

function VaultModuleBox({ href, icon, tone, title, hint }) {
    const style = VAULT_BOX_STYLES[tone] || VAULT_BOX_STYLES.ledger;

    return (
        <Link
            href={href}
            className={
                'group flex min-h-[7rem] flex-col justify-between rounded-2xl p-4 shadow-lg transition hover:-translate-y-0.5 ' +
                style.shell
            }
        >
            <span
                className={
                    'inline-flex h-11 w-11 items-center justify-center rounded-xl text-lg ' +
                    style.icon
                }
            >
                <NavIcon name={icon} className="text-lg" />
            </span>
            <span>
                <span className="block text-base font-semibold tracking-tight">{title}</span>
                {hint && (
                    <span className="mt-0.5 block text-xs font-medium opacity-85">{hint}</span>
                )}
            </span>
        </Link>
    );
}

function periodTotals(series, currency) {
    const inKey = currency === 'USD' ? 'inflow_usd' : 'inflow_iqd';
    const outKey = currency === 'USD' ? 'outflow_usd' : 'outflow_iqd';
    return (series || []).reduce(
        (acc, d) => {
            acc.in += Number(d[inKey]) || 0;
            acc.out += Number(d[outKey]) || 0;
            return acc;
        },
        { in: 0, out: 0 },
    );
}

export default function Vault({
    vault,
    liquidity,
    insurance,
    cashFlow,
}) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const liq = liquidity || {};
    const ins = insurance || {};
    const canRetention = useCan('vault.retention');
    const canLedger = useCan('vault.ledger');
    const canLedgerManage = useCan('vault.ledgerManage');
    const canSettlement = useCan('vault.settlement');
    const canReports = useCan('reports.view');

    const maturedCount = (ins.matured_count || 0);
    const flowUsd = periodTotals(cashFlow, 'USD');
    const flowIqd = periodTotals(cashFlow, 'IQD');

    const modules = [
        canLedger && {
            key: 'ledger',
            href: route('vault.transactions'),
            icon: 'vault',
            tone: 'ledger',
            title: t('vault_box_ledger'),
            hint: t('vault_box_ledger_hint'),
        },
        canLedgerManage && {
            key: 'money-in',
            href: route('vault.transactions.create', { direction: 'in' }),
            icon: 'moneyIn',
            tone: 'moneyIn',
            title: t('money_in'),
            hint: t('vault_box_money_in_hint'),
        },
        canLedgerManage && {
            key: 'money-out',
            href: route('vault.transactions.create', { direction: 'out' }),
            icon: 'moneyOut',
            tone: 'moneyOut',
            title: t('money_out'),
            hint: t('vault_box_money_out_hint'),
        },
        canRetention && {
            key: 'insurance',
            href: route('retention-holds.index'),
            icon: 'insurance',
            tone: 'insurance',
            title: t('vault_box_insurance'),
            hint: t('vault_box_insurance_hint'),
        },
        canSettlement && {
            key: 'settlements',
            href: route('settlements.index'),
            icon: 'settlements',
            tone: 'settlements',
            title: t('settlements'),
            hint: t('vault_box_settlements_hint'),
        },
        canReports && {
            key: 'reports',
            href: route('reports.index'),
            icon: 'reports',
            tone: 'reports',
            title: t('reports'),
            hint: t('vault_box_reports_hint'),
        },
    ].filter(Boolean);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('zhako_vault')}
                    subtitle={t('vault_subtitle')}
                    icon={<NavIcon name="vault" className="text-lg" />}
                    actions={
                        canLedger ? (
                            <Link href={route('vault.transactions')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="vault" className="text-sm" />
                                    {t('vault_ledger')}
                                </SecondaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('vault_dashboard')} />

            <PageShell className="!space-y-8">
                {!vault && (
                    <FlashBanner tone="warning">{t('no_vault_found')}</FlashBanner>
                )}

                {maturedCount > 0 && canRetention && (
                    <Link
                        href={route('retention-holds.index')}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300/80 bg-amber-50 px-4 py-3.5 text-amber-950 transition hover:border-amber-400 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        <span className="flex items-center gap-3 text-sm font-semibold">
                            <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/20">
                                <NavIcon name="insurance" className="text-base" />
                            </span>
                            {t('vault_matured_stripe')}
                            <span
                                dir="ltr"
                                className="rounded-lg bg-amber-500 px-2 py-0.5 font-sans text-xs font-bold text-white tabular-nums"
                            >
                                {maturedCount}
                            </span>
                        </span>
                        <span className="text-sm font-medium underline underline-offset-2">
                            {t('manage_holds')}
                        </span>
                    </Link>
                )}

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
                            value={liq.available_usd}
                            currency={usd}
                            accent
                        />
                        <MoneyStat
                            label={`${t('available_cash')} ${iqd}`}
                            value={liq.available_iqd}
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
                            value={vault?.balance_usd}
                            currency={usd}
                        />
                        <MoneyStat
                            label={`${t('current_balance')} ${iqd}`}
                            value={vault?.balance_iqd}
                            currency={iqd}
                        />
                    </div>
                </section>

                {modules.length > 0 && (
                    <section>
                        <div className="mb-3">
                            <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                {t('vault_modules')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('vault_modules_hint')}
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {modules.map((mod) => (
                                <VaultModuleBox key={mod.key} {...mod} />
                            ))}
                        </div>
                    </section>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <DualBarChart
                        title={t('home_chart_locked')}
                        subtitle={t('home_chart_locked_hint')}
                        ariaLabel={t('home_chart_locked')}
                        bars={[
                            {
                                key: 'locked-usd',
                                label: `${t('home_locked')} ${usd}`,
                                value:
                                    (Number(liq.reserved_insurance_usd) || 0) +
                                    (Number(liq.pending_payouts_usd) || 0),
                                currency: usd,
                                color: 'bg-amber-500',
                            },
                            {
                                key: 'free-usd',
                                label: `${t('home_free')} ${usd}`,
                                value: liq.available_usd ?? 0,
                                currency: usd,
                                color: 'bg-teal-500',
                            },
                            {
                                key: 'locked-iqd',
                                label: `${t('home_locked')} ${iqd}`,
                                value:
                                    (Number(liq.reserved_insurance_iqd) || 0) +
                                    (Number(liq.pending_payouts_iqd) || 0),
                                currency: iqd,
                                color: 'bg-amber-400',
                            },
                            {
                                key: 'free-iqd',
                                label: `${t('home_free')} ${iqd}`,
                                value: liq.available_iqd ?? 0,
                                currency: iqd,
                                color: 'bg-emerald-500',
                            },
                        ]}
                    />
                    <DualBarChart
                        title={t('vault_chart_period_flow')}
                        subtitle={t('vault_chart_period_flow_hint')}
                        ariaLabel={t('vault_chart_period_flow')}
                        bars={[
                            {
                                key: 'in-usd',
                                label: `${t('inflow')} ${usd}`,
                                value: flowUsd.in,
                                currency: usd,
                                color: 'bg-emerald-500',
                            },
                            {
                                key: 'out-usd',
                                label: `${t('outflow')} ${usd}`,
                                value: flowUsd.out,
                                currency: usd,
                                color: 'bg-rose-500',
                            },
                            {
                                key: 'in-iqd',
                                label: `${t('inflow')} ${iqd}`,
                                value: flowIqd.in,
                                currency: iqd,
                                color: 'bg-teal-500',
                            },
                            {
                                key: 'out-iqd',
                                label: `${t('outflow')} ${iqd}`,
                                value: flowIqd.out,
                                currency: iqd,
                                color: 'bg-rose-400',
                            },
                        ]}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <DataPanel
                        title={t('vault_cash_flow_usd')}
                        subtitle={t('vault_cash_flow_hint')}
                    >
                        <CashFlowChart series={cashFlow} currency="USD" t={t} />
                    </DataPanel>
                    <DataPanel
                        title={t('vault_cash_flow_iqd')}
                        subtitle={t('vault_cash_flow_hint')}
                    >
                        <CashFlowChart series={cashFlow} currency="IQD" t={t} />
                    </DataPanel>
                </div>
            </PageShell>
        </AuthenticatedLayout>
    );
}
