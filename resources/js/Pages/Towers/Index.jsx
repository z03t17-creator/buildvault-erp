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

export default function Index({ project, towers }) {
    const t = useTranslations();
    const canCreate = useCan('towers.create');
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
                            {canCreate && (
                                <Link href={route('projects.towers.create', project.id)}>
                                    <PrimaryButton type="button">{t('create_tower')}</PrimaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`${t('towers')} · ${project.name}`} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState title={t('no_towers')} />
                ) : (
                    <DataPanel padded={false}>
                        <ul className="divide-y divide-slate-200 dark:divide-slate-800">
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
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
