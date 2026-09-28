import PageHeader from '@/Components/PageHeader';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">{children}</dd>
        </div>
    );
}

export default function Show({ worker }) {
    const t = useTranslations();
    const canUpdate = useCan('workers.update');
    const canViewAny = useCan('workers.viewAny');
    const canDocs = useCan('documents.viewAny');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={worker.name}
                    subtitle={worker.project?.name || t('unassigned')}
                    actions={
                        <>
                            {canViewAny && (
                                <Link href={route('workers.index')}>
                                    <SecondaryButton>{t('back')}</SecondaryButton>
                                </Link>
                            )}
                            {canDocs && (
                                <Link href={route('documents.index', { worker_id: worker.id, project_id: worker.project_id || undefined })}>
                                    <SecondaryButton>Documents</SecondaryButton>
                                </Link>
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
            <div className="py-8">
                <div className="mx-auto max-w-3xl border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70">
                    <div className="mb-6 flex items-start gap-4">
                        {worker.avatar_url ? (
                            <img
                                src={worker.avatar_url}
                                alt={worker.name}
                                className="h-20 w-20 shrink-0 object-cover border border-slate-200 dark:border-slate-700"
                            />
                        ) : (
                            <div className="flex h-20 w-20 shrink-0 items-center justify-center border border-slate-200 bg-slate-100 font-display text-2xl font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                {(worker.name || '?').charAt(0).toUpperCase()}
                            </div>
                        )}
                        <div>
                            <StatusBadge status={worker.role} />
                            <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{worker.name}</p>
                        </div>
                    </div>
                    <dl className="grid gap-5 sm:grid-cols-2">
                        <Field label={t('project')}>
                            {worker.project ? (
                                <Link href={route('projects.show', worker.project.id)} className="text-emerald-700 underline dark:text-emerald-400">
                                    {worker.project.name}
                                </Link>
                            ) : (
                                t('unassigned')
                            )}
                        </Field>
                        <Field label={t('phone')}>{worker.phone || '—'}</Field>
                        <Field label={t('daily_rate')}>{worker.daily_rate_usd}</Field>
                        <Field label={t('overtime_rate')}>{worker.overtime_rate_usd}</Field>
                        <Field label={t('spending_limit')}>{worker.spending_limit_usd}</Field>
                        <Field label={t('national_id')}>{worker.national_id_number || '—'}</Field>
                    </dl>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
