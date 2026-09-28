import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, usePage } from '@inertiajs/react';

function Meta({ label, value }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">{value ?? '—'}</dd>
        </div>
    );
}

function formatIqd(n, iqdLabel = 'IQD') {
    return (
        new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0,
        }).format(Number(n) || 0) +
        ' ' +
        iqdLabel
    );
}

export default function Show({ project, exchangeRate }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const rate = exchangeRate || 1310;
    const budgetIqd = Math.round(Number(project.total_budget_usd || 0) * Number(rate));
    const towers = project.towers || [];
    const workers = project.workers || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={project.name}
                    subtitle={project.location || t('project')}
                    actions={
                        <>
                            <Link href={route('projects.index')}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            <Link href={route('documents.index', { project_id: project.id })}>
                                <SecondaryButton>Documents</SecondaryButton>
                            </Link>
                            <Link href={route('projects.edit', project.id)}>
                                <SecondaryButton>{t('edit')}</SecondaryButton>
                            </Link>
                            <Link href={route('projects.towers.create', project.id)}>
                                <PrimaryButton type="button">{t('create_tower')}</PrimaryButton>
                            </Link>
                        </>
                    }
                />
            }
        >
            <Head title={project.name} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <section className="border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70">
                        <div className="mb-4 flex flex-wrap items-center gap-2">
                            <StatusBadge status={project.status} />
                        </div>
                        <p className="text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            {project.description || '—'}
                        </p>
                        <dl className="mt-6 grid gap-4 sm:grid-cols-3">
                            <Meta label={t('budget_iqd')} value={formatIqd(budgetIqd, iqd)} />
                            <Meta label={t('towers_count')} value={towers.length} />
                            <Meta label={t('workers_count')} value={workers.length} />
                        </dl>
                    </section>

                    <section>
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="font-display text-xl font-semibold text-slate-900 dark:text-white">
                                {t('towers')}
                            </h3>
                            <Link
                                href={route('projects.towers.index', project.id)}
                                className="text-sm font-medium text-emerald-700 underline dark:text-emerald-400"
                            >
                                {t('towers')}
                            </Link>
                        </div>
                        {towers.length === 0 ? (
                            <EmptyState title={t('no_towers')} />
                        ) : (
                            <ul className="divide-y divide-slate-200 border border-slate-200/80 bg-white/80 dark:divide-slate-800 dark:border-slate-700 dark:bg-slate-900/70">
                                {towers.map((tower) => (
                                    <li key={tower.id}>
                                        <Link
                                            href={route('towers.show', tower.id)}
                                            className="flex items-center justify-between px-5 py-3 transition hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20"
                                        >
                                            <span className="font-medium text-slate-900 dark:text-white">
                                                {tower.name}
                                            </span>
                                            <span className="text-sm text-slate-500">
                                                {(tower.floors || []).length} {t('floors_count').toLowerCase()}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
