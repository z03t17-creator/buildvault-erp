import DataPanel from '@/Components/DataPanel';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

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

export default function Show({ penalty }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canWaive = useCan('penalties.waive');
    const canApply = useCan('penalties.apply');
    const canViewAny = useCan('penalties.viewAny');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${t('penalties')} #${penalty.id}`}
                    subtitle={penalty.staff?.name}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={route('penalties.index')}>
                                    <SecondaryButton>{t('back')}</SecondaryButton>
                                </Link>
                            )}
                            {penalty.status === 'pending' && canApply && (
                                <PrimaryButton type="button" onClick={() => router.post(route('penalties.apply', penalty.id))}>
                                    {t('apply_penalty')}
                                </PrimaryButton>
                            )}
                            {penalty.status === 'pending' && canWaive && (
                                <SecondaryButton type="button" onClick={() => router.post(route('penalties.waive', penalty.id))}>
                                    {t('waive_penalty')}
                                </SecondaryButton>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`${t('penalties')} #${penalty.id}`} />
            <PageShell narrow>
                <DataPanel>
                    <StatusBadge status={penalty.status} />
                    <dl className="mt-5 grid gap-6 sm:grid-cols-2">
                        <Field label={`${t('amount_iqd')} (${iqd})`}>
                            <MoneyAmount
                                value={penalty.amount_iqd_display ?? penalty.amount_iqd}
                                label={iqd}
                                size="lg"
                                showLabel={false}
                                className="text-rose-700 dark:text-rose-300"
                            />
                        </Field>
                        <Field label={t('penalty_type')}>
                            {t(`penalty_type_${penalty.type}`) || penalty.type}
                        </Field>
                        <Field label={t('date')}>{penalty.occurred_on || '—'}</Field>
                        <Field label={t('project')}>{penalty.project?.name}</Field>
                        <Field label={t('staff')}>{penalty.staff?.name || '—'}</Field>
                        <div className="sm:col-span-2">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{t('reason')}</dt>
                            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{penalty.reason}</dd>
                        </div>
                        {penalty.notes && (
                            <div className="sm:col-span-2">
                                <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{t('notes')}</dt>
                                <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{penalty.notes}</dd>
                            </div>
                        )}
                        <Field label={t('created_by')}>{penalty.creator?.name || '—'}</Field>
                        <Field label={t('Status')}>{penalty.status}</Field>
                    </dl>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
