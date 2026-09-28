import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link } from '@inertiajs/react';

export default function Index({ penalties }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const list = penalties || [];
    const canCreate = useCan('penalties.create');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('penalties')}
                    subtitle={t('penalties_subtitle')}
                    actions={
                        canCreate ? (
                            <Link href={route('penalties.create')}>
                                <PrimaryButton type="button">{t('record_penalty')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('penalties')} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bv-surface">
                        <div className="bv-table-wrap">
                    <table className="bv-table min-w-[48rem]">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                            <tr>
                                <th className="px-3 py-2 text-start">{t('worker')}</th>
                                <th className="px-3 py-2 text-start">{t('project')}</th>
                                <th className="px-3 py-2 text-start">{t('penalty_type')}</th>
                                <th className="px-3 py-2 text-start">{t('date')}</th>
                                <th className="px-3 py-2 text-end">{t('amount_iqd')}</th>
                                <th className="px-3 py-2 text-start">{t('Status')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {list.map((p) => (
                                <tr key={p.id}>
                                    <td className="px-3 py-2">
                                        <Link href={route('penalties.show', p.id)} className="text-emerald-700 underline dark:text-emerald-400">
                                            {p.worker?.name || '—'}
                                        </Link>
                                        {p.reason && (
                                            <div className="max-w-xs truncate text-[11px] text-slate-400">{p.reason}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">{p.project?.name || '—'}</td>
                                    <td className="px-3 py-2">{t(`penalty_type_${p.type}`) || p.type}</td>
                                    <td className="px-3 py-2 tabular-nums">{p.occurred_on || '—'}</td>
                                    <td className="px-3 py-2 text-end">
                                        <MoneyAmount
                                            value={p.amount_iqd_display ?? p.amount_iqd}
                                            label={iqd}
                                            size="md"
                                            className="text-rose-700 dark:text-rose-300"
                                        />
                                    </td>
                                    <td className="px-3 py-2"><StatusBadge status={p.status} /></td>
                                </tr>
                            ))}
                            {!list.length && (
                                <tr>
                                    <td colSpan={6} className="px-3 py-8 text-center text-slate-500">{t('no_penalties')}</td>
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
