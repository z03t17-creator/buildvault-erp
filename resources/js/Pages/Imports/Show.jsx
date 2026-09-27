import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

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
    const { flash } = usePage().props;
    const details = job?.details || [];
    const failed = details.filter((d) => d.status === 'invalid' || (d.errors && Object.keys(d.errors || {}).length));
    const progress =
        job?.total_rows > 0
            ? Math.round(((job.success_rows + job.failed_rows) / job.total_rows) * 100)
            : job?.status === 'completed' || job?.status === 'failed' || job?.status === 'rolled_back'
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

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
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

                    <section className="bv-surface grid gap-4 p-5 sm:grid-cols-4">
                        <div>
                            <p className="text-xs uppercase tracking-wider text-slate-500">Status</p>
                            <div className="mt-1"><StatusBadge status={job.status} /></div>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wider text-slate-500">Imported</p>
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
                            <p className="text-xs uppercase tracking-wider text-slate-500">Total rows</p>
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
                    </section>

                    {failed.length > 0 && (
                        <section className="bv-surface p-5">
                            <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                                Error report
                            </h3>
                            <div className="bv-table-wrap mt-4">
                                <table className="bv-table min-w-[36rem]">
                                    <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                        <tr>
                                            <th className="px-3 py-2 text-start">Row</th>
                                            <th className="px-3 py-2 text-start">Errors</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {failed.map((d) => (
                                            <tr key={d.id}>
                                                <td className="px-3 py-2 tabular-nums">{d.row_number}</td>
                                                <td className="px-3 py-2 text-sm text-rose-700 dark:text-rose-300">
                                                    {formatErrors(d.errors)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    )}

                    <section className="bv-surface p-5">
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            All rows
                        </h3>
                        {details.length === 0 ? (
                            <p className="mt-4 text-sm text-slate-500">No row details.</p>
                        ) : (
                            <div className="bv-table-wrap mt-4">
                                <table className="bv-table min-w-[44rem]">
                                    <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                        <tr>
                                            <th className="px-3 py-2 text-start">Row</th>
                                            <th className="px-3 py-2 text-start">Status</th>
                                            <th className="px-3 py-2 text-start">Record</th>
                                            <th className="px-3 py-2 text-start">Payload</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {details.map((d) => (
                                            <tr key={d.id}>
                                                <td className="px-3 py-2 tabular-nums">{d.row_number}</td>
                                                <td className="px-3 py-2"><StatusBadge status={d.status} /></td>
                                                <td className="px-3 py-2 text-sm">
                                                    {d.record_type ? `${d.record_type} #${d.record_id}` : '—'}
                                                </td>
                                                <td className="max-w-md truncate px-3 py-2 text-xs text-slate-500">
                                                    {JSON.stringify(d.payload || {})}
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
