import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{children}</dd>
        </div>
    );
}

export default function Show({ item }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canManage = useCan('stock.manageItems');
    const movements = item.movements || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={item.name}
                    subtitle={item.sku || t('stock_products')}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('stock.items.index')}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            {canManage && (
                                <>
                                    <Link href={route('stock.items.edit', item.id)}>
                                        <PrimaryButton type="button">{t('edit')}</PrimaryButton>
                                    </Link>
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => {
                                            if (confirm(t('confirm_delete'))) {
                                                router.delete(route('stock.items.destroy', item.id));
                                            }
                                        }}
                                    >
                                        {t('delete')}
                                    </SecondaryButton>
                                </>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={item.name} />
            <PageShell narrow>
                <DataPanel>
                    <dl className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <Field label={t('quantity')}>
                            <span className="tabular-nums text-lg font-semibold" dir="ltr">
                                {item.quantity} {item.unit}
                            </span>
                        </Field>
                        <Field label={t('min_quantity')}>
                            <span className="tabular-nums" dir="ltr">{item.min_quantity}</span>
                        </Field>
                        <Field label={`${t('purchase_price_iqd')} (${iqd})`}>
                            <MoneyAmount value={item.purchase_price_iqd} label={iqd} size="sm" showLabel={false} />
                        </Field>
                        <Field label={`${t('stock_value_iqd')} (${iqd})`}>
                            <MoneyAmount value={item.stock_value_iqd} label={iqd} size="sm" showLabel={false} />
                        </Field>
                        <Field label={t('supplier')}>{item.supplier?.name || '—'}</Field>
                        <Field label={t('location')}>{item.location || '—'}</Field>
                        <div className="sm:col-span-2">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{t('notes')}</dt>
                            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{item.notes || '—'}</dd>
                        </div>
                    </dl>
                </DataPanel>

                <DataPanel title={t('stock_movements')} padded={false}>
                    <DataTable caption={t('stock_movements')} minWidth="36rem">
                        <thead>
                            <tr>
                                <Th>{t('type')}</Th>
                                <Th align="end">{t('quantity')}</Th>
                                <Th>{t('date')}</Th>
                                <Th>{t('qty_change')}</Th>
                                <Th>{t('user')}</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.map((m) => (
                                <tr key={m.id}>
                                    <Td>
                                        <span className="uppercase">{m.type}</span>
                                    </Td>
                                    <Td align="end">
                                        <span className="tabular-nums" dir="ltr">{m.quantity}</span>
                                    </Td>
                                    <Td muted>{m.moved_on}</Td>
                                    <Td>
                                        <span className="tabular-nums" dir="ltr">
                                            {m.previous_qty} → {m.new_qty}
                                        </span>
                                    </Td>
                                    <Td muted>{m.user?.name || '—'}</Td>
                                </tr>
                            ))}
                            {!movements.length && (
                                <tr>
                                    <Td colSpan={5} align="center" muted>
                                        {t('no_stock_movements')}
                                    </Td>
                                </tr>
                            )}
                        </tbody>
                    </DataTable>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
