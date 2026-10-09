import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link } from '@inertiajs/react';

function PayModelChip({ payModel, kind, t }) {
    const model = payModel || (kind === 'salary' ? 'monthly' : kind === 'unit' ? 'unit' : 'daily');
    const label = t(`staff_pay_${model}`);
    const tone =
        model === 'monthly'
            ? 'bg-teal-500/15 text-teal-900 dark:text-teal-200'
            : model === 'unit'
              ? 'bg-sky-500/15 text-sky-900 dark:text-sky-200'
              : 'bg-amber-500/15 text-amber-950 dark:text-amber-200';

    return (
        <span className={`inline-flex rounded-lg px-2 py-1 text-xs font-semibold ${tone}`}>
            {label}
        </span>
    );
}

function placeLabel(row, t) {
    if (!row?.site_kind) return '—';
    if (row.site_kind === 'villa') {
        const bits = [t('staff_pay_site_villa'), row.villa_number, row.zone, row.area].filter(
            Boolean,
        );
        return bits.join(' · ');
    }
    const bits = [
        t('staff_pay_site_building'),
        row.block,
        row.zone,
        row.floor,
        row.apartment_number,
        row.apartment_model,
    ].filter(Boolean);
    return bits.join(' · ');
}

function PayTable({ title, hint, rows, emptyTitle, emptyHint, emptyAction, showHold }) {
    const t = useTranslations();

    if (!rows.length) {
        return (
            <EmptyState
                icon="advances"
                title={emptyTitle}
                description={emptyHint}
                action={emptyAction}
            />
        );
    }

    return (
        <DataPanel padded={false}>
            <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                    <NavIcon name="advances" className="text-base" />
                </span>
                <div>
                    <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">{title}</p>
                    <p className="text-xs text-slate-500 dark:text-slate-400">{hint}</p>
                </div>
            </div>
            <DataTable minWidth={showHold ? '52rem' : '40rem'} caption={title} stickyFirstColumn>
                <thead>
                    <tr>
                        <Th>{t('date')}</Th>
                        <Th align="end">{t('amount')}</Th>
                        <Th>{t('currency')}</Th>
                        <Th>{t('staff_pay_place')}</Th>
                        <Th>{t('purpose')}</Th>
                        <Th>{t('project')}</Th>
                        {showHold ? (
                            <>
                                <Th align="end">{t('job_pay_hold_10')}</Th>
                                <Th>{t('job_pay_payout_date')}</Th>
                            </>
                        ) : null}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id}>
                            <Td>
                                <span dir="ltr" className="font-sans tabular-nums">
                                    {row.occurred_on || '—'}
                                </span>
                            </Td>
                            <Td align="end" money>
                                <MoneyAmount
                                    value={row.amount}
                                    label={row.currency}
                                    size="sm"
                                    showLabel={false}
                                />
                            </Td>
                            <Td muted>{row.currency}</Td>
                            <Td muted>{placeLabel(row, t)}</Td>
                            <Td>{row.purpose || '—'}</Td>
                            <Td muted>{row.project?.name || '—'}</Td>
                            {showHold ? (
                                <>
                                    <Td align="end" money>
                                        <MoneyAmount
                                            value={row.hold_amount}
                                            label={row.currency}
                                            size="sm"
                                            showLabel={false}
                                        />
                                    </Td>
                                    <Td>
                                        <span dir="ltr" className="font-sans tabular-nums">
                                            {row.unlock_date || '—'}
                                        </span>
                                    </Td>
                                </>
                            ) : null}
                        </tr>
                    ))}
                </tbody>
            </DataTable>
        </DataPanel>
    );
}

