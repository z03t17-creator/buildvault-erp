import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router, useForm } from '@inertiajs/react';

function formatIqd(n, iqdLabel = 'IQD') {
    return (
        new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0,
        }).format(Number(n) || 0) +
        ' ' +
        iqdLabel
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
            <div className="py-8">
                <div className="mx-auto max-w-2xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70">
                    <StatusBadge status={advance.status} />
                    <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('amount_iqd')}</dt>
                            <dd className="tabular-nums">{formatIqd(advance.amount_iqd, iqd)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('remaining_iqd')}</dt>
                            <dd className="tabular-nums text-amber-700 dark:text-amber-300">
                                {formatIqd(advance.remaining_iqd, iqd)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('date')}</dt>
                            <dd>{advance.advanced_on}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('project')}</dt>
                            <dd>{advance.project?.name || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('repayment_method')}</dt>
                            <dd>{t(`repay_${advance.repayment_method}`) || advance.repayment_method}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-400">{t('entered_by')}</dt>
                            <dd>{advance.enteredBy?.name || advance.entered_by?.name || '—'}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-xs uppercase text-slate-400">{t('reason')}</dt>
                            <dd>{advance.reason}</dd>
                        </div>
                        {advance.notes && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs uppercase text-slate-400">{t('notes')}</dt>
                                <dd>{advance.notes}</dd>
                            </div>
                        )}
                    </dl>

                    {canRepay && advance.status === 'open' && Number(advance.remaining_iqd) > 0 && (
                        <form
                            className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700"
                            onSubmit={(e) => {
                                e.preventDefault();
                                repayForm.post(route('advances.repay', advance.id));
                            }}
                        >
                            <div>
                                <InputLabel value={t('repay_amount_iqd')} />
                                <TextInput
                                    type="number"
                                    step="1"
                                    min="1"
                                    className="mt-1 block w-40"
                                    value={repayForm.data.amount_iqd}
                                    onChange={(e) => repayForm.setData('amount_iqd', e.target.value)}
                                    required
                                />
                                <InputError message={repayForm.errors.amount_iqd} className="mt-1" />
                            </div>
                            <PrimaryButton disabled={repayForm.processing}>{t('record_repayment')}</PrimaryButton>
                        </form>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
