import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
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
    const { flash } = usePage().props;
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

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <p className="border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {flash.success}
                        </p>
                    )}
                    {flash?.error && (
                        <p className="border border-rose-300 bg-rose-50 px-4 py-2 text-sm text-rose-900 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100">
                            {flash.error}
                        </p>
                    )}

                    <section className="bv-surface grid gap-6 p-5 lg:grid-cols-2">
                        <form onSubmit={submit} className="space-y-4">
                            <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                Run backup now
                            </h3>
                            <p className="text-sm text-slate-500">
                                Writes zip archives to <code className="text-xs">storage/app/backups/</code>.
                            </p>
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
                                            {t === 'full' ? 'Full (DB + files)' : t === 'database' ? 'Database only' : 'Files only'}
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

                        <div>
                            <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                Daily schedule
                            </h3>
                            <p className="mt-1 text-sm text-slate-500">
                                {schedule?.note}
                            </p>
                            <pre className="mt-3 overflow-x-auto rounded-sm bg-slate-900 px-3 py-2 text-xs text-emerald-200">
                                {schedule?.cron || '* * * * * php artisan schedule:run'}
                            </pre>
                            <p className="mt-2 text-xs text-slate-500">
                                Direct (no scheduler): <code>{schedule?.direct || schedule?.command}</code>
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                Underlying: <code>{schedule?.command}</code> · logged wrapper: <code>backup:run-logged</code>
                            </p>
                        </div>
                    </section>

                    <section className="bv-surface p-5">
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            Backup history
                        </h3>
                        {list.length === 0 ? (
                            <p className="mt-4 text-sm text-slate-500">No backups logged yet.</p>
                        ) : (
                            <div className="bv-table-wrap mt-4">
                                <table className="bv-table min-w-[44rem]">
                                    <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                        <tr>
                                            <th className="px-3 py-2 text-start">ID</th>
                                            <th className="px-3 py-2 text-start">Type</th>
                                            <th className="px-3 py-2 text-start">Status</th>
                                            <th className="px-3 py-2 text-start">File</th>
                                            <th className="px-3 py-2 text-start">Size</th>
                                            <th className="px-3 py-2 text-start">By</th>
                                            <th className="px-3 py-2 text-start">Finished</th>
                                            <th className="px-3 py-2 text-start"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {list.map((b) => (
                                            <tr key={b.id}>
                                                <td className="px-3 py-2 tabular-nums">#{b.id}</td>
                                                <td className="px-3 py-2">{b.type}</td>
                                                <td className="px-3 py-2"><StatusBadge status={b.status} /></td>
                                                <td className="max-w-xs truncate px-3 py-2 text-sm" title={b.filename || ''}>
                                                    {b.filename || '—'}
                                                </td>
                                                <td className="px-3 py-2 tabular-nums text-sm">
                                                    {formatBytes(b.size_bytes)}
                                                </td>
                                                <td className="px-3 py-2 text-sm">{b.created_by || 'cron'}</td>
                                                <td className="px-3 py-2 text-sm text-slate-500">
                                                    {b.finished_at
                                                        ? new Date(b.finished_at).toLocaleString()
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-2 text-end">
                                                    {b.downloadable ? (
                                                        <a href={route('backups.download', b.id)}>
                                                            <SecondaryButton type="button">Download</SecondaryButton>
                                                        </a>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
