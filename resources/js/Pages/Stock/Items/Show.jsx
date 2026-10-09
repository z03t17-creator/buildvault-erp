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
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';

function Field({ label, children }) {
    return (
        <div className="bv-card px-4 py-3.5">
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {label}
            </dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                {children}
            </dd>
        </div>
    );
}

export default function Show({ item }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canManage = useCan('stock.manageItems');
    const canIn = useCan('stock.stockIn');
    const canOut = useCan('stock.stockOut');
    const movements = item.movements || [];
    const categoryLabel =
        item.category_label || item.stock_category?.name || item.category || '—';

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={item.name}
                    subtitle={item.sku || t('stock_products')}
                    icon={<NavIcon name="stock" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('stock.items.index')}>
                                <SecondaryButton type="button">{t('back')}</SecondaryButton>
                            </Link>
                            {canIn && (
                                <Link href={route('stock.in.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-emerald-600 hover:!bg-emerald-500"
                                    >
                                        {t('stock_in')}
                                    </PrimaryButton>
                                </Link>
                            )}
                            {canOut && (
                                <Link href={route('stock.out.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-amber-600 hover:!bg-amber-500"
                                    >
                                        {t('stock_out_action')}
                                    </PrimaryButton>
                                </Link>
                            )}
                            {canManage && (
                                <>
                                    <Link href={route('stock.items.edit', item.id)}>
                                        <PrimaryButton
                                            type="button"
                                            className="!bg-rose-600 hover:!bg-rose-500"
                                        >
                                            {t('edit')}
                                        </PrimaryButton>
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
            <PageShell className="!space-y-6">
                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label={t('quantity')}>
                        <span className="font-sans text-2xl font-semibold tabular-nums text-rose-900 dark:text-rose-100" dir="ltr">
                            {item.quantity}{' '}
                            <span className="text-sm font-medium text-slate-400">{item.unit}</span>
                        </span>
                    </Field>
                    <Field label={t('min_quantity')}>
                        <span className="font-sans text-lg tabular-nums" dir="ltr">
                            {item.min_quantity}
                        </span>
                    </Field>
                    <Field label={`${t('purchase_price_iqd')} (${iqd})`}>
                        <MoneyAmount
                            value={item.purchase_price_iqd}
                            label={iqd}
                            size="lg"
                            showLabel={false}
                            className="text-rose-800 dark:text-rose-200"
                        />
                    </Field>
                    <Field label={`${t('stock_value_iqd')} (${iqd})`}>
                        <MoneyAmount
                            value={item.stock_value_iqd}
                            label={iqd}
                            size="lg"
                            showLabel={false}
                            className="font-semibold text-rose-900 dark:text-rose-100"
                        />
                    </Field>
                    <Field label={t('category')}>{categoryLabel}</Field>
                    <Field label={t('supplier')}>{item.supplier?.name || '—'}</Field>
                    <Field label={t('location')}>{item.location || '—'}</Field>
                    <Field label={t('notes')}>{item.notes || '—'}</Field>
                </section>

                <DataPanel padded={false}>
                    <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="stockMovements" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('stock_recent_movements')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('stock_product_movements_hint')}
                            </p>
                        </div>
                    </div>
                    {movements.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-slate-500">{t('no_stock_movements')}</p>
                    ) : (
                        <DataTable minWidth="40rem" caption={t('stock_movements')} stickyFirstColumn>
                            <thead>
                                <tr>
                                    <Th>{t('type')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                    <Th>{t('date')}</Th>
                                    <Th>{t('user')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th align="end">{t('qty_change')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {movements.map((m) => (
                                    <tr key={m.id}>
                                        <Td>
                                            <span
                                                className={
                                                    'inline-flex rounded-lg px-2 py-1 text-xs font-semibold uppercase ' +
                                                    (m.type === 'in'
                                                        ? 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300'
                                                        : 'bg-amber-500/15 text-amber-950 dark:text-amber-200')
                                                }
                                            >
                                                {m.type}
                                            </span>
                                        </Td>
                                        <Td align="end" className="font-sans font-semibold tabular-nums">
                                            {m.quantity}
                                        </Td>
                                        <Td muted className="font-sans tabular-nums">
                                            {m.moved_on}
                                        </Td>
                                        <Td muted>{m.user?.name || '—'}</Td>
                                        <Td muted>{m.project?.name || '—'}</Td>
                                        <Td
                                            align="end"
                                            className="font-sans tabular-nums text-slate-600 dark:text-slate-300"
                                        >
                                            {m.previous_qty} → {m.new_qty}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
