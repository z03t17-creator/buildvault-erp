import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Index({ workers }) {
    const t = useTranslations();
    const canCreate = useCan('workers.create');
    const list = workers || [];
    const iqd = t('IQD');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('workers')}
                    subtitle={t('crew_roster')}
                    actions={
                        canCreate ? (
                            <Link href={route('workers.create')}>
                                <PrimaryButton type="button">{t('create_worker')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('workers')} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState
                        title={t('no_workers')}
                        description={t('no_workers_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('workers.create')}>
                                    <PrimaryButton type="button">{t('create_worker')}</PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="40rem" caption={t('workers')}>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('role')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th align="end">
                                        {t('daily_rate')} ({iqd})
                                    </Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((worker) => (
                                    <tr key={worker.id}>
                                        <Td>
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
                                        </Td>
                                        <Td>
                                            <StatusBadge status={worker.role} />
                                        </Td>
                                        <Td muted>{worker.project?.name || t('unassigned')}</Td>
                                        <Td align="end">
                                            <MoneyAmount
                                                value={worker.daily_rate_usd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
