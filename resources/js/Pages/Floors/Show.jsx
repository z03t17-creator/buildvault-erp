import PageHeader from '@/Components/PageHeader';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Show({ floor, tower, project }) {
    const t = useTranslations();

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
                            <Link href={route('floors.edit', floor.id)}>
                                <SecondaryButton>{t('edit')}</SecondaryButton>
                            </Link>
                        </>
                    }
                />
            }
        >
            <Head title={floor.name} />
            <div className="py-8">
                <div className="mx-auto max-w-3xl border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70">
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
