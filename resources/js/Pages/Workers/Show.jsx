import DataPanel from '@/Components/DataPanel';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

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

export default function Show({ worker }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canUpdate = useCan('workers.update');
    const canViewAny = useCan('workers.viewAny');
    const canDocs = useCan('documents.viewAny');
    const canReports = useCan('vault.exports');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={worker.name}
                    subtitle={t('worker_profile_subtitle')}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={route('workers.index')}>
                                    <SecondaryButton>{t('back')}</SecondaryButton>
                                </Link>
                            )}
                            {canDocs && (
                                <Link
                                    href={route('documents.index', {
                                        worker_id: worker.id,
                                        project_id: worker.project_id || undefined,
                                    })}
                                >
                                    <SecondaryButton>{t('docs')}</SecondaryButton>
                                </Link>
                            )}
                            {canReports && (
                                <a href={`${route('exports.worker', worker.id)}`}>
                                    <SecondaryButton type="button">{t('download_pdf')}</SecondaryButton>
                                </a>
                            )}
                            {canUpdate && (
                                <Link href={route('workers.edit', worker.id)}>
                                    <SecondaryButton>{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={worker.name} />
            <PageShell narrow>
                <DataPanel>
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
                        {worker.avatar_url ? (
                            <img
                                src={worker.avatar_url}
                                alt={worker.name}
                                className="h-24 w-24 shrink-0 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-slate-700"
                            />
                        ) : (
                            <div className="flex h-24 w-24 shrink-0 items-center justify-center rounded-lg bg-emerald-600/10 font-display text-3xl font-semibold text-emerald-800 ring-1 ring-emerald-500/20 dark:bg-emerald-500/15 dark:text-emerald-300">
                                {(worker.name || '?').charAt(0).toUpperCase()}
                            </div>
                        )}
                        <div className="min-w-0 space-y-2">
                            <StatusBadge status={worker.role} />
                            <h2 className="font-display text-2xl font-semibold text-slate-900 dark:text-white" dir="auto">
                                {worker.name}
                            </h2>
                            <p className="text-sm text-slate-500">
                                {worker.project?.name || t('unassigned')}
                            </p>
                        </div>
                    </div>
                </DataPanel>

                <DataPanel title={t('profile_information')}>
                    <dl className="grid gap-6 sm:grid-cols-2">
                        <Field label={t('project')}>
                            {worker.project ? (
                                <Link
                                    href={route('projects.show', worker.project.id)}
                                    className="text-emerald-700 hover:underline dark:text-emerald-400"
                                >
                                    {worker.project.name}
                                </Link>
                            ) : (
                                t('unassigned')
                            )}
                        </Field>
                        <Field label={t('phone')}>{worker.phone || '—'}</Field>
                        <Field label={`${t('daily_rate')} (${iqd})`}>
                            <MoneyAmount value={worker.daily_rate_usd} label={iqd} size="sm" showLabel={false} />
                        </Field>
                        <Field label={`${t('overtime_rate')} (${iqd})`}>
                            <MoneyAmount value={worker.overtime_rate_usd} label={iqd} size="sm" showLabel={false} />
                        </Field>
                        <Field label={t('manual_ot_hours')}>
                            <span dir="ltr" className="font-sans tabular-nums">
                                {Number(worker.manual_ot_hours || 0).toFixed(2)}
                            </span>
                        </Field>
                        <Field label={`${t('spending_limit')} (${iqd})`}>
                            <MoneyAmount value={worker.spending_limit_usd} label={iqd} size="sm" showLabel={false} />
                        </Field>
                        <Field label={t('national_id')}>{worker.national_id_number || '—'}</Field>
                    </dl>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
