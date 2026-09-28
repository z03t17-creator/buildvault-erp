import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import { Head, Link } from '@inertiajs/react';

export default function Index({ penalties }) {
    const list = penalties || [];
    const canCreate = useCan('penalties.create');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Penalties"
                    subtitle="Worker deductions"
                    actions={
                        canCreate ? (
                            <Link href={route('penalties.create')}>
                                <PrimaryButton type="button">Record penalty</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title="Penalties" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bv-surface">
                        <div className="bv-table-wrap">
                    <table className="bv-table min-w-[36rem]">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                            <tr>
                                <th className="px-3 py-2 text-start">Worker</th>
                                <th className="px-3 py-2 text-start">Reason</th>
                                <th className="px-3 py-2 text-start">Amount</th>
                                <th className="px-3 py-2 text-start">Payout</th>
                                <th className="px-3 py-2 text-start">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {list.map((p) => (
                                <tr key={p.id}>
                                    <td className="px-3 py-2">
                                        <Link href={route('penalties.show', p.id)} className="text-emerald-700 underline dark:text-emerald-400">
                                            {p.worker?.name || '—'}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 max-w-xs truncate">{p.reason}</td>
                                    <td className="px-3 py-2 tabular-nums text-rose-700 dark:text-rose-300">{p.amount_usd}</td>
                                    <td className="px-3 py-2">{p.payout_id ? `#${p.payout_id}` : '—'}</td>
                                    <td className="px-3 py-2"><StatusBadge status={p.status} /></td>
                                </tr>
                            ))}
                            {!list.length && (
                                <tr>
                                    <td colSpan={5} className="px-3 py-8 text-center text-slate-500">No penalties yet.</td>
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
