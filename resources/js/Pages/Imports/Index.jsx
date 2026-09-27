import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ types, recent }) {
    const list = types || [];
    const jobs = recent || [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Imports"
                    subtitle="Download Excel/CSV templates — upload flow in Phase 4.6"
                />
            }
        >
            <Head title="Imports" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <section className="grid gap-4 sm:grid-cols-2">
                        {list.map((item) => (
                            <article key={item.type} className="bv-card p-5">
                                <h3 className="font-display text-xl font-semibold text-slate-900 dark:text-white">
                                    {item.label}
                                </h3>
                                <p className="mt-1 text-xs uppercase tracking-wider text-slate-500">{item.type}</p>
                                <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                                    Columns: {(item.headers || []).join(', ')}
                                </p>
                                {(item.notes || []).length > 0 && (
                                    <ul className="mt-2 list-disc space-y-1 ps-5 text-xs text-slate-500">
                                        {item.notes.slice(0, 2).map((n) => (
                                            <li key={n}>{n}</li>
                                        ))}
                                    </ul>
                                )}
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <a href={item.csv_url}>
                                        <PrimaryButton type="button">CSV</PrimaryButton>
                                    </a>
                                    <a href={item.xlsx_url}>
                                        <SecondaryButton type="button">Excel</SecondaryButton>
                                    </a>
                                </div>
                            </article>
                        ))}
                    </section>

                    <section className="bv-surface p-5">
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            Recent import jobs
                        </h3>
                        <p className="mt-1 text-sm text-slate-500">
                            Upload + row validation UI ships in Phase 4.6.
                        </p>
                        {jobs.length === 0 ? (
                            <p className="mt-4 text-sm text-slate-500">No import jobs yet.</p>
                        ) : (
                            <div className="bv-table-wrap mt-4">
                                <table className="bv-table min-w-[36rem]">
                                    <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                        <tr>
                                            <th className="px-3 py-2 text-start">ID</th>
                                            <th className="px-3 py-2 text-start">Type</th>
                                            <th className="px-3 py-2 text-start">Mode</th>
                                            <th className="px-3 py-2 text-start">Status</th>
                                            <th className="px-3 py-2 text-start">File</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {jobs.map((job) => (
                                            <tr key={job.id}>
                                                <td className="px-3 py-2 tabular-nums">#{job.id}</td>
                                                <td className="px-3 py-2">{job.type}</td>
                                                <td className="px-3 py-2">{job.mode}</td>
                                                <td className="px-3 py-2"><StatusBadge status={job.status} /></td>
                                                <td className="px-3 py-2 truncate max-w-xs">{job.original_filename || '—'}</td>
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
