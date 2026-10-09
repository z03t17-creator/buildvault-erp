import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        open: 'text-amber-800 dark:text-amber-200',
        muted: 'text-slate-400',
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
                    tones[tone]
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
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

function Meta({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {label}
            </dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                {children}
            </dd>
        </div>
    );
}

function StatusChip({ status, t }) {
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status?.replace(/_/g, ' ') || '—';
    const tones = {
        open: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        repaid: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        cancelled: 'bg-slate-500/15 text-slate-600 dark:text-slate-400',
    };

    return (
        <span
            className={
                'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold capitalize ' +
                (tones[status] || tones.open)
            }
        >
            <NavIcon name="advances" className="text-xs" />
            {label}
        </span>
    );
}

export default function Show({ advance }) {
    const t = useTranslations();
    const usd = t('USD');
    const iqd = t('IQD');
    const canRepay = useCan('advances.repay');
    const canCancel = useCan('advances.cancel');
    const canViewAny = useCan('advances.viewAny');

    const amountUsd = Number(advance.amount_usd) || 0;
    const amountIqd = Number(advance.amount_iqd) || 0;
    const remUsd = Number(advance.remaining_usd) || 0;
    const remIqd = Number(advance.remaining_iqd) || 0;
    const payCurrency = remUsd > 0 && remIqd <= 0 ? 'USD' : 'IQD';
    const payCurrencyLabel = payCurrency === 'USD' ? usd : iqd;
    const remaining = payCurrency === 'USD' ? remUsd : remIqd;

    const repayForm = useForm({
        amount: remaining > 0 ? String(Math.round(remaining * 100) / 100) : '',
        amount_iqd: remIqd > 0 ? String(Math.round(remIqd)) : '',
        amount_usd: remUsd > 0 ? String(remUsd) : '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={advance.worker?.name || t('staff_pay_title')}
                    subtitle={t('staff_pay_show_hint')}
                    icon={<NavIcon name="advances" className="text-lg" />}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={route('advances.index')}>
                                    <SecondaryButton type="button">
                                        <NavIcon name="advances" className="text-sm" />
                                        {t('staff_pay_title')}
                                    </SecondaryButton>
                                </Link>
                            )}
                            {advance.status === 'open' && canCancel && (
                                <SecondaryButton
                                    type="button"
                                    className="!border-rose-300 !text-rose-700 hover:!bg-rose-50 dark:!border-rose-800 dark:!text-rose-300 dark:hover:!bg-rose-950/40"
                                    onClick={() => {
                                        if (window.confirm(t('cancel_advance_confirm'))) {
                                            router.post(route('advances.cancel', advance.id));
                                        }
                                    }}
                                >
                                    <NavIcon name="trash" className="text-sm" />
                                    {t('cancel_advance')}
                                </SecondaryButton>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={advance.worker?.name || t('staff_pay_title')} />
            <PageShell narrow className="!space-y-6">
                <section>
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <StatusChip status={advance.status} t={t} />
                        {advance.worker?.labor_kind ? (
                            <span className="text-xs text-slate-500">
                                {t(`labor_kind_${advance.worker.labor_kind}`)}
                            </span>
                        ) : null}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <MoneyStat
                            label={t('staff_pay_amount_usd')}
                            value={amountUsd > 0 ? amountUsd : null}
                            currency={usd}
                            tone={amountUsd > 0 ? 'default' : 'muted'}
                        />
                        <MoneyStat
                            label={t('staff_pay_amount_iqd')}
                            value={amountIqd > 0 ? amountIqd : null}
                            currency={iqd}
                            tone={amountIqd > 0 ? 'default' : 'muted'}
                        />
                        <MoneyStat
                            label={t('staff_pay_remaining_usd')}
                            value={remUsd > 0 ? remUsd : null}
                            currency={usd}
                            tone={remUsd > 0 ? 'open' : 'muted'}
                        />
                        <MoneyStat
                            label={t('staff_pay_remaining_iqd')}
                            value={remIqd > 0 ? remIqd : null}
                            currency={iqd}
                            tone={remIqd > 0 ? 'open' : 'muted'}
                        />
                    </div>
                </section>

                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                            <NavIcon name="docs" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('staff_pay_details')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('staff_pay_details_hint')}
                            </p>
                        </div>
                    </div>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Meta label={t('date')}>
                            {advance.advanced_on
                                ? String(advance.advanced_on).slice(0, 10)
                                : '—'}
                        </Meta>
                        <Meta label={t('project')}>{advance.project?.name || '—'}</Meta>
                        <Meta label={t('repayment_method')}>
                            {t(`repay_${advance.repayment_method}`) !==
                            `repay_${advance.repayment_method}`
                                ? t(`repay_${advance.repayment_method}`)
                                : advance.repayment_method}
                        </Meta>
                        <Meta label={t('entered_by')}>
                            {advance.enteredBy?.name || advance.entered_by?.name || '—'}
                        </Meta>
                        <div className="sm:col-span-2">
                            <Meta label={t('reason')}>{advance.reason || '—'}</Meta>
                        </div>
                        {advance.notes ? (
                            <div className="sm:col-span-2">
                                <Meta label={t('notes')}>{advance.notes}</Meta>
                            </div>
                        ) : null}
                    </dl>
                </section>

                {canRepay && advance.status === 'open' && remaining > 0 && (
                    <section className="bv-card p-4 sm:p-5">
                        <div className="mb-3 flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <NavIcon name="moneyIn" className="text-base" />
                            </span>
                            <div>
                                <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('record_repayment')}
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {t('staff_pay_repay_hint', {
                                        currency: payCurrencyLabel,
                                        remaining: remaining,
                                    })}
                                </p>
                            </div>
                        </div>
                        <form
                            noValidate
                            onSubmit={(e) => {
                                e.preventDefault();
                                repayForm.post(route('advances.repay', advance.id));
                            }}
                        >
                            <FormSection cols={2}>
                                <FormField>
                                    <InputLabel
                                        value={`${t('staff_pay_repay_amount')} (${payCurrencyLabel})`}
                                    />
                                    <MoneyInput
                                        className={fieldClass}
                                        value={repayForm.data.amount}
                                        onValueChange={(raw) => {
                                            repayForm.setData({
                                                ...repayForm.data,
                                                amount: raw,
                                                amount_iqd:
                                                    payCurrency === 'IQD'
                                                        ? raw
                                                        : repayForm.data.amount_iqd,
                                                amount_usd:
                                                    payCurrency === 'USD'
                                                        ? raw
                                                        : repayForm.data.amount_usd,
                                            });
                                        }}
                                        allowDecimals={payCurrency === 'USD'}
                                    />
                                    <InputError
                                        message={
                                            repayForm.errors.amount ||
                                            repayForm.errors.amount_iqd ||
                                            repayForm.errors.amount_usd
                                        }
                                        className="mt-1"
                                    />
                                </FormField>
                            </FormSection>
                            <FormActions className="mt-4">
                                <PrimaryButton
                                    disabled={repayForm.processing}
                                    className="!bg-amber-600 hover:!bg-amber-500"
                                >
                                    {t('record_repayment')}
                                </PrimaryButton>
                            </FormActions>
                        </form>
                    </section>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
