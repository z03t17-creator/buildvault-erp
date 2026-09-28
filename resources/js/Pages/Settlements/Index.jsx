import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const LINE_META = {
    money_received: { tone: 'in' },
    available_vault_balance: { tone: 'balance' },
    project_expenses: { tone: 'out' },
    payroll: { tone: 'out' },
    employee_advances: { tone: 'out' },
    insurance: { tone: 'muted' },
    penalties: { tone: 'muted' },
    other_expenses: { tone: 'out' },
    approved_payments: { tone: 'out' },
    available_money_for_payment: { tone: 'result' },
};

export default function Index({ settlement, canSave, filters }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const page = usePage();
    const flash = page.props.flash || {};
    const errors = page.props.errors || {};

    const s = settlement || {};
    const ability = s.ability || {};
    const lines = (s.lines || []).filter((line) => line.key !== 'available_money_for_payment');

    const form = useForm({
        month: filters?.month || s.year_month,
        project_id: filters?.project_id || '',
        requested_payout_iqd: filters?.requested_payout_iqd || '',
    });

    const applyFilters = (next = {}) => {
        const month = next.month ?? form.data.month;
        const projectId =
            next.project_id !== undefined ? next.project_id : form.data.project_id;
        const requested =
            next.requested_payout_iqd !== undefined
                ? next.requested_payout_iqd
                : form.data.requested_payout_iqd;

        router.get(
            route('settlements.index'),
            {
                month,
                ...(projectId ? { project_id: projectId } : {}),
                ...(requested !== '' && requested != null
                    ? { requested_payout_iqd: requested }
                    : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    const save = (e) => {
        e.preventDefault();
        form.post(route('settlements.store'), { preserveScroll: true });
    };

    const blocked = ability.requested_payout_iqd > 0 && !ability.allowed;
    const availableForPayment = s.available_money_for_payment_iqd ?? 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('monthly_settlement')}
                    subtitle={t('monthly_settlement_subtitle')}
                    actions={
                        <Link href={route('vault.transactions')}>
                            <SecondaryButton type="button">{t('vault_ledger')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('monthly_settlement')} />

            <PageShell className="!space-y-8" narrow>
                    {flash.success && (
                        <p className="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {flash.success}
                        </p>
                    )}

                    {/* Filters — one clean row */}
                    <section className="flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1 text-sm text-slate-600 dark:text-slate-300">
                            <span>{t('month')}</span>
                            <input
                                type="month"
                                className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                                value={form.data.month}
                                onChange={(e) => {
                                    form.setData('month', e.target.value);
                                    applyFilters({ month: e.target.value });
                                }}
                            />
                        </label>
                        <label className="flex min-w-[12rem] flex-1 flex-col gap-1 text-sm text-slate-600 dark:text-slate-300">
                            <span>{t('project')}</span>
                            <select
                                className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                                value={form.data.project_id || ''}
                                onChange={(e) => {
                                    form.setData('project_id', e.target.value);
                                    applyFilters({ project_id: e.target.value });
                                }}
                            >
                                <option value="">{t('all_projects')}</option>
                                {(s.projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <p className="ms-auto text-xs text-slate-500 tabular-nums">
                            {s.from} → {s.to}
                        </p>
                    </section>

                    {/* Hero result */}
                    <section
                        className={`bv-card rounded-sm border px-5 py-6 sm:px-8 ${
                            availableForPayment > 0
                                ? 'border-emerald-300/80 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30'
                                : 'border-amber-300/80 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-950/30'
                        }`}
                    >
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 dark:text-slate-300">
                            {t('available_money_for_payment')}
                        </p>
                        <div className="mt-2">
                            <MoneyAmount
                                value={availableForPayment}
                                label={iqd}
                                size="hero"
                                accent={availableForPayment > 0}
                            />
                        </div>
                        <p className="mt-3 max-w-xl text-sm text-slate-600 dark:text-slate-400">
                            {t('available_money_for_payment_hint')}
                        </p>
                        <dl className="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-500">
                            <div className="inline-flex items-baseline gap-2">
                                <dt>{t('current_balance')}</dt>
                                <dd>
                                    <MoneyAmount value={s.current_vault_iqd} label={iqd} size="sm" />
                                </dd>
                            </div>
                            <div className="inline-flex items-baseline gap-2">
                                <dt>{t('pending_commitments')}</dt>
                                <dd>
                                    <MoneyAmount
                                        value={s.pending_commitments_iqd}
                                        label={iqd}
                                        size="sm"
                                    />
                                </dd>
                            </div>
                            <div className="inline-flex items-baseline gap-2">
                                <dt>{t('reserved_balance')}</dt>
                                <dd>
                                    <MoneyAmount
                                        value={s.reserved_insurance_iqd}
                                        label={iqd}
                                        size="sm"
                                    />
                                </dd>
                            </div>
                        </dl>
                    </section>

                    {/* Month lines — sparse list, not a dense table dump */}
                    <section className="space-y-1">
                        <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            {t('settlement_month_activity')}
                        </h3>
                        <ul className="divide-y divide-slate-200/80 border-y border-slate-200/80 dark:divide-slate-800 dark:border-slate-800">
                            {lines.map((line) => {
                                const meta = LINE_META[line.key] || { tone: 'muted' };
                                const amountClass =
                                    meta.tone === 'in'
                                        ? 'text-emerald-700 dark:text-emerald-400'
                                        : meta.tone === 'out'
                                          ? 'text-rose-700 dark:text-rose-400'
                                          : 'text-slate-900 dark:text-white';

                                return (
                                    <li
                                        key={line.key}
                                        className="flex items-baseline justify-between gap-4 py-3.5"
                                    >
                                        <span className="text-sm text-slate-700 dark:text-slate-200">
                                            {t(`settlement_${line.key}`)}
                                        </span>
                                        <span className={`shrink-0 ${amountClass}`}>
                                            <MoneyAmount
                                                value={line.amount_iqd}
                                                label={iqd}
                                                size="md"
                                                className={amountClass}
                                            />
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    {/* Ability-to-pay check */}
                    <section className="space-y-3">
                        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                            {t('ability_to_pay_check')}
                        </h3>
                        <div className="flex flex-wrap items-end gap-3">
                            <label className="flex min-w-[14rem] flex-col gap-1 text-sm text-slate-600 dark:text-slate-300">
                                <span>{t('requested_payout_iqd')}</span>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                                    value={form.data.requested_payout_iqd}
                                    onChange={(e) => form.setData('requested_payout_iqd', e.target.value)}
                                    onBlur={() =>
                                        applyFilters({
                                            requested_payout_iqd: form.data.requested_payout_iqd,
                                        })
                                    }
                                    placeholder="0"
                                />
                            </label>
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    applyFilters({
                                        requested_payout_iqd: form.data.requested_payout_iqd,
                                    })
                                }
                            >
                                {t('check_ability')}
                            </SecondaryButton>
                        </div>

                        {blocked && (
                            <div
                                role="alert"
                                className="border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100"
                            >
                                <p className="font-semibold">{t('payout_blocked')}</p>
                                {(ability.reasons || []).map((reason) => (
                                    <p key={reason} className="mt-1">
                                        {reason}
                                    </p>
                                ))}
                                {ability.shortfall_iqd > 0 && (
                                    <p className="mt-2">
                                        {t('shortfall')}:{' '}
                                        <MoneyAmount
                                            value={ability.shortfall_iqd}
                                            label={iqd}
                                            size="sm"
                                            className="text-rose-800 dark:text-rose-200"
                                        />
                                    </p>
                                )}
                            </div>
                        )}

                        {!blocked && ability.requested_payout_iqd > 0 && (
                            <p className="border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100">
                                {t('payout_allowed')}
                            </p>
                        )}

                        {(errors.requested_payout_iqd || errors.month) && (
                            <p className="text-sm text-rose-600 dark:text-rose-400">
                                {errors.requested_payout_iqd || errors.month}
                            </p>
                        )}
                    </section>

                    {/* Snapshot save */}
                    {canSave && (
                        <form onSubmit={save} className="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
                            <PrimaryButton type="submit" disabled={form.processing || blocked}>
                                {s.snapshot ? t('update_settlement_snapshot') : t('save_settlement_snapshot')}
                            </PrimaryButton>
                            {s.snapshot?.saved_at && (
                                <p className="text-xs text-slate-500">
                                    {t('last_saved')}: {new Date(s.snapshot.saved_at).toLocaleString()}
                                    {s.snapshot.saved_by?.name ? ` · ${s.snapshot.saved_by.name}` : ''}
                                </p>
                            )}
                        </form>
                    )}

                    {!canSave && (
                        <p className="text-xs text-slate-500">{t('settlement_view_only')}</p>
                    )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
