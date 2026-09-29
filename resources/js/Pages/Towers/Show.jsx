import DataPanel from '@/Components/DataPanel';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Show({ tower, project }) {
    const t = useTranslations();
    const canUpdate = useCan('towers.update');
    const canCreateFloor = useCan('floors.create');
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
                            {canUpdate && (
                                <Link href={route('towers.edit', tower.id)}>
                                    <SecondaryButton>{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                            {canCreateFloor && (
                                <Link href={route('towers.floors.create', tower.id)}>
                                    <PrimaryButton type="button">{t('create_floor')}</PrimaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={tower.name} />
            <PageShell narrow>
                <DataPanel title={t('floors')} padded={floors.length === 0}>
                    {floors.length === 0 ? (
                        <EmptyState title={t('no_floors')} />
                    ) : (
                        <ul className="divide-y divide-slate-200 dark:divide-slate-800">
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
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
