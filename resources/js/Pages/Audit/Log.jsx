import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Log({ entries, filters, actions, users }) {
    const [action, setAction] = useState(filters?.action || '');
    const [userId, setUserId] = useState(filters?.user_id ? String(filters.user_id) : '');
    const [from, setFrom] = useState(filters?.from || '');
    const [to, setTo] = useState(filters?.to || '');

    const apply = (e) => {
        e.preventDefault();
        router.get(
            route('audit.index'),
            {
                action: action || undefined,
                user_id: userId || undefined,
                from: from || undefined,
                to: to || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const clear = () => {
        setAction('');
        setUserId('');
        setFrom('');
        setTo('');
        router.get(route('audit.index'), {}, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Audit log"
                    subtitle="Sensitive vault and payout actions"
                />
            }
        >
            <Head title="Audit log" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={apply}
                        className="bv-surface grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <div>
                            <InputLabel value="Action" />
                            <select
                                className={selectClass}
                                value={action}
                                onChange={(e) => setAction(e.target.value)}
                            >
                                <option value="">All actions</option>
                                {(actions || []).map((a) => (
                                    <option key={a.value} value={a.value}>
                                        {a.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel value="User" />
                            <select
                                className={selectClass}
                                value={userId}
                                onChange={(e) => setUserId(e.target.value)}
                            >
                                <option value="">All users</option>
                                {(users || []).map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel value="From" />
                            <input
                                type="date"
                                className={selectClass}
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                            />
                        </div>
                        <div>
                            <InputLabel value="To" />
                            <input
                                type="date"
                                className={selectClass}
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                            />
                        </div>
                        <div className="flex items-end gap-2">
                            <button
                                type="submit"
                                className="inline-flex items-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600"
                            >
                                Filter
                            </button>
                            <SecondaryButton type="button" onClick={clear}>
                                Clear
                            </SecondaryButton>
                        </div>
                    </form>

                    <div className="bv-surface overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                            <thead className="bg-slate-50 dark:bg-slate-900/60">
                                <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th className="px-4 py-3">When</th>
                                    <th className="px-4 py-3">Action</th>
                                    <th className="px-4 py-3">User</th>
                                    <th className="px-4 py-3">Description</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {(entries || []).length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-4 py-8 text-center text-slate-500"
                                        >
                                            No audit entries match these filters.
                                        </td>
                                    </tr>
                                )}
                                {(entries || []).map((row) => (
                                    <tr key={row.id}>
                                        <td className="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 dark:text-slate-300">
                                            {row.created_at
                                                ? new Date(row.created_at).toLocaleString()
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                            {row.action_label}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-300">
                                            {row.user?.name || 'System'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700 dark:text-slate-200">
                                            {row.description}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
