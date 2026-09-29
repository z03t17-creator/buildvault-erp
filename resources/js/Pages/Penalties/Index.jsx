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

export default function Index({ penalties }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const list = penalties || [];
    const canCreate = useCan('penalties.create');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('penalties')}
                    subtitle={t('penalties_subtitle')}
                    actions={
                        canCreate ? (
                            <Link href={route('penalties.create')}>
                                <PrimaryButton type="button">{t('record_penalty')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('penalties')} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState
                        title={t('no_penalties')}
                        action={
                            canCreate ? (
                                <Link href={route('penalties.create')}>
                                    <PrimaryButton type="button">{t('record_penalty')}</PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="48rem">
                            <thead>
                                <tr>
                                    <Th>{t('worker')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('penalty_type')}</Th>
                                    <Th>{t('date')}</Th>
                                    <Th align="end">{t('amount_iqd')}</Th>
                                    <Th>{t('status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((p) => (
                                    <tr key={p.id}>
                                        <Td>
                                            <Link
                                                href={route('penalties.show', p.id)}
                                                className="text-emerald-700 underline dark:text-emerald-400"
                                            >
                                                {p.worker?.name || '—'}
                                            </Link>
                                            {p.reason && (
                                                <div className="max-w-xs truncate text-[11px] text-slate-400">
                                                    {p.reason}
                                                </div>
                                            )}
                                        </Td>
                                        <Td>{p.project?.name || '—'}</Td>
                                        <Td>{t(`penalty_type_${p.type}`) || p.type}</Td>
                                        <Td muted className="tabular-nums">
                                            {p.occurred_on || '—'}
                                        </Td>
                                        <Td align="end">
                                            <MoneyAmount
                                                value={p.amount_iqd_display ?? p.amount_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                                className="text-rose-700 dark:text-rose-300"
                                            />
                                        </Td>
                                        <Td>
                                            <StatusBadge status={p.status} />
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
