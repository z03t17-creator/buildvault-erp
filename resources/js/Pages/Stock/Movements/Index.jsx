import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function CountStat({ label, value, hint, tone = 'rose' }) {
    const ring =
        tone === 'emerald'
            ? 'ring-emerald-500/40'
            : tone === 'amber'
              ? 'ring-amber-500/40'
              : 'ring-rose-500/40';

    return (
        <div className={'bv-card px-4 py-3.5 ring-2 ' + ring}>
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

export default function Index({ movements, filters, items, projects, overview }) {
    const list = movements || [];
    const t = useTranslations();
    const canIn = useCan('stock.stockIn');
    const canOut = useCan('stock.stockOut');
    const stats = overview || {
        movements: list.length,
        in_count: 0,
        out_count: 0,
        in_qty: 0,
        out_qty: 0,
    };

    const apply = (next) => {
        router.get(
            route('stock.movements.index'),
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('stock_movements')}
                    subtitle={t('stock_movements_page_hint')}
                    icon={<NavIcon name="stockMovements" className="text-lg" />}
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
            <Head title={t('stock_movements')} />
            <PageShell className="!space-y-6">
                <StockTabs />
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('stock_movements_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('stock_movements_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('type')}
                            </label>
                            <select
                                className={fieldClass}
                                value={filters?.type || ''}
                                onChange={(e) => apply({ type: e.target.value })}
                            >
                                <option value="">{t('all_types')}</option>
                                <option value="in">{t('stock_in')}</option>
                                <option value="out">{t('stock_out_action')}</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('product')}
                            </label>
                            <select
                                className={fieldClass}
                                value={filters?.stock_item_id || ''}
                                onChange={(e) => apply({ stock_item_id: e.target.value || null })}
                            >
                                <option value="">{t('all_products')}</option>
                                {(items || []).map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('project')}
                            </label>
                            <select
                                className={fieldClass}
                                value={filters?.project_id || ''}
                                onChange={(e) => apply({ project_id: e.target.value || null })}
                            >
                                <option value="">{t('all_projects')}</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="stockMovements" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('stock_movements_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('stock_movements_overview_hint', { count: stats.movements })}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <CountStat
                            label={t('stock_movements')}
                            value={stats.movements}
                            hint={t('stock_movements_stat_total_hint')}
                        />
                        <CountStat
                            label={t('stock_in')}
                            value={stats.in_count}
                            hint={`${stats.in_qty} ${t('quantity')}`}
                            tone="emerald"
                        />
                        <CountStat
                            label={t('stock_out_action')}
                            value={stats.out_count}
                            hint={`${stats.out_qty} ${t('quantity')}`}
                            tone="amber"
                        />
                        <CountStat
                            label={t('qty_change')}
                            value={`${stats.in_qty} / ${stats.out_qty}`}
                            hint={t('stock_movements_stat_qty_hint')}
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="stockMovements"
                        title={t('stock_movements_empty_title')}
                        description={t('stock_movements_empty_hint')}
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                <NavIcon name="stockMovements" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('stock_movements_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('stock_movements_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="52rem" caption={t('stock_movements')} stickyFirstColumn>
                            <thead>
                                <tr>
                                    <Th>{t('type')}</Th>
                                    <Th>{t('product')}</Th>
                                    <Th align="end" className="text-rose-800 dark:text-rose-300">
                                        {t('quantity')}
                                    </Th>
                                    <Th>{t('date')}</Th>
                                    <Th>{t('user')}</Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('reference')}</Th>
                                    <Th align="end">{t('qty_change')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((m) => (
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
                                                {m.type === 'in' ? t('stock_in') : t('stock_out_action')}
                                            </span>
                                        </Td>
                                        <Td>
                                            <span className="font-medium text-rose-950 dark:text-rose-100">
                                                {m.item?.name || '—'}
                                            </span>
                                        </Td>
                                        <Td
                                            align="end"
                                            className="font-sans text-base font-semibold tabular-nums text-rose-900 dark:text-rose-100"
                                        >
                                            {m.quantity}
                                        </Td>
                                        <Td muted className="font-sans tabular-nums">
                                            {m.moved_on}
                                        </Td>
                                        <Td muted>{m.user?.name || '—'}</Td>
                                        <Td muted>{m.project?.name || '—'}</Td>
                                        <Td muted>{m.reference || m.invoice_ref || '—'}</Td>
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
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
