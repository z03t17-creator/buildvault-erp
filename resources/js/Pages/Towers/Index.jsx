import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Index({ project, towers }) {
    const t = useTranslations();
    const list = towers || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('towers')}
                    subtitle={project?.name}
                    actions={
                        <>
                            <Link href={route('projects.show', project.id)}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            <Link href={route('projects.towers.create', project.id)}>
                                <PrimaryButton type="button">{t('create_tower')}</PrimaryButton>
                            </Link>
                        </>
                    }
                />
            }
        >
            <Head title={`${t('towers')} · ${project.name}`} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {list.length === 0 ? (
                        <EmptyState title={t('no_towers')} />
                    ) : (
                        <ul className="divide-y divide-slate-200 border border-slate-200/80 bg-white/80 dark:divide-slate-800 dark:border-slate-700 dark:bg-slate-900/70">
                            {list.map((tower) => (
                                <li key={tower.id}>
                                    <Link
                                        href={route('towers.show', tower.id)}
                                        className="flex items-center justify-between px-5 py-4 transition hover:bg-emerald-50/60 dark:hover:bg-emerald-950/20"
                                    >
                                        <span className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                            {tower.name}
                                        </span>
                                        <span className="text-sm text-slate-500">
                                            {tower.floors_count ?? 0} {t('floors_count').toLowerCase()}
                                        </span>
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
