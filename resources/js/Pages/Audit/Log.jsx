import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
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

    const rows = entries || [];

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

            <PageShell>
                <DataPanel title="Filters">
                    <form
                        onSubmit={apply}
                        className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
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
                            <PrimaryButton type="submit">Filter</PrimaryButton>
                            <SecondaryButton type="button" onClick={clear}>
                                Clear
                            </SecondaryButton>
                        </div>
                    </form>
                </DataPanel>

                <DataPanel padded={false}>
                    {rows.length === 0 ? (
                        <div className="p-5">
                            <EmptyState title="No audit entries match these filters." />
                        </div>
                    ) : (
                        <DataTable minWidth="40rem" caption="Audit log">
                            <thead>
                                <tr>
                                    <Th>When</Th>
                                    <Th>Action</Th>
                                    <Th>User</Th>
                                    <Th>Description</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <Td muted className="whitespace-nowrap tabular-nums">
                                            {row.created_at
                                                ? new Date(row.created_at).toLocaleString()
                                                : '—'}
                                        </Td>
                                        <Td className="font-medium">{row.action_label}</Td>
                                        <Td muted>{row.user?.name || 'System'}</Td>
                                        <Td>{row.description}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
