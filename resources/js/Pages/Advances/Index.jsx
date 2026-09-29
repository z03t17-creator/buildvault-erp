import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MobileCardList, { MobileCard } from '@/Components/MobileCardList';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Index({ advances }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const list = advances || [];
    const canCreate = useCan('advances.create');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('advances')}
                    subtitle={t('advances_subtitle')}
                    actions={
                        canCreate ? (
                            <Link href={route('advances.create')}>
                                <PrimaryButton type="button">{t('record_advance')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('advances')} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState
                        title={t('advances_empty')}
                        action={
                            canCreate ? (
                                <Link href={route('advances.create')}>
                                    <PrimaryButton type="button">{t('record_advance')}</PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <MobileCardList>
                            {list.map((a) => (
                                <MobileCard
                                    key={a.id}
                                    href={route('advances.show', a.id)}
                                    title={a.worker?.name || '—'}
                                    subtitle={a.project?.name || a.advanced_on}
                                    badge={<StatusBadge status={a.status} />}
                                    rows={[
                                        {
                                            label: t('amount_iqd'),
                                            money: true,
                                            value: (
                                                <MoneyAmount
                                                    value={a.amount_iqd}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            ),
                                        },
                                        {
                                            label: t('remaining_iqd'),
                                            money: true,
                                            value: (
                                                <MoneyAmount
                                                    value={a.remaining_iqd}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                    className="text-amber-700 dark:text-amber-300"
                                                />
                                            ),
                                        },
                                    ]}
                                />
                            ))}
                        </MobileCardList>
                        <DataTable minWidth="44rem" hideOnMobile>
                            <thead>
                                <tr>
                                    <Th>{t('worker')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('date')}</Th>
                                    <Th align="end">{t('amount_iqd')}</Th>
                                    <Th align="end">{t('remaining_iqd')}</Th>
                                    <Th>{t('repayment_method')}</Th>
                                    <Th>{t('status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((a) => (
                                    <tr key={a.id}>
                                        <Td>
                                            <Link
                                                href={route('advances.show', a.id)}
                                                className="text-emerald-700 underline dark:text-emerald-400"
                                            >
                                                {a.worker?.name || '—'}
                                            </Link>
                                        </Td>
                                        <Td>{a.project?.name || '—'}</Td>
                                        <Td muted className="tabular-nums">
                                            {a.advanced_on}
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={a.amount_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                            />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={a.remaining_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                                className="text-amber-700 dark:text-amber-300"
                                            />
                                        </Td>
                                        <Td>
                                            {t(`repay_${a.repayment_method}`) || a.repayment_method}
                                        </Td>
                                        <Td>
                                            <StatusBadge status={a.status} />
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
