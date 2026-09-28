import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

function Stat({ label, value }) {
    return (
        <div className="border border-slate-200/80 bg-white/90 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
            <div className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1 font-sans text-2xl font-semibold tracking-normal tabular-nums text-slate-900 dark:text-white">
                {value}
            </div>
        </div>
    );
}

export default function Dashboard({ summary, recentMovements }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const canIn = useCan('stock.stockIn');
    const canOut = useCan('stock.stockOut');
    const canManage = useCan('stock.manageItems');
    const movements = recentMovements || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_dashboard')}
                    subtitle={t('stock_dashboard_hint')}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canIn && (
                                <Link href={route('stock.in.create')}>
                                    <PrimaryButton type="button">{t('stock_in')}</PrimaryButton>
                                </Link>
                            )}
                            {canOut && (
                                <Link href={route('stock.out.create')}>
                                    <PrimaryButton type="button">{t('stock_out_action')}</PrimaryButton>
                                </Link>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={t('stock_dashboard')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat label={t('stock_total_items')} value={summary?.total_items ?? 0} />
                        <Stat
                            label={t('stock_value_iqd')}
                            value={<MoneyAmount value={summary?.stock_value_iqd ?? 0} label={iqd} size="xl" />}
                        />
                        <Stat label={t('stock_low')} value={summary?.low_stock ?? 0} />
                        <Stat label={t('stock_out')} value={summary?.out_of_stock ?? 0} />
                        <Stat label={t('stock_today_in')} value={summary?.today_in_qty ?? 0} />
                        <Stat label={t('stock_today_out')} value={summary?.today_out_qty ?? 0} />
                        <Stat label={t('stock_today_in_count')} value={summary?.today_in_count ?? 0} />
                        <Stat label={t('stock_today_out_count')} value={summary?.today_out_count ?? 0} />
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('stock.items.index')}
                            className="inline-flex border border-slate-200 bg-white px-3 py-2 text-sm font-medium dark:border-slate-700 dark:bg-slate-900"
                        >
                            {t('stock_products')}
                        </Link>
                        <Link
                            href={route('stock.suppliers.index')}
                            className="inline-flex border border-slate-200 bg-white px-3 py-2 text-sm font-medium dark:border-slate-700 dark:bg-slate-900"
                        >
                            {t('suppliers')}
                        </Link>
                        <Link
                            href={route('stock.movements.index')}
                            className="inline-flex border border-slate-200 bg-white px-3 py-2 text-sm font-medium dark:border-slate-700 dark:bg-slate-900"
                        >
                            {t('stock_movements')}
                        </Link>
                        {canManage && (
                            <Link
                                href={route('stock.items.create')}
                                className="inline-flex border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100"
                            >
                                {t('new_product')}
                            </Link>
                        )}
                    </div>

                    <section>
                        <h3 className="mb-3 font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('stock_recent_movements')}
                        </h3>
                        <div className="overflow-x-auto border border-slate-200/80 bg-white/80 dark:border-slate-700 dark:bg-slate-900/70">
                            <table className="min-w-full text-sm">
                                <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                    <tr>
                                        <th className="px-3 py-2 text-start">{t('type')}</th>
                                        <th className="px-3 py-2 text-start">{t('product')}</th>
                                        <th className="px-3 py-2 text-start">{t('quantity')}</th>
                                        <th className="px-3 py-2 text-start">{t('date')}</th>
                                        <th className="px-3 py-2 text-start">{t('user')}</th>
                                        <th className="px-3 py-2 text-start">{t('project')}</th>
                                        <th className="px-3 py-2 text-start">{t('qty_change')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {movements.map((m) => (
                                        <tr key={m.id}>
                                            <td className="px-3 py-2 uppercase">{m.type}</td>
                                            <td className="px-3 py-2">{m.item?.name || '—'}</td>
                                            <td className="px-3 py-2 tabular-nums">{m.quantity}</td>
                                            <td className="px-3 py-2 tabular-nums">{m.moved_on}</td>
                                            <td className="px-3 py-2">{m.user?.name || '—'}</td>
                                            <td className="px-3 py-2">{m.project?.name || '—'}</td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {m.previous_qty} → {m.new_qty}
                                            </td>
                                        </tr>
                                    ))}
                                    {!movements.length && (
                                        <tr>
                                            <td colSpan={7} className="px-3 py-8 text-center text-slate-500">
                                                {t('no_stock_movements')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
