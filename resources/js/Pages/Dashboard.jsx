import DashboardQuickLink from '@/Components/DashboardQuickLink';
import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import DangerButton from '@/Components/DangerButton';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ShieldCheck, Users } from 'lucide-react';

function Stat({ label, value, hint }) {
    return (
        <div className="rounded-lg border border-slate-200/80 bg-white/90 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
            <div className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className="mt-1 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white sm:text-3xl"
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

function formatWhen(iso) {
    if (!iso) return '—';
    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function SuperAdminHome({ summary, t, canImportMayorca }) {
    const logins = summary?.last_logins || [];
    const activity = summary?.recent_activity || [];
    const health = summary?.health || {};
    const backup = summary?.backup;
    const iqd = t('IQD');
    const usd = t('USD');
    const importForm = useForm({ confirm_wipe: false });

    const runMayorcaImport = () => {
        if (
            !window.confirm(
                t('mayorca_import_confirm'),
            )
        ) {
            return;
        }
        importForm.transform((data) => ({ ...data, confirm_wipe: true }));
        importForm.post(route('admin.mayorca-import'), {
            preserveScroll: true,
            onFinish: () => importForm.setData('confirm_wipe', false),
        });
    };

    return (
        <div className="space-y-6">
            <DataPanel
                title={t('role_home_admin_title')}
                subtitle={t('role_home_admin_hint')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Shortcut href={route('dashboards.vault')} label={t('open_zhako_vault')} />
                        <Shortcut href={route('settlements.index')} label={t('settlements')} />
                    </div>
                }
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <MoneyStat
                        label={t('balance_iqd')}
                        value={summary?.vault_balance_iqd}
                        iqd={iqd}
                        accent
                    />
                    <MoneyStat
                        label={t('balance_usd')}
                        value={summary?.vault_balance_usd}
                        iqd={usd}
                    />
                    <MoneyStat
                        label={t('role_stat_available')}
                        value={summary?.available_iqd}
                        iqd={iqd}
                        accent
                    />
                    <MoneyStat
                        label={t('role_stat_available_usd')}
                        value={summary?.available_usd}
                        iqd={usd}
                    />
                    <MoneyStat
                        label={t('money_received')}
                        value={summary?.money_received_iqd}
                        iqd={iqd}
                    />
                    <MoneyStat
                        label={t('insurance_reserve')}
                        value={summary?.reserved_insurance_iqd}
                        iqd={iqd}
                    />
                    <Stat
                        label={t('role_stat_pending_payouts')}
                        value={summary?.pending_payouts ?? health.pending_payouts ?? 0}
                    />
                    <Stat
                        label={t('role_stat_matured_holds')}
                        value={summary?.matured_holds ?? health.matured_holds ?? 0}
                    />
                </div>
            </DataPanel>

            <DataPanel title={t('role_admin_quick_work')} subtitle={t('role_admin_quick_work_hint')}>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <DashboardQuickLink
                        href={route('workers.index')}
                        label={t('people')}
                        icon="workers"
                        tone="teal"
                    />
                    <DashboardQuickLink
                        href={route('vault.index')}
                        label={t('vault')}
                        icon="vault"
                        tone="emerald"
                    />
                    <DashboardQuickLink
                        href={route('spatial.index')}
                        label={t('spatial_grid')}
                        icon="spatial"
                        tone="slate"
                    />
                    <DashboardQuickLink
                        href={route('attendance.index')}
                        label={t('attendance')}
                        icon="attendance"
                        tone="slate"
                    />
                    <DashboardQuickLink
                        href={route('client-advances.index')}
                        label={t('client_advances')}
                        icon="clientAdvances"
                        tone="emerald"
                    />
                    <DashboardQuickLink
                        href={route('users.index')}
                        label={t('users')}
                        icon="users"
                        tone="slate"
                    />
                    <DashboardQuickLink
                        href={route('audit.index')}
                        label={t('audit')}
                        icon="audit"
                        tone="slate"
                    />
                    <DashboardQuickLink
                        href={route('backups.index')}
                        label={t('backups')}
                        icon="backups"
                        tone="slate"
                    />
                </div>
            </DataPanel>

            {canImportMayorca && (
                <DataPanel
                    title={t('mayorca_import_panel_title')}
                    subtitle={t('mayorca_import_panel_hint')}
                >
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                            <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                            <p>{t('mayorca_import_panel_body')}</p>
                        </div>
                        <DangerButton
                            type="button"
                            disabled={importForm.processing || !summary?.workbook_bundled}
                            onClick={runMayorcaImport}
                        >
                            {importForm.processing
                                ? t('mayorca_import_running')
                                : t('mayorca_import_button')}
                        </DangerButton>
                    </div>
                    {!summary?.workbook_bundled && (
                        <p className="mt-3 text-sm text-rose-700 dark:text-rose-300">
                            {t('mayorca_workbook_missing')}
                        </p>
                    )}
                </DataPanel>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                <DataPanel title={t('role_panel_last_logins')} padded={false}>
                    <DataTable minWidth="28rem" caption={t('role_panel_last_logins')}>
                        <thead>
                            <tr>
                                <Th>{t('full_name')}</Th>
                                <Th>{t('last_login')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {logins.map((u) => (
                                <tr key={u.id}>
                                    <Td>
                                        <div className="flex items-center gap-2">
                                            <Users className="h-4 w-4 text-slate-400" aria-hidden />
                                            <div>
                                                <div className="font-medium">{u.name}</div>
                                                <div className="text-xs text-slate-500">{u.email}</div>
                                            </div>
                                        </div>
                                    </Td>
                                    <Td muted>{formatWhen(u.last_login_at)}</Td>
                                </tr>
                            ))}
                            {!logins.length && (
                                <tr>
                                    <Td colSpan={2} muted className="py-8 text-center">
                                        {t('role_empty_logins')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </DataTable>
                </DataPanel>

                <DataPanel title={t('role_panel_recent_activity')} padded={false}>
                    <DataTable minWidth="28rem" caption={t('role_panel_recent_activity')}>
                        <thead>
                            <tr>
                                <Th>{t('audit')}</Th>
                                <Th>{t('date')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {activity.map((a) => (
                                <tr key={a.id}>
                                    <Td>
                                        <div className="font-medium">{a.event || a.description}</div>
                                        <div className="text-xs text-slate-500">
                                            {a.causer_name || '—'}
                                        </div>
                                    </Td>
                                    <Td muted>{formatWhen(a.created_at)}</Td>
                                </tr>
                            ))}
                            {!activity.length && (
                                <tr>
                                    <Td colSpan={2} muted className="py-8 text-center">
                                        {t('role_empty_activity')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </DataTable>
                </DataPanel>
            </div>

            <DataPanel
                title={t('role_panel_system_health')}
                subtitle={t('role_panel_system_health_hint')}
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-lg border border-slate-200/80 bg-white/90 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
                        <div className="text-xs font-medium uppercase tracking-wide text-slate-500">
                            {t('role_health_ledger')}
                        </div>
                        <div className="mt-2">
                            <StatusBadge
                                status={health.ledger_ok ? 'approved' : 'pending'}
                            />
                        </div>
                    </div>
                    <Stat label={t('role_stat_users_active')} value={summary?.users_active ?? 0} />
                    <Stat label={t('role_stat_audit_events')} value={summary?.audit_events ?? 0} />
                    <Stat
                        label={t('role_stat_backup')}
                        value={
                            backup
                                ? t(`status_${backup.status}`, backup.status)
                                : t('role_backup_none')
                        }
                        hint={backup?.finished_at ? formatWhen(backup.finished_at) : undefined}
                    />
                </div>
            </DataPanel>
        </div>
    );
}

function BossHome({ summary, t, iqd }) {
    const cards = summary?.project_cards || [];

    return (
        <div className="space-y-6">
            <DataPanel
                title={t('role_home_boss_title')}
                subtitle={t('role_home_boss_hint')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Shortcut href={route('dashboards.vault')} label={t('open_zhako_vault')} />
                        <Shortcut href={route('settlements.index')} label={t('settlements')} />
                        <Shortcut href={route('projects.index')} label={t('projects')} />
                        <Shortcut href={route('reports.index')} label={t('reports')} />
                    </div>
                }
            >
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <MoneyStat label={t('money_received')} value={summary?.money_received_iqd} iqd={iqd} accent />
                    <MoneyStat label={t('role_stat_money_spent')} value={summary?.money_spent_iqd} iqd={iqd} />
                    <MoneyStat label={t('role_stat_available')} value={summary?.available_iqd} iqd={iqd} accent />
                    <MoneyStat
                        label={t('insurance_reserve')}
                        value={summary?.reserved_insurance_iqd}
                        iqd={iqd}
                    />
                    <MoneyStat label={t('balance_iqd')} value={summary?.vault_balance_iqd} iqd={iqd} />
                    <MoneyStat label={t('payroll_cost')} value={summary?.payroll_totals_iqd} iqd={iqd} />
                    <MoneyStat label={t('role_stat_advances')} value={summary?.advances_iqd} iqd={iqd} />
                    <MoneyStat
                        label={t('material_cost')}
                        value={summary?.stock_material_spend_iqd}
                        iqd={iqd}
                    />
                </div>
            </DataPanel>

            <DataPanel
                title={t('role_panel_project_profit')}
                subtitle={t('role_panel_project_profit_hint')}
            >
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {cards.map((p) => (
                        <Link
                            key={p.id}
                            href={route('projects.show', p.id)}
                            className="block rounded-lg border border-slate-200/80 bg-slate-50/60 p-4 transition hover:border-emerald-400/60 dark:border-slate-700 dark:bg-slate-950/40 dark:hover:border-emerald-700/50"
                        >
                            <div className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {p.name}
                            </div>
                            <dl className="mt-3 space-y-1.5 text-sm">
                                <div className="flex items-center justify-between gap-3">
                                    <dt className="text-slate-500">{t('money_received')}</dt>
                                    <dd>
                                        <MoneyAmount
                                            value={p.money_received_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                        />
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <dt className="text-slate-500">{t('material_cost')}</dt>
                                    <dd>
                                        <MoneyAmount
                                            value={p.material_cost_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                        />
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <dt className="text-slate-500">{t('payroll_cost')}</dt>
                                    <dd>
                                        <MoneyAmount
                                            value={p.payroll_cost_iqd}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                        />
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-3 border-t border-slate-200/80 pt-2 dark:border-slate-700">
                                    <dt className="font-medium text-slate-700 dark:text-slate-200">
                                        {t('net_position')}
                                    </dt>
                                    <dd>
                                        <MoneyAmount
                                            value={p.net_position_iqd}
                                            label={iqd}
                                            size="md"
                                            showLabel={false}
                                            accent={Number(p.net_position_iqd) >= 0}
                                        />
                                    </dd>
                                </div>
                            </dl>
                        </Link>
                    ))}
                    {!cards.length && (
                        <p className="text-sm text-slate-500 dark:text-slate-400 sm:col-span-2">
                            {t('role_empty_projects')}
                        </p>
                    )}
                </div>
            </DataPanel>
        </div>
    );
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

export default function Dashboard({ maturedHolds, roleHome, summary, canImportMayorca }) {
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

                {role === 'Super Admin' && (
                    <SuperAdminHome
                        summary={summary}
                        t={t}
                        canImportMayorca={canImportMayorca}
                    />
                )}
                {role === 'Boss / Contractor' && (
                    <BossHome summary={summary} t={t} iqd={iqd} />
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
