import MoneyAmount from '@/Components/MoneyAmount';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router, usePage } from '@inertiajs/react';

function Stat({ label, value }) {
    return (
        <div className="border border-slate-200/80 bg-white/90 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
            <div className="text-sm font-medium uppercase tracking-wide text-slate-700 dark:text-slate-300">
                {label}
            </div>
            <div dir="ltr" className="mt-1 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                {value}
            </div>
        </div>
    );
}

function Shortcut({ href, label }) {
    return (
        <Link
            href={href}
            className="inline-flex items-center border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 transition hover:border-emerald-400/70 hover:text-emerald-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-emerald-600/50 dark:hover:text-emerald-300"
        >
            {label}
        </Link>
    );
}

function SuperAdminHome({ summary, t }) {
    return (
        <section className="space-y-4">
            <div>
                <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                    {t('role_home_admin_title')}
                </h3>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {t('role_home_admin_hint')}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Stat label={t('role_stat_users')} value={summary?.users ?? 0} />
                <Stat label={t('role_stat_projects')} value={summary?.projects ?? 0} />
                <Stat label={t('role_stat_workers')} value={summary?.workers ?? 0} />
                <Stat label={t('role_stat_pending_payouts')} value={summary?.pending_payouts ?? 0} />
                <Stat label={t('role_stat_matured_holds')} value={summary?.matured_holds ?? 0} />
                <Stat label={t('role_stat_audit_events')} value={summary?.audit_events ?? 0} />
            </div>
        </section>
    );
}

function BossHome({ summary, t, iqd }) {
    return (
        <section className="space-y-4">
            <div>
                <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                    {t('role_home_boss_title')}
                </h3>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {t('role_home_boss_hint')}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Stat
                    label={t('balance_iqd')}
                    value={
                        summary?.vault_balance_iqd == null
                            ? '—'
                            : <MoneyAmount value={summary.vault_balance_iqd} label={iqd} size="xl" />
                    }
                />
                <Stat
                    label={t('role_stat_available')}
                    value={
                        summary?.available_iqd == null
                            ? '—'
                            : <MoneyAmount value={summary.available_iqd} label={iqd} size="xl" />
                    }
                />
                <Stat
                    label={t('role_stat_pending_payouts_money')}
                    value={
                        summary?.pending_payouts_iqd == null
                            ? '—'
                            : <MoneyAmount value={summary.pending_payouts_iqd} label={iqd} size="xl" />
                    }
                />
                <Stat
                    label={t('insurance_reserve')}
                    value={
                        summary?.reserved_insurance_iqd == null
                            ? '—'
                            : <MoneyAmount value={summary.reserved_insurance_iqd} label={iqd} size="xl" />
                    }
                />
                <Stat label={t('role_stat_projects')} value={summary?.projects ?? 0} />
                <Stat label={t('role_stat_workers')} value={summary?.workers ?? 0} />
            </div>
            <div className="flex flex-wrap gap-2">
                <Shortcut href={route('dashboards.vault')} label={t('open_zhako_vault')} />
                <Shortcut href={route('dashboards.payroll')} label={t('payroll_summary')} />
                <Shortcut href={route('projects.index')} label={t('projects')} />
                <Shortcut href={route('exports.index')} label={t('exports')} />
            </div>
        </section>
    );
}

function AccountantHome({ summary, t }) {
    return (
        <section className="space-y-4">
            <div>
                <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                    {t('role_home_accountant_title')}
                </h3>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {t('role_home_accountant_hint')}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label={t('role_stat_pending_payouts')} value={summary?.pending_payouts ?? 0} />
                <Stat label={t('role_stat_matured_holds')} value={summary?.matured_holds ?? 0} />
                <Stat label={t('role_stat_projects')} value={summary?.projects ?? 0} />
                <Stat label={t('role_stat_workers')} value={summary?.workers ?? 0} />
            </div>
            <div className="flex flex-wrap gap-2">
                <Shortcut href={route('payouts.create')} label={t('role_shortcut_create_payout')} />
                <Shortcut href={route('payouts.index')} label={t('payouts')} />
                <Shortcut href={route('dashboards.vault')} label={t('open_zhako_vault')} />
                <Shortcut href={route('imports.index')} label={t('imports')} />
                <Shortcut href={route('exports.index')} label={t('exports')} />
                <Shortcut href={route('retention-holds.index')} label={t('insurance')} />
            </div>
        </section>
    );
}

