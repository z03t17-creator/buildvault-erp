import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyAmount from '@/Components/MoneyAmount';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router, useForm } from '@inertiajs/react';

function Field({ label, children }) {
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

export default function Show({ advance }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canRepay = useCan('advances.repay');
    const canCancel = useCan('advances.cancel');
    const canViewAny = useCan('advances.viewAny');
    const repayForm = useForm({
        amount_iqd: advance?.remaining_iqd ? String(Math.round(Number(advance.remaining_iqd))) : '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${t('advance')} #${advance.id}`}
                    subtitle={advance.worker?.name}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={route('advances.index')}>
                                    <SecondaryButton>{t('back')}</SecondaryButton>
                                </Link>
                            )}
                            {advance.status === 'open' && canCancel && (
                                <SecondaryButton
                                    type="button"
                                    onClick={() => {
                                        if (window.confirm(t('cancel_advance_confirm'))) {
                                            router.post(route('advances.cancel', advance.id));
                                        }
                                    }}
                                >
                                    {t('cancel_advance')}
                                </SecondaryButton>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`${t('advance')} #${advance.id}`} />
            <PageShell narrow>
                <DataPanel>
                    <StatusBadge status={advance.status} />
                    <dl className="mt-5 grid gap-6 sm:grid-cols-2">
                        <Field label={`${t('amount_iqd')} (${iqd})`}>
                            <MoneyAmount value={advance.amount_iqd} label={iqd} size="lg" showLabel={false} />
                        </Field>
                        <Field label={`${t('remaining_iqd')} (${iqd})`}>
                            <MoneyAmount
                                value={advance.remaining_iqd}
                                label={iqd}
                                size="lg"
                                showLabel={false}
                                className="text-amber-700 dark:text-amber-300"
                            />
                        </Field>
                        <Field label={t('date')}>{advance.advanced_on}</Field>
                        <Field label={t('project')}>{advance.project?.name || '—'}</Field>
                        <Field label={t('repayment_method')}>
                            {t(`repay_${advance.repayment_method}`) || advance.repayment_method}
                        </Field>
                        <Field label={t('entered_by')}>
                            {advance.enteredBy?.name || advance.entered_by?.name || '—'}
                        </Field>
                        <div className="sm:col-span-2">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{t('reason')}</dt>
                            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{advance.reason}</dd>
                        </div>
                        {advance.notes && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{t('notes')}</dt>
                                <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{advance.notes}</dd>
                            </div>
                        )}
                    </dl>

                    {canRepay && advance.status === 'open' && Number(advance.remaining_iqd) > 0 && (
                        <form
                            className="mt-6 space-y-5 border-t border-slate-200/80 pt-5 dark:border-slate-700/80"
                            onSubmit={(e) => {
                                e.preventDefault();
                                repayForm.post(route('advances.repay', advance.id));
                            }}
                        >
                            <FormSection>
                                <FormField>
                                    <InputLabel value={`${t('repay_amount_iqd')} (${iqd})`} />
                                    <MoneyInput
                                        className="mt-1 block w-full max-w-xs"
                                        value={repayForm.data.amount_iqd}
                                        onValueChange={(raw) => repayForm.setData('amount_iqd', raw)}
                                        required
                                    />
                                    <InputError message={repayForm.errors.amount_iqd} className="mt-1" />
                                </FormField>
                            </FormSection>
                            <FormActions>
                                <PrimaryButton disabled={repayForm.processing}>{t('record_repayment')}</PrimaryButton>
                            </FormActions>
                        </form>
                    )}
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