export default function Show({
    staff,
    rates = [],
    jobPays = [],
    salaries = [],
    canPay = false,
    canCreateJobPay = false,
    canCreateUnitPay = false,
    canCreateSalary = false,
}) {
    const t = useTranslations();
    const payModel = staff?.pay_model || 'daily';
    const isMonthly = payModel === 'monthly';
    const isDaily = payModel === 'daily';
    const isUnit = payModel === 'unit';
    const canCreatePay =
        canPay || canCreateJobPay || canCreateUnitPay || canCreateSalary;
    const payHref = route('vault.lines.staff-pay.create', {
        staff_id: staff?.id,
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={staff?.name || t('staff')}
                    subtitle={t('staff_profile_simple_hint')}
                    icon={<NavIcon name="workers" className="text-lg text-teal-600 dark:text-teal-300" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('staff.index')}>
                                <SecondaryButton type="button">{t('staff_roster_title')}</SecondaryButton>
                            </Link>
                            {canCreatePay ? (
                                <Link href={payHref}>
                                    <PrimaryButton
                                        type="button"
                                        className={
                                            isUnit
                                                ? '!bg-sky-600 hover:!bg-sky-500'
                                                : isMonthly
                                                  ? ''
                                                  : '!bg-amber-600 hover:!bg-amber-500'
                                        }
                                    >
                                        <NavIcon
                                            name={isMonthly ? 'payroll' : isUnit ? 'productions' : 'advances'}
                                            className="text-sm"
                                        />
                                        {isMonthly
                                            ? t('vault_form_salary')
                                            : isUnit
                                              ? t('vault_form_unit_pay')
                                              : t('vault_form_job_pay')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </div>
                    }
                />
            }
        >
            <Head title={staff?.name || t('staff')} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-900 dark:bg-teal-400/15 dark:text-teal-200">
                            <NavIcon name="workers" className="text-lg" />
                        </span>
                        <div className="min-w-0">
                            <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                {staff?.name}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('staff_profile_card_simple')}
                            </p>
                        </div>
                    </div>
                    <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {t('phone')}
                            </dt>
                            <dd className="mt-1 text-sm font-medium text-slate-900 dark:text-white" dir="ltr">
                                {staff?.phone || '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {t('staff_pay_model')}
                            </dt>
                            <dd className="mt-1">
                                <PayModelChip
                                    payModel={staff?.pay_model}
                                    kind={staff?.kind}
                                    t={t}
                                />
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {t('staff_role')}
                            </dt>
                            <dd className="mt-1 text-sm font-medium text-slate-900 dark:text-white">
                                {staff?.role || staff?.trade || '—'}
                            </dd>
                        </div>
                        {isMonthly ? (
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {t('monthly_salary')}
                                </dt>
                                <dd
                                    className="mt-1 inline-flex items-baseline gap-1.5 text-sm font-semibold text-slate-900 dark:text-white"
                                    dir="ltr"
                                >
                                    <MoneyAmount
                                        value={staff?.monthly_salary}
                                        label={staff?.currency}
                                        size="md"
                                        showLabel={false}
                                    />
                                    <span className="text-xs font-medium text-slate-500">
                                        {staff?.currency}
                                    </span>
                                </dd>
                            </div>
                        ) : null}
                        {isDaily && staff?.day_rate != null ? (
                            <div>
                                <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {t('staff_day_rate')}
                                </dt>
                                <dd
                                    className="mt-1 inline-flex items-baseline gap-1.5 text-sm font-semibold text-slate-900 dark:text-white"
                                    dir="ltr"
                                >
                                    <MoneyAmount
                                        value={staff?.day_rate}
                                        label={staff?.currency}
                                        size="md"
                                        showLabel={false}
                                    />
                                    <span className="text-xs font-medium text-slate-500">
                                        {staff?.currency}
                                    </span>
                                </dd>
                            </div>
                        ) : null}
                    </dl>
                </section>

                {isUnit ? (
                    <section className="space-y-3">
                        <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                            {t('staff_rates_title')}
                        </h2>
                        {rates.length === 0 ? (
                            <EmptyState
                                icon="productions"
                                title={t('staff_rates_empty_title')}
                                description={t('staff_rates_empty_hint')}
                            />
                        ) : (
                            <DataPanel padded={false}>
                                <DataTable minWidth="36rem" caption={t('staff_rates_title')}>
                                    <thead>
                                        <tr>
                                            <Th>{t('staff_rate_item')}</Th>
                                            <Th>{t('rate_unit')}</Th>
                                            <Th align="end">{t('unit_rate')}</Th>
                                            <Th>{t('currency')}</Th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rates.map((rate) => (
                                            <tr key={rate.id}>
                                                <Td>{rate.item_name}</Td>
                                                <Td muted>{rate.unit}</Td>
                                                <Td align="end" money>
                                                    <MoneyAmount
                                                        value={rate.rate}
                                                        label={rate.currency}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                </Td>
                                                <Td muted>{rate.currency}</Td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </DataTable>
                            </DataPanel>
                        )}
                    </section>
                ) : null}

                <section className="space-y-3">
                    <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                        {t('vault_form_job_pay')}
                    </h2>
                    <PayTable
                        title={t('vault_form_job_pay')}
                        hint={t('staff_job_pay_hint')}
                        rows={jobPays}
                        emptyTitle={t('staff_job_pay_empty_title')}
                        emptyHint={t('staff_job_pay_empty_hint')}
                        emptyAction={
                            canCreatePay && !isMonthly ? (
                                <Link href={payHref}>
                                    <PrimaryButton
                                        type="button"
                                        className={
                                            isUnit
                                                ? '!bg-sky-600 hover:!bg-sky-500'
                                                : '!bg-amber-600 hover:!bg-amber-500'
                                        }
                                    >
                                        {isUnit ? t('vault_form_unit_pay') : t('job_pay_record')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                        showHold
                    />
                </section>

                {isMonthly ? (
                    <section className="space-y-3">
                        <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                            {t('vault_form_salary')}
                        </h2>
                        <PayTable
                            title={t('vault_form_salary')}
                            hint={t('staff_salary_pay_hint')}
                            rows={salaries}
                            emptyTitle={t('staff_salary_pay_empty_title')}
                            emptyHint={t('staff_salary_pay_empty_hint')}
                            emptyAction={
                                canCreatePay ? (
                                    <Link href={payHref}>
                                        <PrimaryButton type="button">{t('vault_form_salary')}</PrimaryButton>
                                    </Link>
                                ) : null
                            }
                            showHold
                        />
                    </section>
                ) : null}
            </PageShell>
        </AuthenticatedLayout>
    );
}
