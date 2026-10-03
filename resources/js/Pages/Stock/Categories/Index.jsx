import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function CountStat({ label, value, hint, active = false }) {
    return (
        <div className={'bv-card px-4 py-3.5 ' + (active ? 'ring-2 ring-rose-500/40' : '')}>
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1.5 font-sans text-2xl font-semibold tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                {value ?? 0}
            </div>
            {hint ? <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p> : null}
        </div>
    );
}

export default function Index({ categories, filters, overview }) {
    const list = categories || [];
    const canManage = useCan('stock.manageItems');
    const t = useTranslations();
    const [search, setSearch] = useState(filters?.q || '');
    const stats = overview || {
        categories: list.length,
        products_linked: 0,
        empty_categories: 0,
    };

    const applyFilters = (next) => {
        router.get(route('stock.categories.index'), { ...filters, ...next }, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_categories')}
                    subtitle={t('stock_categories_page_hint')}
                    icon={<NavIcon name="stockCategories" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('stock.items.index')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-slate-700 hover:!bg-slate-600"
                                >
                                    <NavIcon name="stock" className="text-sm" />
                                    {t('stock_products')}
                                </PrimaryButton>
                            </Link>
                            {canManage ? (
                                <Link href={route('stock.categories.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-rose-600 hover:!bg-rose-500 dark:!bg-rose-400 dark:!text-rose-950"
                                    >
                                        <NavIcon name="stockCategories" className="text-sm" />
                                        {t('new_stock_category')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </div>
                    }
                />
            }
        >
            <Head title={t('stock_categories')} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('stock_categories_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('stock_categories_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div>
                        <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('search')}
                        </label>
                        <TextInput
                            className={fieldClass}
                            placeholder={t('stock_categories_search_placeholder')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onBlur={() => applyFilters({ q: search })}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') applyFilters({ q: search });
                            }}
                        />
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="stockCategories" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('stock_categories_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('stock_categories_overview_hint', { count: stats.categories })}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                        <CountStat
                            label={t('stock_categories')}
                            value={stats.categories}
                            hint={t('stock_categories_stat_total_hint')}
                            active
                        />
                        <CountStat
                            label={t('stock_products')}
                            value={stats.products_linked}
                            hint={t('stock_categories_stat_linked_hint')}
                        />
                        <CountStat
                            label={t('stock_categories_empty')}
                            value={stats.empty_categories}
                            hint={t('stock_categories_stat_empty_hint')}
                            active={stats.empty_categories > 0}
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="stockCategories"
                        title={t('stock_categories_empty_title')}
                        description={t('stock_categories_empty_hint')}
                        action={
                            canManage ? (
                                <Link href={route('stock.categories.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-rose-600 hover:!bg-rose-500"
                                    >
                                        {t('new_stock_category')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                <NavIcon name="stockCategories" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('stock_categories_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('stock_categories_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="40rem" caption={t('stock_categories')} stickyFirstColumn>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th align="end">{t('stock_products')}</Th>
                                    <Th>{t('notes')}</Th>
                                    {canManage && <Th>{t('actions')}</Th>}
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((c) => (
                                    <tr key={c.id}>
                                        <Td>
                                            <span className="inline-flex items-center gap-2.5">
                                                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                                    <NavIcon name="stockCategories" className="text-sm" />
                                                </span>
                                                <span className="font-medium text-rose-950 dark:text-rose-100">
                                                    {c.name}
                                                </span>
                                            </span>
                                        </Td>
                                        <Td
                                            align="end"
                                            className="font-sans text-base font-semibold tabular-nums text-rose-900 dark:text-rose-100"
                                        >
                                            {c.stock_items_count ?? 0}
                                        </Td>
                                        <Td muted className="max-w-xs truncate">
                                            {c.notes || '—'}
                                        </Td>
                                        {canManage && (
                                            <Td>
                                                <div className="flex flex-wrap gap-2">
                                                    <Link href={route('stock.categories.edit', c.id)}>
                                                        <SecondaryButton type="button">
                                                            <NavIcon name="edit" className="text-sm" />
                                                            {t('edit')}
                                                        </SecondaryButton>
                                                    </Link>
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() => {
                                                            if (confirm(t('stock_categories_delete_confirm'))) {
                                                                router.delete(
                                                                    route('stock.categories.destroy', c.id),
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <NavIcon name="trash" className="text-sm" />
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
