import DataPanel from '@/Components/DataPanel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{children}</dd>
        </div>
    );
}

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
                    <dl className="grid gap-6 sm:grid-cols-2">
                        <Field label={t('tower')}>
                            <Link href={route('towers.show', tower.id)} className="text-emerald-700 underline dark:text-emerald-400">
                                {tower?.name}
                            </Link>
                        </Field>
                        <Field label={t('project')}>
                            <Link href={route('projects.show', project.id)} className="text-emerald-700 underline dark:text-emerald-400">
                                {project?.name}
                            </Link>
                        </Field>
                    </dl>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
