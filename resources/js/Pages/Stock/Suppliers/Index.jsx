import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ suppliers }) {
    const list = suppliers || [];
    const canManage = useCan('stock.manageSuppliers');
    const t = useTranslations();

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('suppliers')}
                    subtitle={t('suppliers_hint')}
                    actions={
                        canManage ? (
                            <Link href={route('stock.suppliers.create')}>
                                <PrimaryButton type="button">{t('new_supplier')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('suppliers')} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState title={t('no_suppliers')} description={t('suppliers_hint')} />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="44rem" caption={t('suppliers')}>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('contact_name')}</Th>
                                    <Th>{t('phone')}</Th>
                                    <Th>{t('email')}</Th>
                                    <Th>{t('notes')}</Th>
                                    {canManage && <Th>{t('actions')}</Th>}
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((s) => (
                                    <tr key={s.id}>
                                        <Td className="font-medium">{s.name}</Td>
                                        <Td muted>{s.contact_name || '—'}</Td>
                                        <Td muted dir="ltr">
                                            {s.phone || '—'}
                                        </Td>
                                        <Td muted dir="ltr">
                                            {s.email || '—'}
                                        </Td>
                                        <Td muted className="max-w-xs truncate">
                                            {s.notes || '—'}
                                        </Td>
                                        {canManage && (
                                            <Td>
                                                <div className="flex flex-wrap gap-2">
                                                    <Link href={route('stock.suppliers.edit', s.id)}>
                                                        <SecondaryButton type="button">{t('edit')}</SecondaryButton>
                                                    </Link>
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() => {
                                                            if (confirm(t('confirm_delete'))) {
                                                                router.delete(
                                                                    route('stock.suppliers.destroy', s.id),
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        {t('delete')}
                                                    </SecondaryButton>
                                                </div>
                                            </Td>
                                        )}
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
