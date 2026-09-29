import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
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

function Shortcut({ href, label }) {
    return (
        <Link href={href}>
            <SecondaryButton type="button">{label}</SecondaryButton>
        </Link>
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
};

function HomeModuleBox({ href, icon, tone, title, hint }) {
    const style = HOME_BOX_STYLES[tone] || HOME_BOX_STYLES.projects;

    return (
        <Link
            href={href}
            className={
                'group flex min-h-[7.5rem] flex-col justify-between rounded-2xl p-4 shadow-lg transition hover:-translate-y-0.5 ' +
                style.shell
            }
        >
            <span
                className={
                    'inline-flex h-11 w-11 items-center justify-center rounded-xl text-lg ' + style.icon
                }
            >
                <NavIcon name={icon} className="text-lg" />
            </span>
            <span>
                <span className="block text-base font-semibold tracking-tight">{title}</span>
                <span className="mt-0.5 block text-xs font-medium opacity-85">{hint}</span>
            </span>
        </Link>
    );
}

function DualBarChart({ title, subtitle, bars, ariaLabel }) {
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
                                    <MoneyAmount
                                        value={bar.value}
                                        label={bar.currency}
                                        size="sm"
                                        showLabel={false}
                                    />{' '}
                                    <span className="text-xs text-slate-400">{bar.currency}</span>
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

function QuietRoleHome({ summary, t }) {
    const usd = t('USD');
    const iqd = t('IQD');
    const charts = summary?.charts || {};
    const available = charts.available || {};
    const spendUsd = charts.spend_usd || {};
    const spendIqd = charts.spend_iqd || {};
    const lockedFree = charts.locked_free || {};
    const unclassified = summary?.unclassified_people ?? 0;

    const modules = [
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
                        {t('home_modules_hint')}
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

function AccountantHome({ summary, t, iqd }) {
    const txns = summary?.recent_transactions || [];

    return (
        <div className="space-y-6">
            <DataPanel
                title={t('role_home_accountant_title')}
                subtitle={t('role_home_accountant_hint')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Shortcut href={route('settlements.index')} label={t('settlements')} />
                        <Shortcut href={route('expenses.index')} label={t('expenses')} />
                        <Shortcut href={route('dashboards.payroll')} label={t('payroll_summary')} />
                        <Shortcut href={route('payouts.index')} label={t('payouts')} />
                    </div>
                }
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <MoneyStat
                        label={t('role_stat_available_payment')}
                        value={summary?.available_payment_iqd}
                        iqd={iqd}
                        accent
                    />
                    <MoneyStat label={t('money_received')} value={summary?.money_received_iqd} iqd={iqd} />
                    <Stat
                        label={t('role_stat_payroll_due')}
                        value={summary?.payroll_due_count ?? 0}
                    />
                    <MoneyStat
                        label={t('role_stat_payroll_due_money')}
                        value={summary?.payroll_due_iqd}
                        iqd={iqd}
                    />
                    <Stat label={t('role_stat_pending_calcs')} value={summary?.pending_calculations ?? 0} />
                    <MoneyStat label={t('role_stat_advances')} value={summary?.advances_open_iqd} iqd={iqd} />
                    <MoneyStat
                        label={t('role_stat_penalties')}
                        value={summary?.penalties_pending_iqd}
                        iqd={iqd}
                    />
                    <MoneyStat label={t('insurance_reserve')} value={summary?.insurance_held_iqd} iqd={iqd} />
                    <MoneyStat
                        label={t('role_stat_pending_expenses')}
                        value={summary?.pending_expenses_iqd}
                        iqd={iqd}
                    />
                    <Stat
                        label={t('role_stat_matured_holds')}
                        value={summary?.insurance_matured_count ?? 0}
                    />
                </div>
            </DataPanel>

            <DataPanel title={t('role_panel_recent_txns')} padded={false}>
                <DataTable minWidth="36rem" caption={t('role_panel_recent_txns')}>
                    <thead>
                        <tr>
                            <Th>{t('date')}</Th>
                            <Th>{t('type')}</Th>
                            <Th>{t('project')}</Th>
                            <Th align="end">{t('amount_iqd')}</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {txns.map((row) => (
                            <tr key={row.id}>
                                <Td muted>{row.occurred_on || '—'}</Td>
                                <Td>{t(`txn_type_${row.type}`, row.type)}</Td>
                                <Td muted>{row.project_name || '—'}</Td>
                                <Td align="end">
                                    <MoneyAmount
                                        value={row.amount_iqd}
                                        label={iqd}
                                        size="sm"
                                        showLabel={false}
                                    />
                                </Td>
                            </tr>
                        ))}
                        {!txns.length && (
                            <tr>
                                <Td colSpan={4} muted className="py-8 text-center">
                                    {t('role_empty_transactions')}
                                </Td>
                            </tr>
                        )}
                    </tbody>
                </DataTable>
            </DataPanel>
        </div>
    );
}

function StockManagerHome({ summary, t, iqd }) {
    const movements = summary?.recent_movements || [];
    const categories = summary?.by_category || [];
    const canIn = useCan('stock.stockIn');
    const canOut = useCan('stock.stockOut');

    return (
        <div className="space-y-6">
            <DataPanel
                title={t('role_home_stock_title')}
                subtitle={t('role_home_stock_hint')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        {canIn && (
                            <Link href={route('stock.in.create')}>
                                <PrimaryButton type="button">{t('stock_in')}</PrimaryButton>
                            </Link>
                        )}
                        {canOut && (
                            <Link href={route('stock.out.create')}>
                                <PrimaryButton type="button">{t('stock_out_action')}</PrimaryButton>
                            </Link>
                        )}
                        <Shortcut href={route('stock.items.index')} label={t('stock_products')} />
                        <Shortcut href={route('stock.movements.index')} label={t('stock_movements')} />
                    </div>
                }
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat label={t('stock_total_items')} value={summary?.total_items ?? 0} />
                    <MoneyStat label={t('stock_value_iqd')} value={summary?.stock_value_iqd ?? 0} iqd={iqd} />
                    <Stat label={t('stock_low')} value={summary?.low_stock ?? 0} />
                    <Stat label={t('stock_out')} value={summary?.out_of_stock ?? 0} />
                    <Stat label={t('stock_today_in')} value={summary?.today_in_qty ?? 0} />
                    <Stat label={t('stock_today_out')} value={summary?.today_out_qty ?? 0} />
                </div>
            </DataPanel>

            <div className="grid gap-6 lg:grid-cols-2">
                <DataPanel title={t('role_panel_stock_by_category')} padded={false}>
                    <DataTable minWidth="24rem" caption={t('role_panel_stock_by_category')}>
                        <thead>
                            <tr>
                                <Th>{t('category')}</Th>
                                <Th align="end">{t('role_stat_items')}</Th>
                                <Th align="end">{t('stock_value_iqd')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.map((c) => (
                                <tr key={c.category}>
                                    <Td>
                                        {c.category === 'uncategorized'
                                            ? t('uncategorized')
                                            : c.category}
                                    </Td>
                                    <Td align="end">{c.items_count}</Td>
                                    <Td align="end">
                                        <MoneyAmount
                                            value={c.value_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                        />
                                    </Td>
                                </tr>
                            ))}
                            {!categories.length && (
                                <tr>
                                    <Td colSpan={3} muted className="py-8 text-center">
                                        {t('no_stock_movements')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </DataTable>
                </DataPanel>

                <DataPanel title={t('stock_recent_movements')} padded={false}>
                    <DataTable minWidth="28rem" caption={t('stock_recent_movements')}>
                        <thead>
                            <tr>
                                <Th>{t('type')}</Th>
                                <Th>{t('product')}</Th>
                                <Th align="end">{t('quantity')}</Th>
                                <Th>{t('date')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.map((m) => (
                                <tr key={m.id}>
                                    <Td className="uppercase">{m.type}</Td>
                                    <Td>{m.item?.name || '—'}</Td>
                                    <Td align="end">{m.quantity}</Td>
                                    <Td muted>{m.moved_on || '—'}</Td>
                                </tr>
                            ))}
                            {!movements.length && (
                                <tr>
                                    <Td colSpan={4} muted className="py-8 text-center">
                                        {t('no_stock_movements')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </DataTable>
                </DataPanel>
            </div>
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
                {role === 'Accountant' && (
                    <AccountantHome summary={summary} t={t} iqd={iqd} />
                )}
                {role === 'Stock Manager' && (
                    <StockManagerHome summary={summary} t={t} iqd={iqd} />
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