function StockManagerHome({ summary, t, iqd }) {
    return (
        <section className="space-y-4">
            <div>
                <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                    {t('role_home_stock_title')}
                </h3>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {t('role_home_stock_hint')}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Stat label={t('stock_total_items')} value={summary?.total_items ?? 0} />
                <Stat
                    label={t('stock_value_iqd')}
                    value={<MoneyAmount value={summary?.stock_value_iqd ?? 0} label={iqd} size="xl" />}
                />
                <Stat label={t('stock_low')} value={summary?.low_stock ?? 0} />
                <Stat label={t('stock_out')} value={summary?.out_of_stock ?? 0} />
                <Stat label={t('stock_today_in')} value={summary?.today_in_qty ?? 0} />
                <Stat label={t('stock_today_out')} value={summary?.today_out_qty ?? 0} />
            </div>
            <div className="flex flex-wrap gap-2">
                <Shortcut href={route('stock.dashboard')} label={t('stock_dashboard')} />
                <Shortcut href={route('stock.items.index')} label={t('stock_products')} />
                <Shortcut href={route('stock.in.create')} label={t('stock_in')} />
                <Shortcut href={route('stock.out.create')} label={t('stock_out_action')} />
                <Shortcut href={route('stock.suppliers.index')} label={t('suppliers')} />
                <Shortcut href={route('stock.movements.index')} label={t('stock_movements')} />
            </div>
        </section>
    );
}

export default function Dashboard({ maturedHolds, roleHome, summary }) {
    const t = useTranslations();
    const page = usePage();
    const role = roleHome || page.props.auth?.role;
    const canVault = useCan('vault.view');
    const canPayroll = useCan('vault.payroll');
    const canRetention = useCan('vault.retention');
    const alerts = canRetention ? maturedHolds || [] : [];
    const iqd = t('IQD');

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-slate-900 dark:text-white">
                        {t('dashboard')}
                    </h2>
                    <p className="text-sm text-slate-500 dark:text-slate-400" dir="auto">
                        {t('product_tagline')}
                    </p>
                </div>
            }
        >
            <Head title={t('dashboard')} />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {alerts.length > 0 && (
                        <section className="border border-amber-300/80 bg-amber-50/90 p-5 dark:border-amber-700/60 dark:bg-amber-950/40">
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
                                            <div className="mt-0.5 tabular-nums text-slate-700 dark:text-slate-300">
                                                <MoneyAmount value={hold.amount_iqd ?? hold.amount_usd} label={iqd} size="sm" />
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

                    {role === 'Super Admin' && (
                        <SuperAdminHome summary={summary} t={t} />
                    )}
                    {role === 'Boss / Contractor' && (
                        <BossHome summary={summary} t={t} iqd={iqd} />
                    )}
                    {role === 'Accountant' && (
                        <AccountantHome summary={summary} t={t} />
                    )}
                    {role === 'Stock Manager' && (
                        <StockManagerHome summary={summary} t={t} iqd={iqd} />
                    )}

                    {role !== 'Stock Manager' && (
                        <section className="flex flex-col items-center justify-center gap-6 border border-slate-200/80 bg-white/80 px-6 py-10 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900/70 sm:py-12">
                            <div className="flex flex-wrap items-center justify-center gap-3">
                                {canVault && (
                                    <Link href={route('dashboards.vault')}>
                                        <PrimaryButton type="button">{t('open_zhako_vault')}</PrimaryButton>
                                    </Link>
                                )}
                                {canPayroll && (
                                    <Link href={route('dashboards.payroll')}>
                                        <PrimaryButton type="button">{t('payroll_summary')}</PrimaryButton>
                                    </Link>
                                )}
                            </div>
                            {canRetention && alerts.length === 0 && (
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    {t('no_matured_holds')}
                                </p>
                            )}
                        </section>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
