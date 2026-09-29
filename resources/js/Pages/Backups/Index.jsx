import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

function formatBytes(n) {
    const bytes = Number(n) || 0;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Index({ backups, types, schedule }) {
    const list = backups || [];
    const typeOptions = types || ['full', 'database', 'files'];
    const [confirming, setConfirming] = useState(false);

    const form = useForm({
        type: 'full',
    });

    const submit = (e) => {
        e.preventDefault();
        if (!confirming) {
            setConfirming(true);
            return;
        }
        form.post(route('backups.store'), {
            onFinish: () => setConfirming(false),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Backups"
                    subtitle="Local disk · Spatie backup · daily cron"
                />
            }
        >
            <Head title="Backups" />

            <PageShell>
                <div className="grid gap-6 lg:grid-cols-2">
                    <DataPanel title="Run backup now" subtitle="Writes zip archives to storage/app/backups/.">
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <InputLabel value="Type" />
                                <select
                                    className={selectClass}
                                    value={form.data.type}
                                    onChange={(e) => {
                                        form.setData('type', e.target.value);
                                        setConfirming(false);
                                    }}
                                >
                                    {typeOptions.map((t) => (
                                        <option key={t} value={t}>
                                            {t === 'full'
                                                ? 'Full (DB + files)'
                                                : t === 'database'
                                                  ? 'Database only'
                                                  : 'Files only'}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <PrimaryButton type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Running…'
                                    : confirming
                                      ? 'Click again to confirm'
                                      : 'Trigger backup'}
                            </PrimaryButton>
                        </form>
                    </DataPanel>

                    <DataPanel title="Daily schedule" subtitle={schedule?.note}>
                        <pre className="overflow-x-auto rounded-sm bg-slate-900 px-3 py-2 text-xs text-emerald-200">
                            {schedule?.cron || '* * * * * php artisan schedule:run'}
                        </pre>
                        <p className="mt-2 text-xs text-slate-500">
                            Direct (no scheduler): <code>{schedule?.direct || schedule?.command}</code>
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                            Underlying: <code>{schedule?.command}</code> · logged wrapper:{' '}
                            <code>backup:run-logged</code>
                        </p>
                    </DataPanel>
                </div>

                <DataPanel title="Backup history" padded={false}>
                    {list.length === 0 ? (
                        <div className="p-5">
                            <EmptyState title="No backups logged yet." />
                        </div>
                    ) : (
                        <DataTable minWidth="44rem" caption="Backup history">
                            <thead>
                                <tr>
                                    <Th>ID</Th>
                                    <Th>Type</Th>
                                    <Th>Status</Th>
                                    <Th>File</Th>
                                    <Th>Size</Th>
                                    <Th>By</Th>
                                    <Th>Finished</Th>
                                    <Th align="end" />
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((b) => (
                                    <tr key={b.id}>
                                        <Td muted className="tabular-nums">
                                            #{b.id}
                                        </Td>
                                        <Td>{b.type}</Td>
                                        <Td>
                                            <StatusBadge status={b.status} />
                                        </Td>
                                        <Td className="max-w-xs truncate" title={b.filename || ''}>
                                            {b.filename || '—'}
                                        </Td>
                                        <Td muted className="tabular-nums">
                                            {formatBytes(b.size_bytes)}
                                        </Td>
                                        <Td muted>{b.created_by || 'cron'}</Td>
                                        <Td muted>
                                            {b.finished_at
                                                ? new Date(b.finished_at).toLocaleString()
                                                : '—'}
                                        </Td>
                                        <Td align="end">
                                            {b.downloadable ? (
                                                <a href={route('backups.download', b.id)}>
                                                    <SecondaryButton type="button">
                                                        Download
                                                    </SecondaryButton>
                                                </a>
                                            ) : (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                        </Td>
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
