import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Index({ types, recent, modes }) {
    const list = types || [];
    const jobs = recent || [];
    const modeOptions = modes || ['partial', 'atomic'];
    const { flash } = usePage().props;

    const form = useForm({
        type: list[0]?.type || 'workers',
        mode: 'partial',
        file: null,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('imports.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Imports"
                    subtitle="Upload CSV/Excel · validate rows · partial or atomic modes"
                />
            }
        >
            <Head title="Imports" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <p className="border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {flash.success}
                        </p>
                    )}

                    <form onSubmit={submit} className="bv-surface space-y-4 p-5">
                        <h3 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            Upload import file
                        </h3>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel value="Type" />
                                <select
                                    className={selectClass}
                                    value={form.data.type}
                                    onChange={(e) => form.setData('type', e.target.value)}
                                >
                                    {list.map((item) => (
                                        <option key={item.type} value={item.type}>
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.type} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Mode" />
                                <select
                                    className={selectClass}
                                    value={form.data.mode}
                                    onChange={(e) => form.setData('mode', e.target.value)}
                                >
                                    {modeOptions.map((m) => (
                                        <option key={m} value={m}>
                                            {m === 'atomic' ? 'Atomic (all-or-nothing)' : 'Partial (skip invalid)'}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.mode} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="File (CSV or XLSX)" />
                                <input
                                    type="file"
                                    accept=".csv,.xlsx,.txt"
                                    className="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white dark:text-slate-300"
                                    onChange={(e) => form.setData('file', e.target.files?.[0] || null)}
                                />
                                <InputError message={form.errors.file} className="mt-1" />
                            </div>
                        </div>
                        <PrimaryButton type="submit" disabled={form.processing || !form.data.file}>
                            {form.processing ? 'Validating…' : 'Upload & validate'}
                        </PrimaryButton>
                    </form>

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
                        {jobs.length === 0 ? (
                            <p className="mt-4 text-sm text-slate-500">No import jobs yet.</p>
                        ) : (
                            <div className="bv-table-wrap mt-4">
                                <table className="bv-table min-w-[40rem]">
                                    <thead className="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800">
                                        <tr>
                                            <th className="px-3 py-2 text-start">ID</th>
                                            <th className="px-3 py-2 text-start">Type</th>
                                            <th className="px-3 py-2 text-start">Mode</th>
                                            <th className="px-3 py-2 text-start">Status</th>
                                            <th className="px-3 py-2 text-start">Rows</th>
                                            <th className="px-3 py-2 text-start">File</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:border-slate-800 dark:divide-slate-800">
                                        {jobs.map((job) => (
                                            <tr key={job.id}>
                                                <td className="px-3 py-2 tabular-nums">
                                                    <Link
                                                        href={route('imports.show', job.id)}
                                                        className="font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                                                    >
                                                        #{job.id}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-2">{job.type}</td>
                                                <td className="px-3 py-2">{job.mode}</td>
                                                <td className="px-3 py-2"><StatusBadge status={job.status} /></td>
                                                <td className="px-3 py-2 tabular-nums text-sm">
                                                    {job.success_rows}/{job.total_rows}
                                                    {job.failed_rows > 0 && (
                                                        <span className="ms-1 text-rose-600">({job.failed_rows} err)</span>
                                                    )}
                                                </td>
                                                <td className="max-w-xs truncate px-3 py-2">
                                                    {job.original_filename || '—'}
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
