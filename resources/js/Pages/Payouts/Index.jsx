import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import { Head, Link } from '@inertiajs/react';

export default function Index({ payouts }) {
    const list = payouts || [];
    const canCreate = useCan('payouts.create');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Payouts"
                    subtitle="Vault ledger approvals"
                    actions={
                        canCreate ? (
                            <Link href={route('payouts.create')}>
                                <PrimaryButton type="button">New payout</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title="Payouts" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl overflow-x-auto px-4 sm:px-6 lg:px-8">
                    <table className="min-w-full border border-slate-200/80 bg-white/80 text-sm dark:border-slate-700 dark:bg-slate-900/70">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                            <tr>
                                <th className="px-3 py-2 text-start">ID</th>
                                <th className="px-3 py-2 text-start">Project</th>
                                <th className="px-3 py-2 text-start">Category</th>
                                <th className="px-3 py-2 text-start">Amount</th>
                                <th className="px-3 py-2 text-start">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {list.map((p) => (
                                <tr key={p.id}>
                                    <td className="px-3 py-2">
                                        <Link href={route('payouts.show', p.id)} className="text-emerald-700 underline dark:text-emerald-400">
                                            #{p.id}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{p.project?.name || '—'}</td>
                                    <td className="px-3 py-2 capitalize">{p.category}</td>
                                    <td className="px-3 py-2">
                                        {p.amount_iqd != null ? (
                                            <MoneyAmount value={p.amount_iqd} label="IQD" size="md" />
                                        ) : (
                                            <MoneyAmount value={p.amount_usd} label="USD" size="md" showLabel />
                                        )}
                                    </td>
                                    <td className="px-3 py-2"><StatusBadge status={p.status} /></td>
                                </tr>
                            ))}
                            {!list.length && (
                                <tr>
                                    <td colSpan={5} className="px-3 py-8 text-center text-slate-500">No payouts yet.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
