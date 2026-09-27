import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Show({ tower, project }) {
    const t = useTranslations();
    const floors = tower.floors || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={tower.name}
                    subtitle={project?.name}
                    actions={
                        <>
                            <Link href={route('projects.towers.index', project.id)}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            <Link href={route('towers.edit', tower.id)}>
                                <SecondaryButton>{t('edit')}</SecondaryButton>
                            </Link>
                            <Link href={route('towers.floors.create', tower.id)}>
                                <PrimaryButton type="button">{t('create_floor')}</PrimaryButton>
                            </Link>
                        </>
                    }
                />
            }
        >
            <Head title={tower.name} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <h3 className="font-display text-xl font-semibold text-slate-900 dark:text-white">
                        {t('floors')}
                    </h3>
                    {floors.length === 0 ? (
                        <EmptyState title={t('no_floors')} />
                    ) : (
                        <ul className="divide-y divide-slate-200 border border-slate-200/80 bg-white/80 dark:divide-slate-800 dark:border-slate-700 dark:bg-slate-900/70">
                            {floors.map((floor) => (
                                <li key={floor.id}>
                                    <Link
                                        href={route('floors.show', floor.id)}
                                        className="block px-5 py-3 font-medium text-emerald-800 transition hover:bg-emerald-50/60 dark:text-emerald-300 dark:hover:bg-emerald-950/20"
                                    >
                                        {floor.name}
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
