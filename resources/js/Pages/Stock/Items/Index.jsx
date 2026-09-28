import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ items, filters, categories }) {
    const list = items || [];
    const canManage = useCan('stock.manageItems');
    const t = useTranslations();
    const iqd = t('IQD');

    const applyFilters = (next) => {
        router.get(route('stock.items.index'), { ...filters, ...next }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_products')}
                    subtitle={t('stock_products_hint')}
                    actions={
                        canManage ? (
                            <Link href={route('stock.items.create')}>
                                <PrimaryButton type="button">{t('new_product')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('stock_products')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap gap-3">
                        <TextInput
                            className="max-w-xs"
                            placeholder={t('search')}
                            defaultValue={filters?.q || ''}
                            onBlur={(e) => applyFilters({ q: e.target.value })}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') applyFilters({ q: e.target.value });
                            }}
                        />
                        <select
                            className="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950"
                            value={filters?.category || ''}
                            onChange={(e) => applyFilters({ category: e.target.value })}
                        >
                            <option value="">{t('all_categories')}</option>
                            {(categories || []).map((c) => (
                                <option key={c} value={c}>
                                    {c}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="overflow-x-auto border border-slate-200/80 bg-white/80 dark:border-slate-700 dark:bg-slate-900/70">
                        <table className="min-w-full text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                <tr>
                                    <th className="px-3 py-2 text-start">{t('name')}</th>
                                    <th className="px-3 py-2 text-start">{t('sku')}</th>
                                    <th className="px-3 py-2 text-start">{t('category')}</th>
                                    <th className="px-3 py-2 text-start">{t('quantity')}</th>
                                    <th className="px-3 py-2 text-start">{t('min_quantity')}</th>
                                    <th className="px-3 py-2 text-start">{t('purchase_price_iqd')}</th>
                                    <th className="px-3 py-2 text-start">{t('stock_value_iqd')}</th>
                                    <th className="px-3 py-2 text-start">{t('supplier')}</th>
                                    <th className="px-3 py-2 text-start">{t('location')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {list.map((item) => (
                                    <tr
                                        key={item.id}
                                        className={
                                            item.is_out_of_stock
                                                ? 'bg-rose-50/60 dark:bg-rose-950/20'
                                                : item.is_low_stock
                                                  ? 'bg-amber-50/60 dark:bg-amber-950/20'
                                                  : ''
                                        }
                                    >
                                        <td className="px-3 py-2">
                                            <Link
                                                href={route('stock.items.show', item.id)}
                                                className="text-emerald-700 underline dark:text-emerald-400"
                                            >
                                                {item.name}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">{item.sku || '—'}</td>
                                        <td className="px-3 py-2">{item.category || '—'}</td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {item.quantity} {item.unit}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">{item.min_quantity}</td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {<MoneyAmount value={item.purchase_price_iqd} label={iqd} size="sm" />}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {<MoneyAmount value={item.stock_value_iqd} label={iqd} size="sm" />}
                                        </td>
                                        <td className="px-3 py-2">{item.supplier?.name || '—'}</td>
                                        <td className="px-3 py-2">{item.location || '—'}</td>
                                    </tr>
                                ))}
                                {!list.length && (
                                    <tr>
                                        <td colSpan={9} className="px-3 py-8 text-center text-slate-500">
                                            {t('no_products')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
