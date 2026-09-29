import DataPanel from '@/Components/DataPanel';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router, usePage } from '@inertiajs/react';

function Stat({ label, value, hint }) {
    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className="mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white sm:text-3xl"
            >
                {value}
            </div>
            {hint && (
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            )}
        </div>
    );
}

function MoneyStat({ label, value, iqd, accent = false }) {
    return (
        <Stat
            label={label}
            value={
                value == null ? (
                    '—'
                ) : (
                    <MoneyAmount value={value} label={iqd} size="xl" showLabel={false} accent={accent} />
                )
            }
            hint={iqd}
        />
    );
}

const HOME_BOX_STYLES = {
    vault: {
        shell: 'bg-teal-600 text-white shadow-teal-900/20 hover:bg-teal-500 dark:bg-teal-500 dark:text-slate-950 dark:hover:bg-teal-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    clients: {
        shell: 'bg-emerald-600 text-white shadow-emerald-900/20 hover:bg-emerald-500 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    projects: {
        shell: 'bg-slate-700 text-white shadow-slate-900/25 hover:bg-slate-600 dark:bg-slate-600 dark:hover:bg-slate-500',
        icon: 'bg-white/15 text-white',
    },
    staff: {
        shell: 'bg-amber-500 text-amber-950 shadow-amber-900/20 hover:bg-amber-400 dark:bg-amber-400 dark:hover:bg-amber-300',
        icon: 'bg-amber-950/15 text-amber-950',
    },
    salary: {
        shell: 'bg-indigo-600 text-white shadow-indigo-900/20 hover:bg-indigo-500 dark:bg-indigo-500 dark:text-slate-950 dark:hover:bg-indigo-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    expenses: {
        shell: 'bg-rose-600 text-white shadow-rose-900/20 hover:bg-rose-500 dark:bg-rose-500 dark:text-slate-950 dark:hover:bg-rose-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    settlements: {
        shell: 'bg-sky-600 text-white shadow-sky-900/20 hover:bg-sky-500 dark:bg-sky-500 dark:text-slate-950 dark:hover:bg-sky-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    stock: {
        shell: 'bg-orange-600 text-white shadow-orange-900/20 hover:bg-orange-500 dark:bg-orange-500 dark:text-slate-950 dark:hover:bg-orange-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    attendance: {
        shell: 'bg-violet-600 text-white shadow-violet-900/20 hover:bg-violet-500 dark:bg-violet-500 dark:text-slate-950 dark:hover:bg-violet-400',
        icon: 'bg-white/20 text-white dark:bg-slate-950/15 dark:text-slate-950',
    },
    reports: {
        shell: 'bg-cyan-700 text-white shadow-cyan-900/25 hover:bg-cyan-600 dark:bg-cyan-600 dark:hover:bg-cyan-500',
        icon: 'bg-white/15 text-white',
    },
};

