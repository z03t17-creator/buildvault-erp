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

function tr(t, key, fallback) {
    const value = t(key);
    return value === key ? fallback : value;
}

export default function Index({ payouts }) {
    const list = payouts || [];
    const canCreate = useCan('payouts.create');
    const t = useTranslations();
    const iqd = tr(t, 'IQD', 'IQD');
    const usd = tr(t, 'USD', 'USD');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={tr(t, 'payouts', 'Payouts')}
                    subtitle={tr(t, 'payouts_subtitle', 'Vault ledger approvals')}
                    actions={
                        canCreate ? (
                            <Link href={route('payouts.create')}>
                                <PrimaryButton type="button">
                                    {tr(t, 'new_payout', 'New payout')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={tr(t, 'payouts', 'Payouts')} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState
                        title={tr(t, 'no_payouts_yet', 'No payouts yet.')}
                        action={
                            canCreate ? (
                                <Link href={route('payouts.create')}>
                                    <PrimaryButton type="button">
                                        {tr(t, 'new_payout', 'New payout')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <MobileCardList>
                            {list.map((p) => (
                                <MobileCard
                                    key={p.id}
                                    href={route('payouts.show', p.id)}
                                    title={`#${p.id}`}
                                    subtitle={p.project?.name || p.category}
                                    badge={<StatusBadge status={p.status} />}
                                    rows={[
                                        {
                                            label: tr(t, 'category', 'Category'),
                                            value: p.category,
                                        },
                                        {
                                            label: tr(t, 'amount_iqd', `Amount (${iqd})`),
                                            money: true,
                                            value:
                                                p.amount_iqd != null ? (
                                                    <MoneyAmount
                                                        value={p.amount_iqd}
                                                        label={iqd}
                                                        size="sm"
                                                        showLabel={false}
                                                    />
                                                ) : (
                                                    <MoneyAmount
                                                        value={p.amount_usd}
                                                        label={usd}
                                                        size="sm"
                                                        showLabel
                                                    />
                                                ),
                                        },
                                    ]}
                                />
                            ))}
                        </MobileCardList>
                        <DataTable minWidth="40rem" caption={tr(t, 'payouts', 'Payouts')} hideOnMobile>
                            <thead>
                                <tr>
                                    <Th>{tr(t, 'id', 'ID')}</Th>
                                    <Th>{tr(t, 'project', 'Project')}</Th>
                                    <Th>{tr(t, 'category', 'Category')}</Th>
                                    <Th align="end">{tr(t, 'amount_iqd', `Amount (${iqd})`)}</Th>
                                    <Th>{tr(t, 'status', 'Status')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((p) => (
                                    <tr key={p.id}>
                                        <Td>
                                            <Link
                                                href={route('payouts.show', p.id)}
                                                className="font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                            >
                                                #{p.id}
                                            </Link>
                                        </Td>
                                        <Td muted>{p.project?.name || '—'}</Td>
                                        <Td className="capitalize">{p.category}</Td>
                                        <Td align="end" money>
                                            {p.amount_iqd != null ? (
                                                <MoneyAmount
                                                    value={p.amount_iqd}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            ) : (
                                                <MoneyAmount
                                                    value={p.amount_usd}
                                                    label={usd}
                                                    size="sm"
                                                    showLabel
                                                />
                                            )}
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
