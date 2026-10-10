import MoneyAmount from '@/Components/MoneyAmount';
import useTranslations from '@/hooks/useTranslations';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ArrowDownRight,
    ArrowUpRight,
    Boxes,
    Lock,
    Receipt,
    Wallet,
} from 'lucide-react';

const DONUT_COLORS = ['#F59E0B', '#34D399', '#38BDF8'];

function formatCompact(value) {
    const n = Number(value) || 0;
    if (Math.abs(n) >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (Math.abs(n) >= 1_000) return `${(n / 1_000).toFixed(1)}K`;
    return n.toLocaleString();
}

function CurrencyPill({ code }) {
    return (
        <span className="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-300">
            {code}
        </span>
    );
}

function KpiCard({ icon: Icon, label, children, accent = 'teal' }) {
    const glow =
        accent === 'amber'
            ? 'from-amber-500/15 via-transparent to-transparent'
            : accent === 'rose'
              ? 'from-rose-500/15 via-transparent to-transparent'
              : accent === 'sky'
                ? 'from-sky-500/15 via-transparent to-transparent'
                : 'from-emerald-500/20 via-transparent to-transparent';

    return (
        <div className="relative overflow-hidden rounded-2xl border border-white/10 bg-[#111827]/90 p-4 shadow-[0_0_40px_-20px_rgba(16,185,129,0.45)] backdrop-blur-md">
            <div className={`pointer-events-none absolute inset-0 bg-gradient-to-br ${glow}`} />
            <div className="relative flex items-start gap-3">
                <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-emerald-300">
                    <Icon className="h-5 w-5" strokeWidth={1.75} />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold tracking-wide text-slate-400">{label}</p>
                    <div className="mt-2">{children}</div>
                </div>
            </div>
        </div>
    );
}

function ChartTooltip({ active, payload, label, t }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="rounded-xl border border-white/10 bg-[#0B0F19]/95 px-3 py-2 text-xs text-slate-200 shadow-xl">
            <p className="mb-1 font-semibold text-slate-300">{label}</p>
            {payload.map((entry) => (
                <p key={entry.dataKey} style={{ color: entry.color }}>
                    {entry.name}: {Number(entry.value || 0).toLocaleString()}
                </p>
            ))}
        </div>
    );
}

