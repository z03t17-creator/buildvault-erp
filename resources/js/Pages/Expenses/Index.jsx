import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

function formatIqd(n, iqdLabel = 'IQD') {
    if (n == null || Number.isNaN(Number(n))) return '—';
    return `${Number(n).toLocaleString(undefined, { maximumFractionDigits: 0 })} ${iqdLabel}`;
}

export default function Index({ expenses }) {
    const list = expenses || [];
    const canCreate = useCan('expenses.create');
    const t = useTranslations();
    const iqd = t('currency_iqd') === 'currency_iqd' ? 'IQD' : t('currency_iqd');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('expenses')}
                    subtitle={t('expenses_subtitle')}
                    actions={
                        canCreate ? (
                            <Link href={route('expenses.create')}>
                                <PrimaryButton type="button">{t('new_expense')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('expenses')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl overflow-x-auto px-4 sm:px-6 lg:px-8">
                    <table className="min-w-full border border-slate-200/80 bg-white/80 text-sm dark:border-slate-700 dark:bg-slate-900/70">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                            <tr>
                                <th className="px-3 py-2 text-start">{t('id')}</th>
                                <th className="px-3 py-2 text-start">{t('project')}</th>
                                <th className="px-3 py-2 text-start">{t('category')}</th>
                                <th className="px-3 py-2 text-start">{t('amount_iqd')}</th>
                                <th className="px-3 py-2 text-start">{t('expense_date')}</th>
                                <th className="px-3 py-2 text-start">{t('status')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {list.map((e) => (
                                <tr key={e.id}>
                                    <td className="px-3 py-2">
                                        <Link
                                            href={route('expenses.show', e.id)}
                                            className="text-emerald-700 underline dark:text-emerald-400"
                                        >
                                            #{e.id}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{e.project?.name || '—'}</td>
                                    <td className="px-3 py-2 capitalize">
                                        {t(`expense_category_${e.category}`) !== `expense_category_${e.category}`
                                            ? t(`expense_category_${e.category}`)
                                            : e.category}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">{formatIqd(e.amount_iqd, iqd)}</td>
                                    <td className="px-3 py-2 tabular-nums">{e.expense_date}</td>
                                    <td className="px-3 py-2">
                                        <StatusBadge status={e.approval_status} />
                                    </td>
                                </tr>
                            ))}
                            {!list.length && (
                                <tr>
                                    <td colSpan={6} className="px-3 py-8 text-center text-slate-500">
                                        {t('no_expenses')}
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
