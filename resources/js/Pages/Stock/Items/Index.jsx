import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function MoneyStat({ label, value, currency, tone = 'default' }) {
    const tones = {
        default: 'text-slate-900 dark:text-white',
        rose: 'text-rose-800 dark:text-rose-200',
    };

    return (
        <div className="bv-card px-4 py-3.5">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div
                dir="ltr"
                className={
                    'mt-1.5 font-sans text-2xl font-semibold tracking-normal tabular-nums sm:text-3xl ' +
                    (tones[tone] || tones.default)
                }
            >
                {value == null ? (
                    '—'
                ) : (
                    <MoneyAmount
                        value={value}
                        label={currency}
                        size="xl"
                        showLabel={false}
                    />
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{currency}</p>
        </div>
    );
}

function CountStat({ label, value, hint, active = false, tone = 'rose' }) {
    const ring =
        tone === 'amber'
            ? 'ring-amber-500/40 dark:ring-amber-400/40'
            : tone === 'slate'
              ? 'ring-slate-400/40 dark:ring-slate-500/40'
              : 'ring-rose-500/40 dark:ring-rose-400/40';

    return (
        <div className={'bv-card px-4 py-3.5 ' + (active ? 'ring-2 ' + ring : '')}>
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1.5 font-sans text-2xl font-semibold tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                {value ?? 0}
            </div>
            {hint ? (
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            ) : null}
        </div>
    );
}

function StockStatusChip({ item, t }) {
    if (item.is_out_of_stock) {
        return (
            <span className="inline-flex items-center gap-1 rounded-lg bg-rose-500/15 px-2 py-1 text-xs font-semibold text-rose-900 dark:text-rose-200">
                <NavIcon name="stockOut" className="text-xs" />
                {t('stock_out')}
            </span>
        );
    }
    if (item.is_low_stock) {
        return (
            <span className="inline-flex items-center gap-1 rounded-lg bg-amber-500/15 px-2 py-1 text-xs font-semibold text-amber-950 dark:text-amber-200">
                <NavIcon name="stock" className="text-xs" />
                {t('stock_low')}
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-lg bg-emerald-500/15 px-2 py-1 text-xs font-semibold text-emerald-900 dark:text-emerald-300">
            <NavIcon name="stockIn" className="text-xs" />
            {t('stock_ok')}
        </span>
    );
}

function formatQty(value) {
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return Number.isInteger(n) ? String(n) : String(n);
}

export default function Index({ items, filters, categories, overview }) {
    const list = items || [];
    const canManage = useCan('stock.manageItems');
    const t = useTranslations();
    const iqd = t('IQD');
    const [search, setSearch] = useState(filters?.q || '');
    const activeCategory = filters?.category_id ? String(filters.category_id) : '';
    const stats = overview || {
        products: list.length,
        stock_value_iqd: 0,
        low_stock: 0,
        out_of_stock: 0,
        total_quantity: 0,
    };

    const applyFilters = (next) => {
        router.get(
            route('stock.items.index'),
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    };

    const categoryChips = [
        { key: '', label: t('all_categories') },
        ...(categories || []).map((c) => ({
            key: String(c.id),
            label: c.name,
        })),
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_products')}
                    subtitle={t('stock_products_page_hint')}
                    icon={<NavIcon name="stock" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('stock.dashboard')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-slate-700 hover:!bg-slate-600 dark:!bg-slate-200 dark:!text-slate-900"
                                >
                                    <NavIcon name="stock" className="text-sm" />
                                    {t('stock_dashboard')}
                                </PrimaryButton>
                            </Link>
                            <Link href={route('stock.categories.index')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-slate-600 hover:!bg-slate-500 dark:!bg-slate-300 dark:!text-slate-900"
                                >
                                    <NavIcon name="stockCategories" className="text-sm" />
                                    {t('stock_categories')}
                                </PrimaryButton>
                            </Link>
                            {canManage ? (
                                <Link href={route('stock.items.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-rose-600 hover:!bg-rose-500 dark:!bg-rose-400 dark:!text-rose-950 dark:hover:!bg-rose-300"
                                    >
                                        <NavIcon name="stockIn" className="text-sm" />
                                        {t('new_product')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </div>
                    }
                />
            }
        >
            <Head title={t('stock_products')} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('stock_products_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('stock_products_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('search')}
                            </label>
                            <TextInput
                                className={fieldClass}
                                placeholder={t('stock_products_search_placeholder')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onBlur={() => applyFilters({ q: search })}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyFilters({ q: search });
                                    }
                                }}
                            />
                        </div>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-1.5">
                        {categoryChips.map((chip) => {
                            const active = activeCategory === chip.key;
                            return (
                                <button
                                    key={chip.key || 'all'}
                                    type="button"
                                    onClick={() =>
                                        applyFilters({
                                            category_id: chip.key || null,
                                        })
                                    }
                                    className={
                                        'inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-semibold capitalize transition ' +
                                        (active
                                            ? 'bg-rose-600 text-white dark:bg-rose-400 dark:text-rose-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-rose-50 hover:text-rose-900 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-rose-950/40')
                                    }
                                >
                                    {chip.label}
                                </button>
                            );
                        })}
                    </div>
                </section>

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
                                {t('stock_products_overview_hint', {
                                    count: stats.products,
                                })}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <CountStat
                            label={t('stock_total_items')}
                            value={stats.products}
                            hint={t('stock_products_stat_items_hint')}
                        />
                        <CountStat
                            label={t('quantity')}
                            value={formatQty(stats.total_quantity)}
                            hint={t('stock_products_stat_qty_hint')}
                        />
                        <CountStat
                            label={t('stock_low')}
                            value={stats.low_stock}
                            hint={t('stock_products_stat_low_hint')}
                            tone="amber"
                            active={stats.low_stock > 0}
                        />
                        <CountStat
                            label={t('stock_out')}
                            value={stats.out_of_stock}
                            hint={t('stock_products_stat_out_hint')}
                            active={stats.out_of_stock > 0}
                        />
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-1">
                        <MoneyStat
                            label={t('stock_value_iqd')}
                            value={stats.stock_value_iqd}
                            currency={iqd}
                            tone="rose"
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="stock"
                        title={t('stock_products_empty_title')}
                        description={t('stock_products_empty_hint')}
                        action={
                            canManage ? (
                                <Link href={route('stock.items.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-rose-600 hover:!bg-rose-500"
                                    >
                                        <NavIcon name="stockIn" className="text-sm" />
                                        {t('new_product')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                <NavIcon name="stock" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('stock_products_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('stock_products_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable
                            minWidth="60rem"
                            caption={t('stock_products')}
                            stickyFirstColumn
                        >
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('sku')}</Th>
                                    <Th>{t('category')}</Th>
                                    <Th
                                        align="end"
                                        className="text-rose-800 dark:text-rose-300"
                                    >
                                        {t('quantity')}
                                    </Th>
                                    <Th align="end">{t('min_quantity')}</Th>
                                    <Th
                                        align="end"
                                        className="text-rose-800 dark:text-rose-300"
                                    >
                                        {t('purchase_price_iqd')}
                                    </Th>
                                    <Th
                                        align="end"
                                        className="text-rose-800 dark:text-rose-300"
                                    >
                                        {t('stock_value_iqd')}
                                    </Th>
                                    <Th>{t('stock_status')}</Th>
                                    <Th>{t('supplier')}</Th>
                                    <Th>{t('location')}</Th>
                                </tr>
                            </thead>
                            <tbody>
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
                                        <Td>
                                            <Link
                                                href={route(
                                                    'stock.items.show',
                                                    item.id,
                                                )}
                                                className="inline-flex items-center gap-2.5"
                                            >
                                                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                                    <NavIcon
                                                        name="stock"
                                                        className="text-sm"
                                                    />
                                                </span>
                                                <span className="font-medium text-rose-950 underline-offset-2 hover:underline dark:text-rose-100">
                                                    {item.name}
                                                </span>
                                            </Link>
                                        </Td>
                                        <Td
                                            muted
                                            className="font-sans tabular-nums"
                                        >
                                            {item.sku || '—'}
                                        </Td>
                                        <Td muted className="capitalize">
                                            {item.category_label ||
                                                item.stock_category?.name ||
                                                item.category ||
                                                '—'}
                                        </Td>
                                        <Td
                                            align="end"
                                            className="font-sans text-base font-semibold tabular-nums text-rose-900 dark:text-rose-100"
                                        >
                                            {formatQty(item.quantity)}
                                            <span className="ms-1 text-xs font-medium text-slate-400">
                                                {item.unit || ''}
                                            </span>
                                        </Td>
                                        <Td
                                            align="end"
                                            className="font-sans tabular-nums"
                                        >
                                            {formatQty(item.min_quantity)}
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={item.purchase_price_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                                className="text-rose-800 dark:text-rose-200"
                                            />
                                        </Td>
                                        <Td align="end" money>
                                            <MoneyAmount
                                                value={item.stock_value_iqd}
                                                label={iqd}
                                                size="sm"
                                                showLabel={false}
                                                className="font-semibold text-rose-900 dark:text-rose-100"
                                            />
                                        </Td>
                                        <Td>
                                            <StockStatusChip item={item} t={t} />
                                        </Td>
                                        <Td muted>
                                            {item.supplier?.name || '—'}
                                        </Td>
                                        <Td muted>{item.location || '—'}</Td>
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
