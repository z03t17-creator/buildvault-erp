import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Index({ projects }) {
    const t = useTranslations();
    const list = projects || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('projects')}
                    subtitle="BuildVault · site hierarchy"
                    actions={
                        <Link href={route('projects.create')}>
                            <PrimaryButton type="button">{t('create_project')}</PrimaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('projects')} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {list.length === 0 ? (
                        <EmptyState
                            title={t('no_projects')}
                            description={t('no_projects_hint')}
                            action={
                                <Link href={route('projects.create')}>
                                    <PrimaryButton type="button">{t('create_project')}</PrimaryButton>
                                </Link>
                            }
                        />
                    ) : (
                        <ul className="bv-surface divide-y divide-slate-200 dark:divide-slate-800">
                            {list.map((project) => (
                                <li key={project.id}>
                                    <Link
                                        href={route('projects.show', project.id)}
                                        className="flex flex-col gap-3 px-5 py-4 transition duration-200 hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-display text-xl font-semibold text-slate-900 dark:text-white">
                                                    {project.name}
                                                </span>
                                                <StatusBadge status={project.status} />
                                            </div>
                                            <p className="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">
                                                {project.location || t('location') + ': —'}
                                            </p>
                                        </div>
                                        <div className="flex gap-6 text-sm text-slate-600 dark:text-slate-300">
                                            <div>
                                                <span className="block text-xs uppercase tracking-wider text-slate-400">
                                                    {t('towers_count')}
                                                </span>
                                                <span className="font-semibold">{project.towers_count ?? 0}</span>
                                            </div>
                                            <div>
                                                <span className="block text-xs uppercase tracking-wider text-slate-400">
                                                    {t('workers_count')}
                                                </span>
                                                <span className="font-semibold">{project.workers_count ?? 0}</span>
                                            </div>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
