import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { Lock, Receipt, Users, Wallet } from 'lucide-react';
import { useState } from 'react';

function Shell({ children, className = '' }) {
    return (
        <div
            className={
                'rounded-2xl border border-white/10 bg-[#111827]/90 shadow-[0_0_40px_-24px_rgba(16,185,129,0.35)] backdrop-blur-md ' +
                className
            }
        >
            {children}
        </div>
    );
}

function SectionTitle({ title, subtitle, right = null, className = '' }) {
    return (
        <div className={'flex flex-wrap items-end justify-between gap-2 ' + className}>
            <div>
                <h2 className="text-sm font-semibold text-white">{title}</h2>
                {subtitle ? <p className="mt-0.5 text-xs text-slate-400">{subtitle}</p> : null}
            </div>
            {right}
        </div>
    );
}

function ActionLink({ href, icon: Icon, title, hint, tone = 'teal' }) {
    const tones = {
        teal: 'hover:border-emerald-400/40 hover:bg-emerald-500/10',
        rose: 'hover:border-rose-400/40 hover:bg-rose-500/10',
        amber: 'hover:border-amber-400/40 hover:bg-amber-500/10',
    };
    const icons = {
        teal: 'bg-emerald-500/15 text-emerald-300',
        rose: 'bg-rose-500/15 text-rose-300',
        amber: 'bg-amber-500/15 text-amber-300',
    };

    return (
        <Link
            href={href}
            className={
                'flex min-h-[4.25rem] items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] p-3 transition ' +
                (tones[tone] || tones.teal)
            }
        >
            <span
                className={
                    'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ' +
                    (icons[tone] || icons.teal)
                }
            >
                <Icon className="h-5 w-5" strokeWidth={1.75} />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-white">{title}</span>
                <span className="mt-0.5 block text-xs text-slate-400">{hint}</span>
            </span>
        </Link>
    );
}

