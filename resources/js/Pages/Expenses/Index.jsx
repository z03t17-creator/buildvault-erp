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

export default function Index({ expenses }) {
    const list = expenses || [];
    const canCreate = useCan('expenses.create');
    const t = useTranslations();
    const iqd = t('IQD');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('expenses')}
                    subtitle={t('expenses_subtitle')}
                    actions={
                        canCreate ? (
                            <Link href={route('expenses.create')}>
                                <PrimaryButton type="button">{t('new_expense')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('expenses')} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState
                        title={t('no_expenses')}
                        description={t('expenses_subtitle')}
                        action={
                            canCreate ? (
                                <Link href={route('expenses.create')}>
                                    <PrimaryButton type="button">{t('new_expense')}</PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="44rem" caption={t('expenses')}>
                            <thead>
                                <tr>
                                    <Th>{t('id')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('category')}</Th>
                                    <Th align="end">{t('amount_iqd')}</Th>
                                    <Th>{t('expense_date')}</Th>
                                    <Th>{t('status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((e) => (
                                    <tr key={e.id}>
                                        <Td>
                                            <Link
                                                href={route('expenses.show', e.id)}
                                                className="font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                            >
                                                #{e.id}
                                            </Link>
                                        </Td>
                                        <Td muted>{e.project?.name || '—'}</Td>
                                        <Td className="capitalize">
                                            {t(`expense_category_${e.category}`) !==
                                            `expense_category_${e.category}`
                                                ? t(`expense_category_${e.category}`)
                                                : e.category}
                                        </Td>
                                        <Td align="end">
                                            {e.amount_iqd == null ? (
                                                '—'
                                            ) : (
                                                <MoneyAmount
                                                    value={e.amount_iqd}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            )}
                                        </Td>
                                        <Td muted className="tabular-nums">
                                            {typeof e.expense_date === 'string'
                                                ? e.expense_date.slice(0, 10)
                                                : e.expense_date}
                                        </Td>
                                        <Td>
                                            <StatusBadge status={e.approval_status} />
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
