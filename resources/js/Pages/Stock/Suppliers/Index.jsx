import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ suppliers }) {
    const list = suppliers || [];
    const canManage = useCan('stock.manageSuppliers');
    const t = useTranslations();

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('suppliers')}
                    subtitle={t('suppliers_hint')}
                    actions={
                        canManage ? (
                            <Link href={route('stock.suppliers.create')}>
                                <PrimaryButton type="button">{t('new_supplier')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('suppliers')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl overflow-x-auto px-4 sm:px-6 lg:px-8">
                    <table className="min-w-full border border-slate-200/80 bg-white/80 text-sm dark:border-slate-700 dark:bg-slate-900/70">
                        <thead className="border-b text-xs uppercase text-slate-500">
                            <tr>
                                <th className="px-3 py-2 text-start">{t('name')}</th>
                                <th className="px-3 py-2 text-start">{t('contact_name')}</th>
                                <th className="px-3 py-2 text-start">{t('phone')}</th>
                                <th className="px-3 py-2 text-start">{t('email')}</th>
                                <th className="px-3 py-2 text-start">{t('notes')}</th>
                                {canManage && <th className="px-3 py-2 text-start">{t('actions')}</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {list.map((s) => (
                                <tr key={s.id}>
                                    <td className="px-3 py-2 font-medium">{s.name}</td>
                                    <td className="px-3 py-2">{s.contact_name || '—'}</td>
                                    <td className="px-3 py-2">{s.phone || '—'}</td>
                                    <td className="px-3 py-2">{s.email || '—'}</td>
                                    <td className="px-3 py-2 max-w-xs truncate">{s.notes || '—'}</td>
                                    {canManage && (
                                        <td className="px-3 py-2">
                                            <div className="flex gap-2">
                                                <Link href={route('stock.suppliers.edit', s.id)}>
                                                    <SecondaryButton type="button">{t('edit')}</SecondaryButton>
                                                </Link>
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() => {
                                                        if (confirm(t('confirm_delete'))) {
                                                            router.delete(route('stock.suppliers.destroy', s.id));
                                                        }
                                                    }}
                                                >
                                                    {t('delete')}
                                                </SecondaryButton>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {!list.length && (
                                <tr>
                                    <td colSpan={canManage ? 6 : 5} className="px-3 py-8 text-center text-slate-500">
                                        {t('no_suppliers')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