function HoldTable({
    rows,
    emptyTitle,
    emptyHint,
    showStaff,
    t,
    usd,
    iqd,
    canConfirmHold = false,
    confirmingId = null,
    onConfirmHold,
    canManageAdvance = false,
}) {
    if (!rows?.length) {
        return (
            <div className="px-4 py-10 text-center">
                <p className="text-sm font-medium text-slate-200">{emptyTitle}</p>
                <p className="mt-1 text-xs text-slate-500">{emptyHint}</p>
            </div>
        );
    }

    const showActions = canManageAdvance || (showStaff && canConfirmHold);

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="border-b border-white/10 text-xs uppercase tracking-wide text-slate-500">
                        {showStaff ? (
                            <th className="px-4 py-2.5 text-start font-semibold">{t('staff')}</th>
                        ) : null}
                        <th className="px-4 py-2.5 text-start font-semibold">{t('amount')}</th>
                        <th className="px-4 py-2.5 text-start font-semibold">{t('currency')}</th>
                        <th className="px-4 py-2.5 text-start font-semibold">{t('vault_unlock_date')}</th>
                        <th className="px-4 py-2.5 text-start font-semibold">{t('date')}</th>
                        {showActions ? (
                            <th className="px-4 py-2.5 text-start font-semibold">{t('actions')}</th>
                        ) : null}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-t border-white/5 hover:bg-white/[0.03]">
                            {showStaff ? (
                                <td className="px-4 py-2.5 font-medium text-white">
                                    {row.staff_name || '—'}
                                    {row.due ? (
                                        <span className="ms-2 inline-flex rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-semibold text-amber-200">
                                            {t('vault_hold_due_now')}
                                        </span>
                                    ) : null}
                                </td>
                            ) : null}
                            <td className="px-4 py-2.5 font-sans font-semibold tabular-nums text-slate-100" dir="ltr">
                                <MoneyAmount
                                    value={row.amount}
                                    label={row.currency}
                                    size="sm"
                                    showLabel={false}
                                    className="text-slate-100"
                                />
                            </td>
                            <td className="px-4 py-2.5">
                                <span className="inline-flex rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-300">
                                    {row.currency === 'USD' ? usd : iqd}
                                </span>
                            </td>
                            <td
                                className="px-4 py-2.5 font-sans tabular-nums text-slate-300"
                                dir="ltr"
                            >
                                {row.unlock_date || '—'}
                            </td>
                            <td
                                className="px-4 py-2.5 font-sans tabular-nums text-slate-500"
                                dir="ltr"
                            >
                                {row.occurred_on || '—'}
                            </td>
                            {showActions ? (
                                <td className="px-4 py-2.5">
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {canManageAdvance ? (
                                            <>
                                                <Link
                                                    href={route('vault.lines.advance.edit', row.id)}
                                                    className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-semibold text-slate-200 transition hover:bg-white/10"
                                                >
                                                    {t('edit')}
                                                </Link>
                                                <button
                                                    type="button"
                                                    className="rounded-lg border border-rose-400/20 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                                    onClick={() => {
                                                        if (confirm(t('confirm_delete'))) {
                                                            router.delete(
                                                                route(
                                                                    'vault.lines.advance.destroy',
                                                                    row.id,
                                                                ),
                                                            );
                                                        }
                                                    }}
                                                >
                                                    {t('delete')}
                                                </button>
                                            </>
                                        ) : null}
                                        {showStaff && canConfirmHold ? (
                                            <button
                                                type="button"
                                                disabled={confirmingId === row.id}
                                                onClick={() => onConfirmHold?.(row.id)}
                                                className="rounded-lg border border-amber-400/25 bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-100 transition hover:bg-amber-500/25 disabled:opacity-50"
                                            >
                                                {t('job_pay_hold_confirm')}
                                            </button>
                                        ) : null}
                                    </div>
                                </td>
                            ) : null}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function EstimateCard({ estimate, currency, t }) {
    const covers = Boolean(estimate?.covers);
    const shortfall = Number(estimate?.shortfall || 0);
    const headroom = Math.max(
        0,
        Number(estimate?.available_cash || 0) - Number(estimate?.obligations || 0),
    );

    const rows = [
        { key: 'cash', label: t('available_cash'), value: estimate?.available_cash ?? 0 },
        { key: 'salaries', label: t('vault_salaries_to_pay'), value: estimate?.salaries_to_pay ?? 0 },
        { key: 'expenses', label: t('vault_expenses_to_pay'), value: estimate?.expenses_to_pay ?? 0 },
        {
            key: 'result',
            label: covers ? t('vault_headroom') : t('shortfall'),
            value: covers ? headroom : shortfall,
            strong: true,
            ok: covers,
        },
    ];

    return (
        <Shell
            className={
                'p-4 ' +
                (covers
                    ? 'border-emerald-400/20'
                    : 'border-rose-400/25')
            }
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {currency}
                    </p>
                    <p className="mt-1 text-sm font-semibold text-white">
                        {covers ? t('vault_estimate_covers') : t('vault_estimate_short')}
                    </p>
                </div>
                <span
                    className={
                        'inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ' +
                        (covers
                            ? 'bg-emerald-500/20 text-emerald-200'
                            : 'bg-rose-500/20 text-rose-200')
                    }
                >
                    {covers ? t('vault_estimate_ok') : t('shortfall')}
                </span>
            </div>
            <dl className="mt-4 space-y-2">
                {rows.map((row) => (
                    <div
                        key={row.key}
                        className="flex items-baseline justify-between gap-3 border-b border-white/5 pb-2 last:border-0 last:pb-0"
                    >
                        <dt className="text-xs text-slate-400">{row.label}</dt>
                        <dd
                            dir="ltr"
                            className={
                                'font-sans text-sm font-semibold tabular-nums ' +
                                (row.strong
                                    ? row.ok
                                        ? 'text-emerald-300'
                                        : 'text-rose-300'
                                    : 'text-slate-100')
                            }
                        >
                            <MoneyAmount
                                value={row.value}
                                label={currency}
                                size="sm"
                                showLabel={false}
                                className="!text-inherit"
                            />{' '}
                            <span className="text-[11px] font-medium text-slate-500">{currency}</span>
                        </dd>
                    </div>
                ))}
            </dl>
        </Shell>
    );
}

