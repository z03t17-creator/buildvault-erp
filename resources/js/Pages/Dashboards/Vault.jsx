import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function MoneyStat({ label, value, currency, tone = 'teal' }) {
    const tones = {
        teal: 'text-teal-900 dark:text-teal-100',
        amber: 'text-amber-900 dark:text-amber-100',
        rose: 'text-rose-800 dark:text-rose-200',
        slate: 'text-slate-900 dark:text-white',
    };

    return (
        <div className="bv-card px-4 py-4">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className={
                    'mt-2 font-sans text-3xl font-semibold tracking-normal tabular-nums sm:text-4xl ' +
                    (tones[tone] || tones.teal)
                }
            >
                {value == null || value === '' ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                        accent={tone === 'teal'}
                    />
                )}
            </div>
            <p className="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                {currency}
            </p>
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
        {
            key: 'cash',
            label: t('available_cash'),
            value: estimate?.available_cash ?? 0,
            tone: 'default',
        },
        {
            key: 'salaries',
            label: t('vault_salaries_to_pay'),
            value: estimate?.salaries_to_pay ?? 0,
            tone: 'default',
        },
        {
            key: 'expenses',
            label: t('vault_expenses_to_pay'),
            value: estimate?.expenses_to_pay ?? 0,
            tone: 'default',
        },
        {
            key: 'result',
            label: covers ? t('vault_headroom') : t('shortfall'),
            value: covers ? headroom : shortfall,
            tone: covers ? 'ok' : 'short',
        },
    ];

    return (
        <div
            className={
                'rounded-2xl border p-4 ' +
                (covers
                    ? 'border-teal-200 bg-teal-50/70 dark:border-teal-800 dark:bg-teal-950/30'
                    : 'border-rose-200 bg-rose-50/80 dark:border-rose-900 dark:bg-rose-950/30')
            }
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {currency}
                    </p>
                    <p className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                        {covers
                            ? t('vault_estimate_covers')
                            : t('vault_estimate_short')}
                    </p>
                </div>
                <span
                    className={
                        'inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold ' +
                        (covers
                            ? 'bg-teal-600 text-white dark:bg-teal-400 dark:text-slate-950'
                            : 'bg-rose-600 text-white dark:bg-rose-400 dark:text-slate-950')
                    }
                >
                    {covers ? t('vault_estimate_ok') : t('shortfall')}
                </span>
            </div>

            <dl className="mt-4 space-y-2.5">
                {rows.map((row) => (
                    <div
                        key={row.key}
                        className="flex items-baseline justify-between gap-4 border-b border-black/5 pb-2 last:border-0 last:pb-0 dark:border-white/10"
                    >
                        <dt className="min-w-0 flex-1 text-xs font-medium leading-5 text-slate-500 dark:text-slate-400">
                            {row.label}
                        </dt>
                        <dd
                            dir="ltr"
                            className={
                                'shrink-0 text-end font-sans text-base font-semibold tabular-nums sm:text-lg ' +
                                (row.tone === 'ok'
                                    ? 'text-teal-800 dark:text-teal-200'
                                    : row.tone === 'short'
                                      ? 'text-rose-700 dark:text-rose-300'
                                      : 'text-slate-900 dark:text-white')
                            }
                        >
                            <MoneyAmount
                                value={row.value}
                                label={currency}
                                size="sm"
                                showLabel={false}
                                className="!text-inherit !text-base sm:!text-lg"
                            />
                            <span className="ms-1 text-xs font-medium text-slate-400">
                                {currency}
                            </span>
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
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
}) {
    if (!rows?.length) {
        return (
            <EmptyState
                icon="insurance"
                title={emptyTitle}
                description={emptyHint}
            />
        );
    }

    return (
        <DataTable>
            <thead>
                <tr>
                    {showStaff ? <Th>{t('staff')}</Th> : null}
                    <Th>{t('amount')}</Th>
                    <Th>{t('currency')}</Th>
                    <Th>{t('vault_unlock_date')}</Th>
                    <Th>{t('date')}</Th>
                    {showStaff && canConfirmHold ? <Th>{t('job_pay_hold_status')}</Th> : null}
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.id}>
                        {showStaff ? (
                            <Td className="font-medium text-slate-900 dark:text-white">
                                {row.staff_name || '—'}
                                {row.due ? (
                                    <span className="ms-2 inline-flex rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">
                                        {t('vault_hold_due_now')}
                                    </span>
                                ) : null}
                            </Td>
                        ) : null}
                        <Td dir="ltr" className="font-sans tabular-nums">
                            <MoneyAmount
                                value={row.amount}
                                label={row.currency}
                                size="sm"
                                showLabel={false}
                            />
                        </Td>
                        <Td>{row.currency === 'USD' ? usd : iqd}</Td>
                        <Td dir="ltr" className="font-sans tabular-nums text-slate-700 dark:text-slate-200">
                            {row.unlock_date || '—'}
                        </Td>
                        <Td dir="ltr" className="font-sans tabular-nums text-slate-500">
                            {row.occurred_on || '—'}
                        </Td>
                        {showStaff && canConfirmHold ? (
                            <Td>
                                <SecondaryButton
                                    type="button"
                                    disabled={confirmingId === row.id}
                                    onClick={() => onConfirmHold?.(row.id)}
                                    className="!min-h-0 !px-2.5 !py-1.5 text-xs !bg-amber-50 !text-amber-950 hover:!bg-amber-100 dark:!bg-amber-950/40 dark:!text-amber-100"
                                >
                                    {t('job_pay_hold_confirm')}
                                </SecondaryButton>
                            </Td>
                        ) : null}
                    </tr>
                ))}
            </tbody>
        </DataTable>
    );
}

