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
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ workers, filters, laborKinds }) {
    const t = useTranslations();
    const canCreate = useCan('workers.create');
    const list = workers || [];

    const apply = (labor_kind) => {
        router.get(route('workers.index'), { labor_kind }, { preserveState: true, replace: true });
    };

    const kindLabel = (kind) => {
        const key = `labor_kind_${kind || 'unclassified'}`;
        const translated = t(key);
        return translated !== key ? translated : kind;
    };

    const nameLabel = (worker) => {
        if (worker.labor_kind === 'staff') return t('staff_name');
        if (worker.labor_kind === 'worker') return t('worker_name');
        return t('name');
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('people')}
                    subtitle={t('crew_roster')}
                    actions={
                        canCreate ? (
                            <Link href={route('workers.create')}>
                                <PrimaryButton type="button">{t('create_person')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('people')} />
            <PageShell>
                <div className="mb-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        className={`rounded border px-3 py-1 text-sm ${!filters?.labor_kind ? 'border-emerald-600 bg-emerald-50' : 'border-slate-300'}`}
                        onClick={() => apply('')}
                    >
                        All
                    </button>
                    {(laborKinds || []).map((k) => (
                        <button
                            key={k}
                            type="button"
                            className={`rounded border px-3 py-1 text-sm ${filters?.labor_kind === k ? 'border-emerald-600 bg-emerald-50' : 'border-slate-300'}`}
                            onClick={() => apply(k)}
                        >
                            {kindLabel(k)}
                        </button>
                    ))}
                </div>

                {list.length === 0 ? (
                    <EmptyState
                        title={t('no_workers')}
                        description={t('no_workers_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('workers.create')}>
                                    <PrimaryButton type="button">{t('create_person')}</PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="44rem" caption={t('people')}>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th align="center">{t('labor_kind')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th align="end">{t('monthly_salary_usd')}</Th>
                                    <Th align="end">{t('monthly_salary_iqd')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((worker) => (
                                    <tr key={worker.id}>
                                        <Td className="text-left">
                                            <Link
                                                href={route('workers.show', worker.id)}
                                                className="font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                            >
                                                {worker.name}
                                            </Link>
                                            <span className="sr-only">{nameLabel(worker)}</span>
                                        </Td>
                                        <Td align="center">
                                            <StatusBadge status={worker.labor_kind || 'unclassified'} />
                                        </Td>
                                        <Td className="text-left">{worker.project?.name || '—'}</Td>
                                        <Td align="end" className="font-mono tabular-nums">
                                            <MoneyAmount value={worker.monthly_salary_usd} label="USD" size="sm" showLabel={false} />
                                        </Td>
                                        <Td align="end" className="font-mono tabular-nums">
                                            <MoneyAmount value={worker.monthly_salary_iqd} label="IQD" size="sm" showLabel={false} />
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
