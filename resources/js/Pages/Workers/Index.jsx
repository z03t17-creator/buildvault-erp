import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Index({ workers }) {
    const t = useTranslations();
    const list = workers || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('workers')}
                    subtitle="Crew roster"
                    actions={
                        <Link href={route('workers.create')}>
                            <PrimaryButton type="button">{t('create_worker')}</PrimaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('workers')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {list.length === 0 ? (
                        <EmptyState
                            title={t('no_workers')}
                            description={t('no_workers_hint')}
                            action={
                                <Link href={route('workers.create')}>
                                    <PrimaryButton type="button">{t('create_worker')}</PrimaryButton>
                                </Link>
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto border border-slate-200/80 bg-white/80 dark:border-slate-700 dark:bg-slate-900/70">
                            <table className="min-w-full text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50/80 text-start text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">{t('name')}</th>
                                        <th className="px-4 py-3 font-semibold">{t('role')}</th>
                                        <th className="px-4 py-3 font-semibold">{t('project')}</th>
                                        <th className="px-4 py-3 font-semibold">{t('daily_rate')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {list.map((worker) => (
                                        <tr key={worker.id} className="hover:bg-emerald-50/40 dark:hover:bg-emerald-950/15">
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={route('workers.show', worker.id)}
                                                    className="font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                                >
                                                    {worker.name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={worker.role} />
                                            </td>
                                            <td className="px-4 py-3 text-slate-600 dark:text-slate-300">
                                                {worker.project?.name || t('unassigned')}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-slate-700 dark:text-slate-200">
                                                {worker.daily_rate_usd}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
