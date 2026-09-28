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
                    subtitle={t('crew_roster')}
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
                        <div className="bv-surface">
                            <div className="bv-table-wrap">
                            <table className="bv-table min-w-[36rem]">
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
                                        <tr key={worker.id}>
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={route('workers.show', worker.id)}
                                                    className="inline-flex items-center gap-3 font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                                >
                                                    {worker.avatar_url ? (
                                                        <img
                                                            src={worker.avatar_url}
                                                            alt=""
                                                            className="h-8 w-8 object-cover border border-slate-200 dark:border-slate-700"
                                                        />
                                                    ) : (
                                                        <span className="flex h-8 w-8 items-center justify-center border border-slate-200 bg-slate-100 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            {(worker.name || '?').charAt(0).toUpperCase()}
                                                        </span>
                                                    )}
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
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