export default function FinanceDesk({ finance }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const data = finance || {};
    const available = data.available || {};
    const locked = data.locked || {};
    const expenses = data.expenses || {};
    const inventory = data.inventory || {};
    const cashflow = data.cashflow || [];
    const donut = data.expense_donut || {};
    const activity = data.activity || [];
    const trend = available.trend_pct;
    const trendUp = Number(trend) >= 0;

    const donutData = [
        { key: 'staff', name: t('home_finance_slice_staff'), value: Number(donut.staff) || 0 },
        { key: 'materials', name: t('home_finance_slice_materials'), value: Number(donut.materials) || 0 },
        { key: 'salary', name: t('home_finance_slice_salary'), value: Number(donut.salary) || 0 },
    ].filter((row) => row.value > 0);

    const titleFor = (row) => {
        const map = {
            advance: t('vault_form_advance'),
            expense: t('expenses'),
            salary: t('vault_form_salary'),
            staff_pay: t('vault_form_job_pay'),
        };
        return map[row.title_key] || row.title_key || '—';
    };

    const statusLabel = (status) =>
        status === 'held' ? t('home_finance_status_held') : t('home_finance_status_posted');

    return (
        <div
            dir="rtl"
            className="space-y-5 rounded-3xl border border-white/5 bg-[#0B0F19] p-4 text-slate-100 sm:p-6"
        >
            <div>
                <h2 className="font-display text-xl font-semibold tracking-tight text-white">
                    {t('home_finance_title')}
                </h2>
                <p className="mt-1 text-sm text-slate-400">{t('home_finance_hint')}</p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard icon={Wallet} label={t('home_finance_kpi_cash')} accent="teal">
                    <p dir="ltr" className="font-sans text-2xl font-semibold tabular-nums text-white">
                        <MoneyAmount
                            value={available.usd ?? 0}
                            label={usd}
                            size="xl"
                            showLabel={false}
                            className="text-white"
                        />
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <CurrencyPill code={usd} />
                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-200">
                            {iqd} {(available.iqd ?? 0).toLocaleString()}
                        </span>
                        {trend != null ? (
                            <span
                                className={
                                    'inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ' +
                                    (trendUp
                                        ? 'bg-emerald-500/15 text-emerald-300'
                                        : 'bg-rose-500/15 text-rose-300')
                                }
                            >
                                {trendUp ? (
                                    <ArrowUpRight className="h-3.5 w-3.5" />
                                ) : (
                                    <ArrowDownRight className="h-3.5 w-3.5" />
                                )}
                                {Math.abs(Number(trend)).toFixed(1)}%
                            </span>
                        ) : null}
                    </div>
                </KpiCard>

                <KpiCard icon={Lock} label={t('home_finance_kpi_locked')} accent="amber">
                    <p dir="ltr" className="font-sans text-2xl font-semibold tabular-nums text-white">
                        {formatCompact(locked.total_usd ?? 0)}{' '}
                        <span className="text-sm font-medium text-slate-400">{usd}</span>
                    </p>
                    <div className="mt-2 space-y-1 text-xs text-slate-300">
                        <p>
                            {t('home_finance_locked_staff')}:{' '}
                            <span dir="ltr">
                                {(locked.staff_usd ?? 0).toLocaleString()} {usd} ·{' '}
                                {(locked.staff_iqd ?? 0).toLocaleString()} {iqd}
                            </span>
                        </p>
                        <p>
                            {t('home_finance_locked_insurance')}:{' '}
                            <span dir="ltr">
                                {(locked.insurance_usd ?? 0).toLocaleString()} {usd} ·{' '}
                                {(locked.insurance_iqd ?? 0).toLocaleString()} {iqd}
                            </span>
                        </p>
                    </div>
                </KpiCard>

                <KpiCard icon={Receipt} label={t('home_finance_kpi_expenses')} accent="rose">
                    <p dir="ltr" className="font-sans text-2xl font-semibold tabular-nums text-white">
                        {formatCompact(expenses.usd ?? 0)}{' '}
                        <span className="text-sm font-medium text-slate-400">{usd}</span>
                    </p>
                    <div className="mt-2 flex flex-wrap gap-1.5 text-[11px] text-slate-300">
                        <span className="rounded-full bg-white/5 px-2 py-0.5">
                            {t('home_finance_slice_staff')} {(expenses.staff_usd ?? 0).toLocaleString()}
                        </span>
                        <span className="rounded-full bg-white/5 px-2 py-0.5">
                            {t('home_finance_slice_materials')}{' '}
                            {(expenses.materials_usd ?? 0).toLocaleString()}
                        </span>
                        <span className="rounded-full bg-white/5 px-2 py-0.5">
                            {t('home_finance_slice_salary')} {(expenses.salary_usd ?? 0).toLocaleString()}
                        </span>
                    </div>
                    <p className="mt-2 text-xs text-slate-400" dir="ltr">
                        {iqd} {(expenses.iqd ?? 0).toLocaleString()}
                    </p>
                </KpiCard>

                <KpiCard icon={Boxes} label={t('home_finance_kpi_inventory')} accent="sky">
                    <p dir="ltr" className="font-sans text-2xl font-semibold tabular-nums text-white">
                        {formatCompact(inventory.usd ?? 0)}{' '}
                        <span className="text-sm font-medium text-slate-400">{usd}</span>
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <span className="rounded-full border border-sky-400/30 bg-sky-400/10 px-2 py-0.5 text-[11px] font-semibold text-sky-200">
                            {iqd} {(inventory.iqd ?? 0).toLocaleString()}
                        </span>
                        <span className="text-xs text-slate-400">
                            {t('stock_total_items')}: {inventory.items ?? 0}
                        </span>
                    </div>
                </KpiCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-5">
                <div className="rounded-2xl border border-white/10 bg-[#111827]/90 p-4 xl:col-span-3">
                    <div className="mb-3 flex items-end justify-between gap-2">
                        <div>
                            <h3 className="text-sm font-semibold text-white">
                                {t('home_finance_cashflow_title')}
                            </h3>
                            <p className="text-xs text-slate-400">{t('home_finance_cashflow_hint')}</p>
                        </div>
                    </div>
                    <div className="h-64 w-full" dir="ltr">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={cashflow} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="advFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#34D399" stopOpacity={0.45} />
                                        <stop offset="100%" stopColor="#34D399" stopOpacity={0} />
                                    </linearGradient>
                                    <linearGradient id="expFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#FB7185" stopOpacity={0.4} />
                                        <stop offset="100%" stopColor="#FB7185" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid stroke="rgba(148,163,184,0.12)" vertical={false} />
                                <XAxis
                                    dataKey="label"
                                    tick={{ fill: '#94A3B8', fontSize: 11 }}
                                    axisLine={false}
                                    tickLine={false}
                                    minTickGap={24}
                                />
                                <YAxis
                                    tick={{ fill: '#94A3B8', fontSize: 11 }}
                                    axisLine={false}
                                    tickLine={false}
                                    width={48}
                                    tickFormatter={formatCompact}
                                />
                                <Tooltip content={<ChartTooltip t={t} />} />
                                <Area
                                    type="monotone"
                                    dataKey="advances"
                                    name={t('home_finance_series_advances')}
                                    stroke="#34D399"
                                    fill="url(#advFill)"
                                    strokeWidth={2}
                                />
                                <Area
                                    type="monotone"
                                    dataKey="expenses"
                                    name={t('home_finance_series_expenses')}
                                    stroke="#FB7185"
                                    fill="url(#expFill)"
                                    strokeWidth={2}
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="rounded-2xl border border-white/10 bg-[#111827]/90 p-4 xl:col-span-2">
                    <div className="mb-3">
                        <h3 className="text-sm font-semibold text-white">
                            {t('home_finance_donut_title')}
                        </h3>
                        <p className="text-xs text-slate-400">{t('home_finance_donut_hint')}</p>
                    </div>
                    <div className="h-52 w-full" dir="ltr">
                        {donutData.length ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={donutData}
                                        dataKey="value"
                                        nameKey="name"
                                        innerRadius={58}
                                        outerRadius={84}
                                        paddingAngle={3}
                                        stroke="none"
                                    >
                                        {donutData.map((entry, index) => (
                                            <Cell
                                                key={entry.key}
                                                fill={DONUT_COLORS[index % DONUT_COLORS.length]}
                                            />
                                        ))}
                                    </Pie>
                                    <Tooltip
                                        formatter={(value, name) => [
                                            Number(value || 0).toLocaleString(),
                                            name,
                                        ]}
                                        contentStyle={{
                                            background: '#0B0F19',
                                            border: '1px solid rgba(255,255,255,0.1)',
                                            borderRadius: 12,
                                            color: '#E2E8F0',
                                        }}
                                    />
                                </PieChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="flex h-full items-center justify-center text-sm text-slate-500">
                                {t('home_finance_donut_empty')}
                            </div>
                        )}
                    </div>
                    <ul className="mt-2 space-y-1.5 text-xs text-slate-300">
                        {donutData.map((row, idx) => (
                            <li key={row.key} className="flex items-center justify-between gap-2">
                                <span className="inline-flex items-center gap-2">
                                    <span
                                        className="h-2.5 w-2.5 rounded-full"
                                        style={{ background: DONUT_COLORS[idx % DONUT_COLORS.length] }}
                                    />
                                    {row.name}
                                </span>
                                <span dir="ltr" className="font-sans tabular-nums text-slate-200">
                                    {row.value.toLocaleString()}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>

            <div className="rounded-2xl border border-white/10 bg-[#111827]/90">
                <div className="border-b border-white/10 px-4 py-3">
                    <h3 className="text-sm font-semibold text-white">{t('home_finance_activity_title')}</h3>
                    <p className="text-xs text-slate-400">{t('home_finance_activity_hint')}</p>
                </div>
                {activity.length === 0 ? (
                    <p className="px-4 py-8 text-sm text-slate-500">{t('home_finance_activity_empty')}</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-wide text-slate-500">
                                    <th className="px-4 py-2.5 text-start font-semibold">{t('date')}</th>
                                    <th className="px-4 py-2.5 text-start font-semibold">
                                        {t('home_finance_activity_title_col')}
                                    </th>
                                    <th className="px-4 py-2.5 text-start font-semibold">{t('project')}</th>
                                    <th className="px-4 py-2.5 text-start font-semibold">{t('amount')}</th>
                                    <th className="px-4 py-2.5 text-start font-semibold">{t('status')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {activity.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-t border-white/5 text-slate-200 hover:bg-white/[0.03]"
                                    >
                                        <td className="px-4 py-2.5 font-sans tabular-nums text-slate-400" dir="ltr">
                                            {row.date || '—'}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <p className="font-medium text-white">{titleFor(row)}</p>
                                            {row.recipient ? (
                                                <p className="text-xs text-slate-400">{row.recipient}</p>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-300">
                                            {row.project || '—'}
                                            {row.apartment ? (
                                                <span className="text-slate-500"> · {row.apartment}</span>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-2.5" dir="ltr">
                                            <span className="inline-flex items-center gap-1.5 font-sans font-semibold tabular-nums">
                                                {(row.amount ?? 0).toLocaleString()}
                                                <CurrencyPill code={row.currency === 'USD' ? usd : iqd} />
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span
                                                className={
                                                    'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ' +
                                                    (row.status === 'held'
                                                        ? 'bg-amber-500/15 text-amber-200'
                                                        : 'bg-emerald-500/15 text-emerald-200')
                                                }
                                            >
                                                {statusLabel(row.status)}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
