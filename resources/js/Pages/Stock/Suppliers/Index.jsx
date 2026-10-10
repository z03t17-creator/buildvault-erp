import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';

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

export default function Index({ suppliers, overview }) {
    const list = suppliers || [];
    const canManage = useCan('stock.manageSuppliers');
    const t = useTranslations();
    const stats = overview || {
        suppliers: list.length,
        with_phone: 0,
        with_email: 0,
        products_linked: 0,
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('suppliers')}
                    subtitle={t('suppliers_page_hint')}
                    icon={<NavIcon name="suppliers" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('stock.dashboard')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-slate-700 hover:!bg-slate-600"
                                >
                                    {t('stock_dashboard')}
                                </PrimaryButton>
                            </Link>
                            {canManage ? (
                                <Link href={route('stock.suppliers.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-rose-600 hover:!bg-rose-500 dark:!bg-rose-400 dark:!text-rose-950"
                                    >
                                        <NavIcon name="suppliers" className="text-sm" />
                                        {t('new_supplier')}
                                    </PrimaryButton>
                                </Link>
                            ) : null}
                        </div>
                    }
                />
            }
        >
            <Head title={t('suppliers')} />
            <PageShell className="!space-y-6">
                <StockTabs />
                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="suppliers" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('suppliers_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('suppliers_overview_hint', { count: stats.suppliers })}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <CountStat
                            label={t('suppliers')}
                            value={stats.suppliers}
                            hint={t('suppliers_stat_total_hint')}
                            active
                        />
                        <CountStat
                            label={t('phone')}
                            value={stats.with_phone}
                            hint={t('suppliers_stat_phone_hint')}
                        />
                        <CountStat
                            label={t('email')}
                            value={stats.with_email}
                            hint={t('suppliers_stat_email_hint')}
                        />
                        <CountStat
                            label={t('stock_products')}
                            value={stats.products_linked}
                            hint={t('suppliers_stat_linked_hint')}
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="suppliers"
                        title={t('suppliers_empty_title')}
                        description={t('suppliers_empty_hint')}
                        action={
                            canManage ? (
                                <Link href={route('stock.suppliers.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-rose-600 hover:!bg-rose-500"
                                    >
                                        {t('new_supplier')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                <NavIcon name="suppliers" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('suppliers_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('suppliers_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="48rem" caption={t('suppliers')} stickyFirstColumn>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('contact_name')}</Th>
                                    <Th>{t('phone')}</Th>
                                    <Th>{t('email')}</Th>
                                    <Th align="end">{t('stock_products')}</Th>
                                    <Th>{t('notes')}</Th>
                                    {canManage && <Th>{t('actions')}</Th>}
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((s) => (
                                    <tr key={s.id}>
                                        <Td>
                                            <span className="inline-flex items-center gap-2.5">
                                                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                                                    <NavIcon name="suppliers" className="text-sm" />
                                                </span>
                                                <span className="font-medium text-rose-950 dark:text-rose-100">
                                                    {s.name}
                                                </span>
                                            </span>
                                        </Td>
                                        <Td muted>{s.contact_name || '—'}</Td>
                                        <Td muted dir="ltr" className="font-sans tabular-nums">
                                            {s.phone || '—'}
                                        </Td>
                                        <Td muted dir="ltr">
                                            {s.email || '—'}
                                        </Td>
                                        <Td
                                            align="end"
                                            className="font-sans font-semibold tabular-nums text-rose-900 dark:text-rose-100"
                                        >
                                            {s.stock_items_count ?? 0}
                                        </Td>
                                        <Td muted className="max-w-xs truncate">
                                            {s.notes || '—'}
                                        </Td>
                                        {canManage && (
                                            <Td>
                                                <div className="flex flex-wrap gap-2">
                                                    <Link href={route('stock.suppliers.edit', s.id)}>
                                                        <SecondaryButton type="button">
                                                            {t('edit')}
                                                        </SecondaryButton>
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
