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
import { Head, Link } from '@inertiajs/react';

function CountStat({ label, value, hint, tone = 'rose' }) {
    const iconBg =
        tone === 'amber'
            ? 'bg-amber-500/15 text-amber-950 dark:text-amber-200'
            : tone === 'emerald'
              ? 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300'
              : 'bg-rose-500/15 text-rose-900 dark:text-rose-200';

    return (
        <div className="bv-card px-4 py-3.5">
            <div className="flex items-start gap-3">
                <span
                    className={
                        'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ' +
                        iconBg
                    }
                >
                    <NavIcon name="stock" className="text-sm" />
                </span>
                <div className="min-w-0">
                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {label}
                    </div>
                    <div
                        dir="ltr"
                        className="mt-1 font-sans text-2xl font-semibold tabular-nums text-slate-900 dark:text-white"
                    >
                        {value}
                    </div>
                    {hint ? (
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
                    ) : null}
                </div>
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
    const categories = summary?.by_category || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_dashboard')}
                    subtitle={t('stock_dashboard_hint')}
                    icon={<NavIcon name="stock" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canIn && (
                                <Link href={route('stock.in.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-emerald-600 hover:!bg-emerald-500"
                                    >
                                        <NavIcon name="stockIn" className="text-sm" />
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
                                        <NavIcon name="stockOut" className="text-sm" />
                                        {t('stock_out_action')}
                                    </PrimaryButton>
                                </Link>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={t('stock_dashboard')} />
            <PageShell className="!space-y-6">
                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="stock" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('stock_products_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('stock_dashboard_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <CountStat label={t('stock_total_items')} value={summary?.total_items ?? 0} />
                        <CountStat
                            label={t('stock_value_iqd')}
                            value={
                                <MoneyAmount
                                    value={summary?.stock_value_iqd ?? 0}
                                    label={iqd}
                                    size="xl"
                                    showLabel={false}
                                    className="text-rose-900 dark:text-rose-100"
                                />
                            }
                        />
                        <CountStat
                            label={t('stock_low')}
                            value={summary?.low_stock ?? 0}
                            tone="amber"
                        />
                        <CountStat label={t('stock_out')} value={summary?.out_of_stock ?? 0} />
                        <CountStat
                            label={t('stock_today_in')}
                            value={summary?.today_in_qty ?? 0}
                            tone="emerald"
                        />
                        <CountStat
                            label={t('stock_today_out')}
                            value={summary?.today_out_qty ?? 0}
                            tone="amber"
                        />
                        <CountStat
                            label={t('stock_today_in_count')}
                            value={summary?.today_in_count ?? 0}
                            tone="emerald"
                        />
                        <CountStat
                            label={t('stock_today_out_count')}
                            value={summary?.today_out_count ?? 0}
                            tone="amber"
                        />
                    </div>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Link href={route('stock.items.index')}>
                            <SecondaryButton type="button">
                                <NavIcon name="stock" className="text-sm" />
                                {t('stock_products')}
                            </SecondaryButton>
                        </Link>
                        <Link href={route('stock.categories.index')}>
                            <SecondaryButton type="button">
                                <NavIcon name="stockCategories" className="text-sm" />
                                {t('stock_categories')}
                            </SecondaryButton>
                        </Link>
                        <Link href={route('stock.suppliers.index')}>
                            <SecondaryButton type="button">
                                <NavIcon name="suppliers" className="text-sm" />
                                {t('suppliers')}
                            </SecondaryButton>
                        </Link>
                        <Link href={route('stock.movements.index')}>
                            <SecondaryButton type="button">
                                <NavIcon name="stockMovements" className="text-sm" />
                                {t('stock_movements')}
                            </SecondaryButton>
                        </Link>
                        {canManage && (
                            <Link href={route('stock.items.create')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-rose-600 hover:!bg-rose-500"
                                >
                                    {t('new_product')}
                                </PrimaryButton>
                            </Link>
                        )}
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                <NavIcon name="stockCategories" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('role_panel_stock_by_category')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="24rem" caption={t('role_panel_stock_by_category')}>
                            <thead>
                                <tr>
                                    <Th>{t('category')}</Th>
                                    <Th align="end">{t('role_stat_items')}</Th>
                                    <Th align="end">{t('stock_value_iqd')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {categories.length === 0 ? (
                                    <tr>
                                        <Td muted colSpan={3}>
                                            {t('stock_categories_empty_title')}
                                        </Td>
                                    </tr>
                                ) : (
                                    categories.map((c) => (
                                        <tr key={c.category}>
                                            <Td>
                                                {c.category === 'uncategorized'
                                                    ? t('uncategorized')
                                                    : c.category}
                                            </Td>
                                            <Td align="end" className="font-sans tabular-nums">
                                                {c.items_count}
                                            </Td>
                                            <Td align="end" money>
                                                <MoneyAmount
                                                    value={c.value_iqd}
                                                    label={iqd}
                                                    size="sm"
                                                    showLabel={false}
                                                />
                                            </Td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </DataTable>
                    </DataPanel>

                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                <NavIcon name="stockMovements" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('stock_recent_movements')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="28rem" caption={t('stock_recent_movements')}>
                            <thead>
                                <tr>
                                    <Th>{t('type')}</Th>
                                    <Th>{t('product')}</Th>
                                    <Th align="end">{t('quantity')}</Th>
                                    <Th>{t('date')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {movements.length === 0 ? (
                                    <tr>
                                        <Td muted colSpan={4}>
                                            {t('no_stock_movements')}
                                        </Td>
                                    </tr>
                                ) : (
                                    movements.map((m) => (
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
                                            <Td>{m.item?.name || '—'}</Td>
                                            <Td
                                                align="end"
                                                className="font-sans font-semibold tabular-nums"
                                            >
                                                {m.quantity}
                                            </Td>
                                            <Td muted className="font-sans tabular-nums">
                                                {m.moved_on}
                                            </Td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                </div>
            </PageShell>
        </AuthenticatedLayout>
    );
}