function MoneyAction({ href, icon, title, hint, tone = 'teal' }) {
    const tones = {
        teal: 'border-teal-200 hover:border-teal-400 hover:bg-teal-50/70 dark:border-teal-900 dark:hover:border-teal-600 dark:hover:bg-teal-950/40',
        rose: 'border-rose-200 hover:border-rose-400 hover:bg-rose-50/70 dark:border-rose-900 dark:hover:border-rose-600 dark:hover:bg-rose-950/40',
        amber: 'border-amber-200 hover:border-amber-400 hover:bg-amber-50/70 dark:border-amber-900 dark:hover:border-amber-600 dark:hover:bg-amber-950/40',
        slate: 'border-slate-200 hover:border-slate-400 hover:bg-slate-50/70 dark:border-slate-700 dark:hover:border-slate-500 dark:hover:bg-slate-900/60',
    };
    const iconTones = {
        teal: 'bg-teal-600 text-white dark:bg-teal-400 dark:text-slate-950',
        rose: 'bg-rose-600 text-white dark:bg-rose-400 dark:text-slate-950',
        amber: 'bg-amber-600 text-white dark:bg-amber-400 dark:text-slate-950',
        slate: 'bg-slate-700 text-white dark:bg-slate-300 dark:text-slate-950',
    };

    return (
        <Link
            href={href}
            className={
                'flex min-h-[4.5rem] items-start gap-3 rounded-xl border p-3 transition ' +
                (tones[tone] || tones.teal)
            }
        >
            <span
                className={
                    'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ' +
                    (iconTones[tone] || iconTones.teal)
                }
            >
                <NavIcon name={icon} className="text-base" />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-slate-900 dark:text-white">
                    {title}
                </span>
                <span className="mt-0.5 block text-xs font-medium text-slate-500 dark:text-slate-400">
                    {hint}
                </span>
            </span>
        </Link>
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
        if (!canManage || confirmingId) {
            return;
        }
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
            header={
                <PageHeader
                    title={t('vault')}
                    subtitle={t('vault_simple_subtitle')}
                    icon={<NavIcon name="vault" className="text-lg text-teal-600 dark:text-teal-300" />}
                />
            }
        >
            <Head title={t('vault')} />

            <PageShell className="!space-y-5">
                {canManage ? (
                    <section>
                        <div className="mb-2">
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('vault_money_forms')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('vault_money_forms_hint')}
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <MoneyAction
                                href={route('vault.lines.advance.create')}
                                icon="vault"
                                title={t('vault_form_advance')}
                                hint={t('vault_form_advance_short')}
                                tone="teal"
                            />
                            <MoneyAction
                                href={route('vault.lines.expense.create')}
                                icon="expenses"
                                title={t('vault_form_expense')}
                                hint={t('vault_form_expense_short')}
                                tone="rose"
                            />
                            <MoneyAction
                                href={route('vault.lines.job-pay.create')}
                                icon="workers"
                                title={t('vault_form_job_pay')}
                                hint={t('vault_form_job_pay_short')}
                                tone="amber"
                            />
                            <MoneyAction
                                href={route('vault.lines.salary.create')}
                                icon="payroll"
                                title={t('vault_form_salary')}
                                hint={t('vault_form_salary_short')}
                                tone="slate"
                            />
                        </div>
                        <div className="mt-3">
                            <Link href={route('staff.create')}>
                                <PrimaryButton type="button" className="!px-3 !py-2 text-sm">
                                    <NavIcon name="workers" className="text-sm" />
                                    {t('vault_form_add_staff')}
                                </PrimaryButton>
                            </Link>
                        </div>
                    </section>
                ) : null}

                <section>
                    <div className="mb-2 flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('available_cash')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('vault_available_cash_hint')}
                            </p>
                        </div>
                        {asOf ? (
                            <p dir="ltr" className="text-xs font-medium tabular-nums text-slate-400">
                                {asOf}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <MoneyStat
                            label={usd}
                            value={cash.USD}
                            currency={usd}
                            tone="teal"
                        />
                        <MoneyStat
                            label={iqd}
                            value={cash.IQD}
                            currency={iqd}
                            tone="teal"
                        />
                    </div>
                </section>

                <DataPanel
                    title={t('vault_insurance_unlocks')}
                    subtitle={t('vault_insurance_unlocks_hint')}
                >
                    <HoldTable
                        rows={unlocks}
                        emptyTitle={t('vault_insurance_empty')}
                        emptyHint={t('vault_insurance_empty_hint')}
                        showStaff={false}
                        t={t}
                        usd={usd}
                        iqd={iqd}
                    />
                </DataPanel>

                <DataPanel
                    title={t('vault_staff_holds')}
                    subtitle={t('vault_staff_holds_hint')}
                >
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
                </DataPanel>

                <section>
                    <div className="mb-2">
                        <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                            {t('vault_estimate_title')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('vault_estimate_hint')}
                        </p>
                    </div>
                    <div className="grid gap-3 lg:grid-cols-2">
                        <EstimateCard estimate={est.USD} currency={usd} t={t} />
                        <EstimateCard estimate={est.IQD} currency={iqd} t={t} />
                    </div>
                </section>
            </PageShell>
        </AuthenticatedLayout>
    );
}
