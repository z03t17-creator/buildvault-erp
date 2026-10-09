import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

function formatErrors(errors) {
    if (!errors || typeof errors !== 'object') return '—';
    return Object.entries(errors)
        .map(([field, msgs]) => {
            const list = Array.isArray(msgs) ? msgs.join('; ') : String(msgs);
            return `${field}: ${list}`;
        })
        .join(' · ');
}

export default function Show({ import: job }) {
    const details = job?.details || [];
    const failed = details.filter(
        (d) => d.status === 'invalid' || (d.errors && Object.keys(d.errors || {}).length),
    );
    const progress =
        job?.total_rows > 0
            ? Math.round(((job.success_rows + job.failed_rows) / job.total_rows) * 100)
            : job?.status === 'completed' ||
                job?.status === 'failed' ||
                job?.status === 'rolled_back'
              ? 100
              : 0;

    const rollback = () => {
        if (!window.confirm('Roll back imported records where still safe?')) return;
        router.post(route('imports.rollback', job.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Import #${job.id}`}
                    subtitle={`${job.type} · ${job.mode} · ${job.original_filename || 'untitled'}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('imports.index')}>
                                <SecondaryButton type="button">All imports</SecondaryButton>
                            </Link>
                            {job.can_rollback && (
                                <PrimaryButton type="button" onClick={rollback}>
                                    Rollback
                                </PrimaryButton>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={`Import #${job.id}`} />

            <PageShell>
                <DataPanel>
                    <div className="grid gap-4 sm:grid-cols-4">
                        <div>
                            <p className="text-xs uppercase tracking-wider text-slate-500">Status</p>
                            <div className="mt-1">
                                <StatusBadge status={job.status} />
                            </div>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wider text-slate-500">
                                Imported
                            </p>
                            <p className="mt-1 font-display text-2xl tabular-nums text-slate-900 dark:text-white">
                                {job.success_rows}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wider text-slate-500">Failed</p>
                            <p className="mt-1 font-display text-2xl tabular-nums text-rose-600">
                                {job.failed_rows}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wider text-slate-500">
                                Total rows
                            </p>
                            <p className="mt-1 font-display text-2xl tabular-nums text-slate-900 dark:text-white">
                                {job.total_rows}
                            </p>
                        </div>
                        <div className="sm:col-span-4">
                            <div className="h-2 overflow-hidden rounded-sm bg-slate-200 dark:bg-slate-800">
                                <div
                                    className={`h-full transition-all ${job.failed_rows > 0 ? 'bg-amber-500' : 'bg-emerald-600'}`}
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                            <p className="mt-2 text-sm text-slate-500">
                                {job.notes || 'Row-level results below.'}
                            </p>
                        </div>
                    </div>
                </DataPanel>

                {failed.length > 0 && (
                    <DataPanel title="Error report" padded={false}>
                        <DataTable minWidth="36rem" caption="Error report">
                            <thead>
                                <tr>
                                    <Th>Row</Th>
                                    <Th>Errors</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {failed.map((d) => (
                                    <tr key={d.id}>
                                        <Td className="tabular-nums">{d.row_number}</Td>
                                        <Td className="text-rose-700 dark:text-rose-300">
                                            {formatErrors(d.errors)}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}

                <DataPanel title="All rows" padded={false}>
                    {details.length === 0 ? (
                        <div className="p-5">
                            <EmptyState title="No row details." />
                        </div>
                    ) : (
                        <DataTable minWidth="44rem" caption="All rows">
                            <thead>
                                <tr>
                                    <Th>Row</Th>
                                    <Th>Status</Th>
                                    <Th>Record</Th>
                                    <Th>Payload</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {details.map((d) => (
                                    <tr key={d.id}>
                                        <Td className="tabular-nums">{d.row_number}</Td>
                                        <Td>
                                            <StatusBadge status={d.status} />
                                        </Td>
                                        <Td muted>
                                            {d.record_type
                                                ? `${d.record_type} #${d.record_id}`
                                                : '—'}
                                        </Td>
                                        <Td muted className="max-w-md truncate text-xs">
                                            {JSON.stringify(d.payload || {})}
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