export default function Vault({
    available_cash: availableCash,
    insurance_unlocks: insuranceUnlocks,
    staff_holds: staffHolds,
    estimates,
    as_of: asOf,
}) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const canManage = useCan('vault.ledgerManage');
    const cash = availableCash || { USD: 0, IQD: 0 };
    const unlocks = insuranceUnlocks || [];
    const holds = staffHolds || [];
    const est = estimates || {};
    const [confirmingId, setConfirmingId] = useState(null);

    const confirmHold = (id) => {
        if (!canManage || confirmingId) return;
        setConfirmingId(id);
        router.post(
            route('vault.job-pay.confirm-hold', id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setConfirmingId(null),
            },
        );
    };

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('vault')}
                    subtitle={t('vault_simple_subtitle')}
                    icon={<NavIcon name="vault" className="text-lg text-emerald-300" />}
                />
            }
        >
            <Head title={t('vault')} />

            <PageShell className="!max-w-6xl !space-y-5">
                <div
                    dir="rtl"
                    className="space-y-5 rounded-3xl border border-white/5 bg-[#0B0F19] p-4 text-slate-100 sm:p-6"
                >
                    {/* Available cash — hero */}
                    <Shell className="relative overflow-hidden p-5">
                        <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-emerald-500/15 via-transparent to-transparent" />
                        <div className="relative">
                            <div className="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <p className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-emerald-300/90">
                                        <Wallet className="h-3.5 w-3.5" />
                                        {t('available_cash')}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-400">
                                        {t('vault_available_cash_hint')}
                                    </p>
                                </div>
                                {asOf ? (
                                    <p dir="ltr" className="text-xs tabular-nums text-slate-500">
                                        {asOf}
                                    </p>
                                ) : null}
                            </div>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                    <p className="text-xs font-semibold text-slate-400">{usd}</p>
                                    <p
                                        dir="ltr"
                                        className="mt-1 font-sans text-3xl font-semibold tabular-nums text-emerald-300 sm:text-4xl"
                                    >
                                        <MoneyAmount
                                            value={cash.USD}
                                            label={usd}
                                            size="xl"
                                            showLabel={false}
                                            className="text-emerald-300"
                                        />
                                    </p>
                                </div>
                                <div className="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                                    <p className="text-xs font-semibold text-slate-400">{iqd}</p>
                                    <p
                                        dir="ltr"
                                        className="mt-1 font-sans text-3xl font-semibold tabular-nums text-emerald-200 sm:text-4xl"
                                    >
                                        <MoneyAmount
                                            value={cash.IQD}
                                            label={iqd}
                                            size="xl"
                                            showLabel={false}
                                            className="text-emerald-200"
                                        />
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Shell>

                    {/* Actions */}
                    {canManage ? (
                        <section>
                            <SectionTitle
                                className="mb-3"
                                title={t('vault_money_forms')}
                                subtitle={t('vault_money_forms_hint')}
                                right={
                                    <Link
                                        href={route('staff.create')}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-400/30 bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-500/25"
                                    >
                                        <Users className="h-3.5 w-3.5" />
                                        {t('vault_form_add_staff')}
                                    </Link>
                                }
                            />
                            <div className="grid gap-3 sm:grid-cols-3">
                                <ActionLink
                                    href={route('vault.lines.advance.create')}
                                    icon={Wallet}
                                    title={t('vault_form_advance')}
                                    hint={t('vault_form_advance_short')}
                                    tone="teal"
                                />
                                <ActionLink
                                    href={route('expenses.create')}
                                    icon={Receipt}
                                    title={t('vault_form_expense')}
                                    hint={t('vault_form_expense_short')}
                                    tone="rose"
                                />
                                <ActionLink
                                    href={route('vault.lines.job-pay.create')}
                                    icon={Users}
                                    title={t('vault_form_job_pay')}
                                    hint={t('vault_form_job_pay_short')}
                                    tone="amber"
                                />
                            </div>
                        </section>
                    ) : null}

                    {/* Insurance unlocks */}
                    <Shell>
                        <div className="border-b border-white/10 px-4 py-3.5">
                            <SectionTitle
                                title={t('vault_insurance_unlocks')}
                                subtitle={t('vault_insurance_unlocks_hint')}
                            />
                        </div>
                        <HoldTable
                            rows={unlocks}
                            emptyTitle={t('vault_insurance_empty')}
                            emptyHint={t('vault_insurance_empty_hint')}
                            showStaff={false}
                            t={t}
                            usd={usd}
                            iqd={iqd}
                            canManageAdvance={canManage}
                        />
                    </Shell>

                    {/* Staff holds */}
                    <Shell>
                        <div className="border-b border-white/10 px-4 py-3.5">
                            <SectionTitle
                                title={
                                    <span className="inline-flex items-center gap-2">
                                        <Lock className="h-3.5 w-3.5 text-amber-300" />
                                        {t('vault_staff_holds')}
                                    </span>
                                }
                                subtitle={t('vault_staff_holds_hint')}
                            />
                        </div>
                        <HoldTable
                            rows={holds}
                            emptyTitle={t('vault_staff_holds_empty')}
                            emptyHint={t('vault_staff_holds_empty_hint')}
                            showStaff
                            t={t}
                            usd={usd}
                            iqd={iqd}
                            canConfirmHold={canManage}
                            confirmingId={confirmingId}
                            onConfirmHold={confirmHold}
                        />
                    </Shell>

                    {/* Estimate */}
                    <section>
                        <SectionTitle
                            className="mb-3"
                            title={t('vault_estimate_title')}
                            subtitle={t('vault_estimate_hint')}
                        />
                        <div className="grid gap-3 lg:grid-cols-2">
                            <EstimateCard estimate={est.USD} currency={usd} t={t} />
                            <EstimateCard estimate={est.IQD} currency={iqd} t={t} />
                        </div>
                    </section>
                </div>
            </PageShell>
        </AuthenticatedLayout>
    );
}