function HomeModuleBox({
    href,
    icon,
    tone,
    title,
    hint,
    primaryLabel,
    secondaryHref,
    secondaryLabel,
}) {
    const style = HOME_BOX_STYLES[tone] || HOME_BOX_STYLES.projects;
    const shell =
        'group flex min-h-[7.5rem] flex-col justify-between rounded-2xl p-4 shadow-lg transition hover:-translate-y-0.5 ' +
        style.shell;
    const iconShell =
        'inline-flex h-11 w-11 items-center justify-center rounded-xl text-lg ' + style.icon;
    const chip =
        'rounded-lg bg-black/15 px-2.5 py-1 text-xs font-semibold underline-offset-2 hover:bg-black/25 hover:underline';

    if (secondaryHref && secondaryLabel) {
        return (
            <div className={shell}>
                <span className={iconShell}>
                    <NavIcon name={icon} className="text-lg" />
                </span>
                <span>
                    <span className="block text-base font-semibold tracking-tight">{title}</span>
                    {hint && (
                        <span className="mt-0.5 block text-xs font-medium opacity-85">{hint}</span>
                    )}
                    <span className="mt-2 flex flex-wrap gap-2">
                        <Link href={href} className={chip}>
                            {primaryLabel || title}
                        </Link>
                        <Link href={secondaryHref} className={chip}>
                            {secondaryLabel}
                        </Link>
                    </span>
                </span>
            </div>
        );
    }

    return (
        <Link href={href} className={shell}>
            <span className={iconShell}>
                <NavIcon name={icon} className="text-lg" />
            </span>
            <span>
                <span className="block text-base font-semibold tracking-tight">{title}</span>
                <span className="mt-0.5 block text-xs font-medium opacity-85">{hint}</span>
            </span>
        </Link>
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
                                <span dir="ltr" className="font-sans tabular-nums text-slate-600 dark:text-slate-300">
                                    {format === 'money' ? (
                                        <>
                                            <MoneyAmount
                                                value={bar.value}
                                                label={bar.currency}
                                                size="sm"
                                                showLabel={false}
                                            />{' '}
                                            <span className="text-xs text-slate-400">{bar.currency}</span>
                                        </>
                                    ) : (
                                        <span>
                                            {Number(bar.value || 0).toLocaleString()}
                                            {bar.unit ? (
                                                <span className="ms-1 text-xs text-slate-400">{bar.unit}</span>
                                            ) : null}
                                        </span>
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

function QuietRoleHome({ summary, t, modules: modulesOverride, modulesHint }) {
    const usd = t('USD');
    const iqd = t('IQD');
    const charts = summary?.charts || {};
    const available = charts.available || {};
    const spendUsd = charts.spend_usd || {};
    const spendIqd = charts.spend_iqd || {};
    const lockedFree = charts.locked_free || {};
    const unclassified = summary?.unclassified_people ?? 0;

    const modules = modulesOverride || [
        {
            key: 'vault',
            href: route('vault.index'),
            icon: 'vault',
            tone: 'vault',
            title: t('home_box_vault'),
            hint: t('home_box_vault_hint'),
        },
        {
            key: 'clients',
            href: route('client-advances.index'),
            icon: 'clientAdvances',
            tone: 'clients',
            title: t('home_box_clients'),
            hint: t('home_box_clients_hint'),
        },
        {
            key: 'projects',
            href: route('projects.index'),
            icon: 'projects',
            tone: 'projects',
            title: t('home_box_projects'),
            hint: t('home_box_projects_hint'),
        },
        {
            key: 'staff',
            href: route('workers.index', { labor_kind: 'staff' }),
            icon: 'workers',
            tone: 'staff',
            title: t('home_box_staff'),
            hint: t('home_box_staff_hint'),
        },
        {
            key: 'salary',
            href: route('dashboards.payroll'),
            icon: 'payroll',
            tone: 'salary',
            title: t('home_box_salary'),
            hint: t('home_box_salary_hint'),
        },
        {
            key: 'expenses',
            href: route('expenses.index'),
            icon: 'expenses',
            tone: 'expenses',
            title: t('home_box_expenses'),
            hint: t('home_box_expenses_hint'),
        },
    ];

    return (
        <div className="space-y-6">
            {unclassified > 0 && (
                <Link
                    href={route('workers.index', { labor_kind: 'unclassified' })}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300/80 bg-amber-50 px-4 py-3.5 text-amber-950 transition hover:border-amber-400 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <span className="flex items-center gap-3 text-sm font-semibold">
                        <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/20">
                            <NavIcon name="workers" className="text-base" />
                        </span>
                        {t('home_unclassified_stripe')}
                        <span
                            dir="ltr"
                            className="rounded-lg bg-amber-500 px-2 py-0.5 font-sans text-xs font-bold text-white tabular-nums"
                        >
                            {unclassified}
                        </span>
                    </span>
                    <span className="text-sm font-medium underline underline-offset-2">
                        {t('home_unclassified_cta')}
                    </span>
                </Link>
            )}

            <div className="grid gap-3 sm:grid-cols-2">
                <MoneyStat
                    label={`${t('available_cash')} ${usd}`}
                    value={summary?.available_usd}
                    iqd={usd}
                    accent
                />
                <MoneyStat
                    label={`${t('available_cash')} ${iqd}`}
                    value={summary?.available_iqd}
                    iqd={iqd}
                    accent
                />
            </div>

            <section>
                <div className="mb-3">
                    <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                        {t('home_modules')}
                    </h2>
                    <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {modulesHint || t('home_modules_hint')}
                    </p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {modules.map((mod) => (
                        <HomeModuleBox key={mod.key} {...mod} />
                    ))}
                </div>
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                <DualBarChart
                    title={t('home_chart_available')}
                    subtitle={t('home_chart_available_hint')}
                    ariaLabel={t('home_chart_available')}
                    bars={[
                        {
                            key: 'usd',
                            label: usd,
                            value: available.usd ?? 0,
                            currency: usd,
                            color: 'bg-teal-500',
                        },
                        {
                            key: 'iqd',
                            label: iqd,
                            value: available.iqd ?? 0,
                            currency: iqd,
                            color: 'bg-emerald-500',
                        },
                    ]}
                />
                <DualBarChart
                    title={t('home_chart_locked')}
                    subtitle={t('home_chart_locked_hint')}
                    ariaLabel={t('home_chart_locked')}
                    bars={[
                        {
                            key: 'locked-usd',
                            label: `${t('home_locked')} ${usd}`,
                            value: lockedFree.usd?.locked ?? 0,
                            currency: usd,
                            color: 'bg-amber-500',
                        },
                        {
                            key: 'free-usd',
                            label: `${t('home_free')} ${usd}`,
                            value: lockedFree.usd?.free ?? 0,
                            currency: usd,
                            color: 'bg-teal-500',
                        },
                        {
                            key: 'locked-iqd',
                            label: `${t('home_locked')} ${iqd}`,
                            value: lockedFree.iqd?.locked ?? 0,
                            currency: iqd,
                            color: 'bg-amber-400',
                        },
                        {
                            key: 'free-iqd',
                            label: `${t('home_free')} ${iqd}`,
                            value: lockedFree.iqd?.free ?? 0,
                            currency: iqd,
                            color: 'bg-emerald-500',
                        },
                    ]}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <DualBarChart
                    title={t('home_chart_spend_usd')}
                    subtitle={t('home_chart_spend_hint')}
                    ariaLabel={t('home_chart_spend_usd')}
                    bars={[
                        {
                            key: 'exp-usd',
                            label: t('home_spend_expenses'),
                            value: spendUsd.expenses ?? 0,
                            currency: usd,
                            color: 'bg-rose-500',
                        },
                        {
                            key: 'staff-usd',
                            label: t('home_spend_staff'),
                            value: spendUsd.staff ?? 0,
                            currency: usd,
                            color: 'bg-amber-500',
                        },
                        {
                            key: 'sal-usd',
                            label: t('home_spend_salary'),
                            value: spendUsd.salary ?? 0,
                            currency: usd,
                            color: 'bg-indigo-500',
                        },
                    ]}
                />
                <DualBarChart
                    title={t('home_chart_spend_iqd')}
                    subtitle={t('home_chart_spend_hint')}
                    ariaLabel={t('home_chart_spend_iqd')}
                    bars={[
                        {
                            key: 'exp-iqd',
                            label: t('home_spend_expenses'),
                            value: spendIqd.expenses ?? 0,
                            currency: iqd,
                            color: 'bg-rose-500',
                        },
                        {
                            key: 'staff-iqd',
                            label: t('home_spend_staff'),
                            value: spendIqd.staff ?? 0,
                            currency: iqd,
                            color: 'bg-amber-500',
                        },
                        {
                            key: 'sal-iqd',
                            label: t('home_spend_salary'),
                            value: spendIqd.salary ?? 0,
                            currency: iqd,
                            color: 'bg-indigo-500',
                        },
                    ]}
                />
            </div>
        </div>
    );
}

function SuperAdminHome({ summary, t }) {
    return <QuietRoleHome summary={summary} t={t} />;
}

function BossHome({ summary, t }) {
    return <QuietRoleHome summary={summary} t={t} />;
}

function accountantModules(t) {
    return [
        {
            key: 'vault',
            href: route('vault.index'),
            secondaryHref: route('dashboards.vault'),
            primaryLabel: t('home_dest_ledger'),
            secondaryLabel: t('home_dest_qasa'),
            icon: 'vault',
            tone: 'vault',
            title: t('home_box_vault'),
            hint: t('home_box_vault_hint'),
        },
        {
            key: 'expenses',
            href: route('expenses.index'),
            secondaryHref: route('expenses.create'),
            primaryLabel: t('home_dest_expenses'),
            secondaryLabel: t('home_dest_expense_create'),
            icon: 'expenses',
            tone: 'expenses',
            title: t('home_box_expenses'),
            hint: t('home_box_acct_expenses_hint'),
        },
        {
            key: 'clients',
            href: route('client-advances.index'),
            secondaryHref: route('client-advances.create'),
            primaryLabel: t('home_dest_client_advances'),
            secondaryLabel: t('home_dest_client_advance_create'),
            icon: 'clientAdvances',
            tone: 'clients',
            title: t('home_box_client_advances'),
            hint: t('home_box_client_advances_hint'),
        },
        {
            key: 'staff-pay',
            href: route('advances.index'),
            secondaryHref: route('workers.index', { labor_kind: 'staff' }),
            primaryLabel: t('home_dest_staff_advances'),
            secondaryLabel: t('home_dest_staff'),
            icon: 'advances',
            tone: 'staff',
            title: t('home_box_staff_pay'),
            hint: t('home_box_staff_pay_hint'),
        },
        {
            key: 'salary',
            href: route('dashboards.payroll'),
            secondaryHref: route('workers.index', { labor_kind: 'worker' }),
            primaryLabel: t('home_dest_payroll'),
            secondaryLabel: t('home_dest_workers'),
            icon: 'payroll',
            tone: 'salary',
            title: t('home_box_salary'),
            hint: t('home_box_acct_salary_hint'),
        },
        {
            key: 'settlements',
            href: route('settlements.index'),
            secondaryHref: route('payouts.index'),
            primaryLabel: t('home_dest_settlements'),
            secondaryLabel: t('home_dest_payouts'),
            icon: 'settlements',
            tone: 'settlements',
            title: t('home_box_settlements'),
            hint: t('home_box_settlements_hint'),
        },
    ];
}

function AccountantHome({ summary, t }) {
    return (
        <QuietRoleHome
            summary={summary}
            t={t}
            modules={accountantModules(t)}
            modulesHint={t('home_modules_accountant_hint')}
        />
    );
}

function stockManagerModules(t) {
    return [
        {
            key: 'products',
            href: route('stock.items.index'),
            secondaryHref: route('stock.dashboard'),
            primaryLabel: t('home_dest_products'),
            secondaryLabel: t('home_dest_stock_dashboard'),
            icon: 'stock',
            tone: 'stock',
            title: t('home_box_stock_products'),
            hint: t('home_box_stock_products_hint'),
        },
        {
            key: 'stock-in',
            href: route('stock.in.create'),
            icon: 'stockIn',
            tone: 'clients',
            title: t('home_box_stock_in'),
            hint: t('home_box_stock_in_hint'),
        },
        {
            key: 'stock-out',
            href: route('stock.out.create'),
            icon: 'stockOut',
            tone: 'expenses',
            title: t('home_box_stock_out'),
            hint: t('home_box_stock_out_hint'),
        },
        {
            key: 'movements',
            href: route('stock.movements.index'),
            icon: 'stockMovements',
            tone: 'projects',
            title: t('home_box_stock_movements'),
            hint: t('home_box_stock_movements_hint'),
        },
        {
            key: 'attendance',
            href: route('attendance.index'),
            icon: 'attendance',
            tone: 'attendance',
            title: t('home_box_attendance'),
            hint: t('home_box_attendance_hint'),
        },
        {
            key: 'reports',
            href: route('reports.index'),
            icon: 'reports',
            tone: 'reports',
            title: t('home_box_stock_reports'),
            hint: t('home_box_stock_reports_hint'),
        },
    ];
}

function StockManagerHome({ summary, t, iqd }) {
    const charts = summary?.charts || {};
    const todayFlow = charts.today_flow || {};
    const health = charts.health || {};
    const categoryBars = charts.value_by_category || [];

    return (
        <div className="space-y-6">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label={t('stock_total_items')} value={summary?.total_items ?? 0} />
                <MoneyStat
                    label={t('stock_value_iqd')}
                    value={summary?.stock_value_iqd ?? 0}
                    iqd={iqd}
                    accent
                />
                <Stat label={t('stock_low')} value={summary?.low_stock ?? 0} />
                <Stat label={t('stock_out')} value={summary?.out_of_stock ?? 0} />
            </div>

            <section>
                <div className="mb-3">
                    <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                        {t('home_modules')}
                    </h2>
                    <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {t('home_modules_stock_hint')}
                    </p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {stockManagerModules(t).map((mod) => (
                        <HomeModuleBox key={mod.key} {...mod} />
                    ))}
                </div>
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                <DualBarChart
                    title={t('home_chart_today_flow')}
                    subtitle={t('home_chart_today_flow_hint')}
                    ariaLabel={t('home_chart_today_flow')}
                    format="number"
                    bars={[
                        {
                            key: 'in',
                            label: t('home_stock_flow_in'),
                            value: todayFlow.in ?? 0,
                            color: 'bg-emerald-500',
                        },
                        {
                            key: 'out',
                            label: t('home_stock_flow_out'),
                            value: todayFlow.out ?? 0,
                            color: 'bg-rose-500',
                        },
                    ]}
                />
                <DualBarChart
                    title={t('home_chart_stock_health')}
                    subtitle={t('home_chart_stock_health_hint')}
                    ariaLabel={t('home_chart_stock_health')}
                    format="number"
                    bars={[
                        {
                            key: 'ok',
                            label: t('home_stock_ok'),
                            value: health.ok ?? 0,
                            color: 'bg-teal-500',
                        },
                        {
                            key: 'low',
                            label: t('stock_low'),
                            value: health.low ?? 0,
                            color: 'bg-amber-500',
                        },
                        {
                            key: 'out',
                            label: t('stock_out'),
                            value: health.out ?? 0,
                            color: 'bg-rose-500',
                        },
                    ]}
                />
            </div>

            <DualBarChart
                title={t('home_chart_stock_value')}
                subtitle={t('home_chart_stock_value_hint')}
                ariaLabel={t('home_chart_stock_value')}
                format="money"
                bars={
                    categoryBars.length
                        ? categoryBars.map((c, idx) => ({
                              key: c.key || `cat-${idx}`,
                              label:
                                  c.label === 'uncategorized'
                                      ? t('uncategorized')
                                      : c.label,
                              value: c.value ?? 0,
                              currency: iqd,
                              color:
                                  [
                                      'bg-orange-500',
                                      'bg-teal-500',
                                      'bg-amber-500',
                                      'bg-slate-500',
                                      'bg-rose-500',
                                      'bg-indigo-500',
                                  ][idx % 6],
                          }))
                        : [
                              {
                                  key: 'empty',
                                  label: t('no_stock_movements'),
                                  value: 0,
                                  currency: iqd,
                                  color: 'bg-slate-300',
                              },
                          ]
                }
            />
        </div>
    );
}

export default function Dashboard({ maturedHolds, roleHome, summary }) {
    const t = useTranslations();
    const page = usePage();
    const role = roleHome || page.props.auth?.role;
    const canRetention = useCan('vault.retention');
    const alerts = canRetention ? maturedHolds || [] : [];
    const iqd = t('IQD');

    const subtitle =
        role === 'Super Admin'
            ? t('role_home_admin_hint')
            : role === 'Boss / Contractor'
              ? t('role_home_boss_hint')
              : role === 'Accountant'
                ? t('role_home_accountant_hint')
                : role === 'Stock Manager'
                  ? t('role_home_stock_hint')
                  : t('product_tagline');

    return (
        <AuthenticatedLayout
            header={<PageHeader title={t('dashboard')} subtitle={subtitle} />}
        >
            <Head title={t('dashboard')} />

            <PageShell className="!space-y-8">
                {alerts.length > 0 && (
                    <section className="rounded-lg border border-amber-300/80 bg-amber-50/90 p-5 dark:border-amber-700/60 dark:bg-amber-950/40">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 className="font-display text-lg font-semibold text-amber-950 dark:text-amber-100">
                                    {t('insurance_ready')}
                                </h3>
                                <p className="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                                    {t('insurance_ready_hint', { count: alerts.length })}
                                </p>
                            </div>
                            <Link
                                href={route('retention-holds.index')}
                                className="text-sm font-medium text-amber-900 underline dark:text-amber-200"
                            >
                                {t('view_all_holds')}
                            </Link>
                        </div>
                        <ul className="mt-4 divide-y divide-amber-200/80 dark:divide-amber-800/60">
                            {alerts.map((hold) => (
                                <li
                                    key={hold.id}
                                    className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"
                                >
                                    <div>
                                        <span className="font-medium text-slate-900 dark:text-slate-100">
                                            {hold.worker?.name || `${t('worker')} #${hold.worker_id}`}
                                        </span>
                                        <span className="text-slate-500">
                                            {' '}
                                            · {hold.project?.name}
                                        </span>
                                        <div className="mt-0.5 text-slate-700 dark:text-slate-300">
                                            <MoneyAmount
                                                value={hold.amount_iqd ?? hold.amount_usd}
                                                label={iqd}
                                                size="sm"
                                            />
                                            {' · '}
                                            {t('matured_on', { date: hold.maturity_date })}
                                        </div>
                                    </div>
                                    <PrimaryButton
                                        type="button"
                                        onClick={() =>
                                            router.post(route('retention-holds.release', hold.id))
                                        }
                                    >
                                        {t('release_to_payroll')}
                                    </PrimaryButton>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {role === 'Super Admin' && <SuperAdminHome summary={summary} t={t} />}
                {role === 'Boss / Contractor' && (
                    <BossHome summary={summary} t={t} />
                )}
                {role === 'Accountant' && <AccountantHome summary={summary} t={t} />}
                {role === 'Stock Manager' && (
                    <StockManagerHome summary={summary} t={t} iqd={iqd} />
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
