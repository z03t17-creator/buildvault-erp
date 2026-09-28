import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Index({ advances }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const list = advances || [];
    const canCreate = useCan('advances.create');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('advances')}
                    subtitle={t('advances_subtitle')}
                    actions={
                        canCreate ? (
                            <Link href={route('advances.create')}>
                                <PrimaryButton type="button">{t('record_advance')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('advances')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bv-surface">
                        <div className="bv-table-wrap">
                            <table className="bv-table min-w-[44rem]">
                                <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                    <tr>
                                        <th className="px-3 py-2 text-start">{t('worker')}</th>
                                        <th className="px-3 py-2 text-start">{t('project')}</th>
                                        <th className="px-3 py-2 text-start">{t('date')}</th>
                                        <th className="px-3 py-2 text-end">{t('amount_iqd')}</th>
                                        <th className="px-3 py-2 text-end">{t('remaining_iqd')}</th>
                                        <th className="px-3 py-2 text-start">{t('repayment_method')}</th>
                                        <th className="px-3 py-2 text-start">{t('status')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {list.map((a) => (
                                        <tr key={a.id}>
                                            <td className="px-3 py-2">
                                                <Link
                                                    href={route('advances.show', a.id)}
                                                    className="text-emerald-700 underline dark:text-emerald-400"
                                                >
                                                    {a.worker?.name || '—'}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2">{a.project?.name || '—'}</td>
                                            <td className="px-3 py-2 tabular-nums">{a.advanced_on}</td>
                                            <td className="px-3 py-2 text-end">
                                                <MoneyAmount value={a.amount_iqd} label={iqd} size="md" />
                                            </td>
                                            <td className="px-3 py-2 text-end">
                                                <MoneyAmount
                                                    value={a.remaining_iqd}
                                                    label={iqd}
                                                    size="md"
                                                    className="text-amber-700 dark:text-amber-300"
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                {t(`repay_${a.repayment_method}`) || a.repayment_method}
                                            </td>
                                            <td className="px-3 py-2">
                                                <StatusBadge status={a.status} />
                                            </td>
                                        </tr>
                                    ))}
                                    {!list.length && (
                                        <tr>
                                            <td colSpan={7} className="px-3 py-8 text-center text-slate-500">
                                                {t('advances_empty')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
