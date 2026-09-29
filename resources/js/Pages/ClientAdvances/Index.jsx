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
    const canCreate = useCan('clientAdvances.create');
    const list = advances || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('client_advances')}
                    subtitle={t('money_in')}
                    actions={
                        canCreate ? (
                            <Link href={route('client-advances.create')}>
                                <PrimaryButton type="button">{t('new_client_advance')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('client_advances')} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState title={t('client_advances')} description="No client advances yet." />
                ) : (
                    <DataPanel padded={false}>
                        <MobileCardList>
                            {list.map((row) => (
                                <MobileCard
                                    key={row.id}
                                    href={route('client-advances.show', row.id)}
                                    title={row.client_name}
                                    subtitle={row.project?.name || row.received_on}
                                    badge={
                                        (row.retention_holds || []).length > 0 ? (
                                            <StatusBadge status="holding" />
                                        ) : null
                                    }
                                    rows={[
                                        {
                                            label: 'USD',
                                            money: true,
                                            value: (
                                                <MoneyAmount
                                                    value={row.amount_usd}
                                                    label="USD"
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            ),
                                        },
                                        {
                                            label: 'IQD',
                                            money: true,
                                            value: (
                                                <MoneyAmount
                                                    value={row.amount_iqd}
                                                    label="IQD"
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            ),
                                        },
                                    ]}
                                />
                            ))}
                        </MobileCardList>
                        <DataTable minWidth="48rem" hideOnMobile>
                            <thead>
                                <tr>
                                    <Th>{t('date')}</Th>
                                    <Th className="text-left">{t('client_name')}</Th>
                                    <Th className="text-left">{t('project')}</Th>
                                    <Th align="center">{t('currency')}</Th>
                                    <Th align="end">USD</Th>
                                    <Th align="end">IQD</Th>
                                    <Th align="center">10%/6mo</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((row) => (
                                    <tr key={row.id}>
                                        <Td className="font-mono tabular-nums">{row.received_on}</Td>
                                        <Td className="text-left">
                                            <Link
                                                href={route('client-advances.show', row.id)}
                                                className="text-emerald-800 underline dark:text-emerald-300"
                                            >
                                                {row.client_name}
                                            </Link>
                                        </Td>
                                        <Td className="text-left">{row.project?.name || '—'}</Td>
                                        <Td align="center">{row.currency}</Td>
                                        <Td align="end" money>
                                            <MoneyAmount value={row.amount_usd} label="USD" size="sm" showLabel={false} />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount value={row.amount_iqd} label="IQD" size="sm" showLabel={false} />
                                        </Td>
                                        <Td align="center">
                                            {(row.retention_holds || []).length > 0 ? (
                                                <StatusBadge status="holding" />
                                            ) : (
                                                '—'
                                            )}
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
