import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';
import PageShell from '@/Components/PageShell';

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
            <PageShell>
                    <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 border border-slate-200/80 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/70">
                        <div>
                            <dt className="text-xs uppercase text-slate-500">{t('quantity')}</dt>
                            <dd className="tabular-nums text-lg font-semibold">
                                {item.quantity} {item.unit}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-500">{t('min_quantity')}</dt>
                            <dd className="tabular-nums">{item.min_quantity}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-500">{t('purchase_price_iqd')}</dt>
                            <dd className="tabular-nums">{<MoneyAmount value={item.purchase_price_iqd} label={iqd} size="sm" />}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-500">{t('stock_value_iqd')}</dt>
                            <dd className="tabular-nums">{<MoneyAmount value={item.stock_value_iqd} label={iqd} size="sm" />}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-500">{t('supplier')}</dt>
                            <dd>{item.supplier?.name || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase text-slate-500">{t('location')}</dt>
                            <dd>{item.location || '—'}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-xs uppercase text-slate-500">{t('notes')}</dt>
                            <dd>{item.notes || '—'}</dd>
                        </div>
                    </dl>

                    <section>
                        <h3 className="mb-3 font-display text-lg font-semibold">{t('stock_movements')}</h3>
                        <div className="overflow-x-auto border border-slate-200/80 bg-white/80 dark:border-slate-700 dark:bg-slate-900/70">
                            <table className="min-w-full text-sm">
                                <thead className="border-b text-xs uppercase text-slate-500">
                                    <tr>
                                        <th className="px-3 py-2 text-start">{t('type')}</th>
                                        <th className="px-3 py-2 text-start">{t('quantity')}</th>
                                        <th className="px-3 py-2 text-start">{t('date')}</th>
                                        <th className="px-3 py-2 text-start">{t('qty_change')}</th>
                                        <th className="px-3 py-2 text-start">{t('user')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {movements.map((m) => (
                                        <tr key={m.id}>
                                            <td className="px-3 py-2 uppercase">{m.type}</td>
                                            <td className="px-3 py-2 tabular-nums">{m.quantity}</td>
                                            <td className="px-3 py-2">{m.moved_on}</td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {m.previous_qty} → {m.new_qty}
                                            </td>
                                            <td className="px-3 py-2">{m.user?.name || '—'}</td>
                                        </tr>
                                    ))}
                                    {!movements.length && (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-6 text-center text-slate-500">
                                                {t('no_stock_movements')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </PageShell>

                </AuthenticatedLayout>
    );
}
