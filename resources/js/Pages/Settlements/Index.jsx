import DataPanel from '@/Components/DataPanel';
import FlashBanner from '@/Components/FlashBanner';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const LINE_META = {
    money_received: { tone: 'in' },
    project_expenses: { tone: 'out' },
    payroll: { tone: 'out' },
    employee_advances: { tone: 'out' },
    insurance: { tone: 'muted' },
    penalties: { tone: 'muted' },
    other_expenses: { tone: 'out' },
    approved_payments: { tone: 'out' },
};

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        teal: 'text-teal-900 dark:text-teal-200',
        rose: 'text-rose-800 dark:text-rose-200',
    };

    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className={
                    'mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums sm:text-3xl ' +
                    (tones[tone] || tones.default)
                }
            >
                {value == null ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

export default function Index({ settlement, canSave, filters }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const page = usePage();
    const errors = page.props.errors || {};

    const s = settlement || {};
    const ability = s.ability || {};
    const monthLines =
        s.month_lines ||
        (s.lines || []).filter(
            (line) =>
                line.key !== 'available_money_for_payment' &&
                line.key !== 'available_vault_balance' &&
                !line.live,
        );

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
    const availableIqd = s.available_money_for_payment_iqd ?? 0;
    const availableUsd = s.available_money_for_payment_usd ?? 0;
    const hasCash = availableIqd > 0 || availableUsd > 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('monthly_settlement')}
                    subtitle={t('settlements_page_hint')}
                    icon={<NavIcon name="settlements" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('dashboards.vault')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="vault" className="text-sm" />
                                    {t('vault_dashboard')}
                                </SecondaryButton>
                            </Link>
                        </div>
                    }
                />
            }
        >
            <Head title={t('monthly_settlement')} />

            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('settlements_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('settlements_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:items-end">
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('month')}
                            </label>
                            <input
                                type="month"
                                className={`${fieldClass} font-sans tabular-nums`}
                                value={form.data.month}
                                onChange={(e) => {
                                    form.setData('month', e.target.value);
                                    applyFilters({ month: e.target.value });
                                }}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('project')}
                            </label>
                            <select
                                className={fieldClass}
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
                        </div>
                        <p className="text-xs text-slate-500 dark:text-slate-400 sm:col-span-2 lg:col-span-1">
                            {s.from && s.to
                                ? `${s.from} → ${s.to}`
                                : t('settlements_period_hint')}
                        </p>
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                            <NavIcon name="vault" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('available_money_for_payment')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('settlements_available_hint')}
                            </p>
                        </div>
                    </div>
                    <div
                        className={
                            'bv-card grid gap-4 p-4 sm:grid-cols-2 sm:p-5 ' +
                            (hasCash
                                ? 'ring-2 ring-teal-500/30 dark:ring-teal-400/30'
                                : 'ring-2 ring-amber-500/30 dark:ring-amber-400/30')
                        }
                    >
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {iqd}
                            </p>
                            <div className="mt-1">
                                <MoneyAmount
                                    value={availableIqd}
                                    label={iqd}
                                    size="hero"
                                    showLabel={false}
                                    accent={availableIqd > 0}
                                    className={
                                        availableIqd > 0
                                            ? 'text-teal-900 dark:text-teal-200'
                                            : ''
                                    }
                                />
                            </div>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {usd}
                            </p>
                            <div className="mt-1">
                                <MoneyAmount
                                    value={availableUsd}
                                    label={usd}
                                    size="hero"
                                    showLabel={false}
                                    accent={availableUsd > 0}
                                    className={
                                        availableUsd > 0
                                            ? 'text-teal-900 dark:text-teal-200'
                                            : ''
                                    }
                                />
                            </div>
                        </div>
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <MoneyStat
                            label={t('current_balance') + ` · ${iqd}`}
                            value={s.current_vault_iqd}
                            currency={iqd}
                            tone="teal"
                        />
                        <MoneyStat
                            label={t('current_balance') + ` · ${usd}`}
                            value={s.current_vault_usd}
                            currency={usd}
                            tone="teal"
                        />
                        <MoneyStat
                            label={t('pending_commitments') + ` · ${iqd}`}
                            value={s.pending_commitments_iqd}
                            currency={iqd}
                            tone="rose"
                        />
                        <MoneyStat
                            label={t('reserved_balance') + ` · ${usd}`}
                            value={s.reserved_insurance_usd}
                            currency={usd}
                        />
                    </div>
                </section>

                <DataPanel padded={false}>
                    <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                            <NavIcon name="settlements" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('settlement_month_activity')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('settlements_month_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-[36rem] w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-200/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800">
                                    <th className="px-4 py-3">{t('settlements_line')}</th>
                                    <th className="px-4 py-3 text-end text-teal-900 dark:text-teal-200">
                                        {usd}
                                    </th>
                                    <th className="px-4 py-3 text-end text-teal-900 dark:text-teal-200">
                                        {iqd}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {monthLines.map((line) => {
                                    const meta = LINE_META[line.key] || {
                                        tone: 'muted',
                                    };
                                    const amountClass =
                                        meta.tone === 'in'
                                            ? 'text-emerald-700 dark:text-emerald-400'
                                            : meta.tone === 'out'
                                              ? 'text-rose-700 dark:text-rose-400'
                                              : 'text-slate-900 dark:text-white';

                                    return (
                                        <tr
                                            key={line.key}
                                            className="border-b border-slate-100 dark:border-slate-800/80"
                                        >
                                            <td className="px-4 py-3.5 text-slate-700 dark:text-slate-200">
                                                {t(`settlement_${line.key}`)}
                                            </td>
                                            <td
                                                className={`px-4 py-3.5 text-end ${amountClass}`}
                                            >
                                                <MoneyAmount
                                                    value={line.amount_usd ?? 0}
                                                    label={usd}
                                                    size="sm"
                                                    showLabel={false}
                                                    className={amountClass}
                                                />
                                            </td>
                                            <td
                                                className={`px-4 py-3.5 text-end ${amountClass}`}
                                            >
                                                <MoneyAmount
                                                    value={line.amount_iqd ?? 0}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                    className={amountClass}
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                            <tfoot>
                                <tr className="bg-slate-50/80 dark:bg-slate-900/40">
                                    <td className="px-4 py-3.5 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                        {t('settlements_month_outflows')}
                                    </td>
                                    <td className="px-4 py-3.5 text-end text-rose-700 dark:text-rose-400">
                                        <MoneyAmount
                                            value={s.month_outflows_usd ?? 0}
                                            label={usd}
                                            size="sm"
                                            showLabel={false}
                                            className="text-rose-700 dark:text-rose-400"
                                        />
                                    </td>
                                    <td className="px-4 py-3.5 text-end text-rose-700 dark:text-rose-400">
                                        <MoneyAmount
                                            value={s.month_outflows_iqd ?? 0}
                                            label={iqd}
                                            size="sm"
                                            showLabel={false}
                                            className="text-rose-700 dark:text-rose-400"
                                        />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </DataPanel>

                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                            <NavIcon name="payouts" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('ability_to_pay_check')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('settlements_ability_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:items-end">
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('requested_payout_iqd')}
                            </label>
                            <TextInput
                                type="text"
                                inputMode="numeric"
                                className={`${fieldClass} font-sans tabular-nums`}
                                value={form.data.requested_payout_iqd}
                                onChange={(e) =>
                                    form.setData(
                                        'requested_payout_iqd',
                                        e.target.value,
                                    )
                                }
                                onBlur={() =>
                                    applyFilters({
                                        requested_payout_iqd:
                                            form.data.requested_payout_iqd,
                                    })
                                }
                                placeholder="0"
                            />
                        </div>
                        <SecondaryButton
                            type="button"
                            onClick={() =>
                                applyFilters({
                                    requested_payout_iqd:
                                        form.data.requested_payout_iqd,
                                })
                            }
                        >
                            {t('check_ability')}
                        </SecondaryButton>
                    </div>

                    {blocked && (
                        <div className="mt-3">
                            <FlashBanner tone="error">
                                <span className="font-semibold">
                                    {t('payout_blocked')}
                                </span>
                                {(ability.reasons || []).map((reason) => (
                                    <span key={reason} className="mt-1 block">
                                        {reason}
                                    </span>
                                ))}
                                {ability.shortfall_iqd > 0 && (
                                    <span className="mt-2 block">
                                        {t('shortfall')}:{' '}
                                        <MoneyAmount
                                            value={ability.shortfall_iqd}
                                            label={iqd}
                                            size="sm"
                                            className="text-rose-800 dark:text-rose-200"
                                        />
                                    </span>
                                )}
                            </FlashBanner>
                        </div>
                    )}

                    {!blocked && ability.requested_payout_iqd > 0 && (
                        <div className="mt-3">
                            <FlashBanner tone="success">
                                {t('payout_allowed')}
                            </FlashBanner>
                        </div>
                    )}

                    {(errors.requested_payout_iqd || errors.month) && (
                        <p className="mt-2 text-sm text-rose-600 dark:text-rose-400">
                            {errors.requested_payout_iqd || errors.month}
                        </p>
                    )}
                </section>

                {canSave ? (
                    <form
                        onSubmit={save}
                        className="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-4 dark:border-slate-800"
                    >
                        <PrimaryButton
                            type="submit"
                            disabled={form.processing || blocked}
                            className="!bg-teal-600 hover:!bg-teal-500 dark:!bg-teal-400 dark:!text-teal-950"
                        >
                            <NavIcon name="settlements" className="text-sm" />
                            {s.snapshot
                                ? t('update_settlement_snapshot')
                                : t('save_settlement_snapshot')}
                        </PrimaryButton>
                        {s.snapshot?.saved_at ? (
                            <p className="text-xs text-slate-500">
                                {t('last_saved')}:{' '}
                                {new Date(s.snapshot.saved_at).toLocaleString()}
                                {s.snapshot.saved_by?.name
                                    ? ` · ${s.snapshot.saved_by.name}`
                                    : ''}
                            </p>
                        ) : null}
                    </form>
                ) : (
                    <p className="text-xs text-slate-500">
                        {t('settlement_view_only')}
                    </p>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
