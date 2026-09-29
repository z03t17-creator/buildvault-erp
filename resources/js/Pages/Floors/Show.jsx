import PageHeader from '@/Components/PageHeader';
import SecondaryButton from '@/Components/SecondaryButton';
import PageShell from '@/Components/PageShell';
import DataPanel from '@/Components/DataPanel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Show({ floor, tower, project }) {
    const t = useTranslations();
    const canUpdate = useCan('floors.update');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={floor.name}
                    subtitle={`${project?.name} · ${tower?.name}`}
                    actions={
                        <>
                            <Link href={route('towers.floors.index', tower.id)}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            {canUpdate && (
                                <Link href={route('floors.edit', floor.id)}>
                                    <SecondaryButton>{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={floor.name} />
            <PageShell narrow>
                <DataPanel>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs uppercase tracking-wider text-slate-400">{t('tower')}</dt>
                            <dd className="mt-1">
                                <Link href={route('towers.show', tower.id)} className="font-medium text-emerald-700 underline dark:text-emerald-400">
                                    {tower?.name}
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wider text-slate-400">{t('project')}</dt>
                            <dd className="mt-1">
                                <Link href={route('projects.show', project.id)} className="font-medium text-emerald-700 underline dark:text-emerald-400">
                                    {project?.name}
                                </Link>
                            </dd>
                        </div>
                    </dl>
                </DataPanel>

                </PageShell>

                </AuthenticatedLayout>
    );
}
