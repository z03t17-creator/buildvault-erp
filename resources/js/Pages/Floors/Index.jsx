import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Index({ tower, project, floors }) {
    const t = useTranslations();
    const canCreate = useCan('floors.create');
    const list = floors || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('floors')}
                    subtitle={`${project?.name} · ${tower?.name}`}
                    actions={
                        <>
                            <Link href={route('towers.show', tower.id)}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            {canCreate && (
                                <Link href={route('towers.floors.create', tower.id)}>
                                    <PrimaryButton type="button">{t('create_floor')}</PrimaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={`${t('floors')} · ${tower.name}`} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {list.length === 0 ? (
                        <EmptyState title={t('no_floors')} />
                    ) : (
                        <ul className="divide-y divide-slate-200 border border-slate-200/80 bg-white/80 dark:divide-slate-800 dark:border-slate-700 dark:bg-slate-900/70">
                            {list.map((floor) => (
                                <li key={floor.id}>
                                    <Link
                                        href={route('floors.show', floor.id)}
                                        className="block px-5 py-4 font-display text-lg font-semibold text-slate-900 transition hover:bg-emerald-50/60 dark:text-white dark:hover:bg-emerald-950/20"
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
