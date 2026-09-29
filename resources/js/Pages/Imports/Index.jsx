import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import DangerButton from '@/Components/DangerButton';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Index({ types, recent, modes, canImportMayorca, workbookBundled }) {
    const t = useTranslations();
    const list = types || [];
    const jobs = recent || [];
    const modeOptions = modes || ['partial', 'atomic'];

    const form = useForm({
        type: list[0]?.type || 'workers',
        mode: 'partial',
        file: null,
    });

    const mayorcaForm = useForm({ confirm_wipe: false });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('imports.store'), { forceFormData: true });
    };

    const runMayorcaImport = () => {
        if (!window.confirm(t('mayorca_import_confirm'))) {
            return;
        }
        mayorcaForm.transform((data) => ({ ...data, confirm_wipe: true }));
        mayorcaForm.post(route('admin.mayorca-import'), {
            preserveScroll: true,
            onFinish: () => mayorcaForm.setData('confirm_wipe', false),
        });
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

            <PageShell>
                {canImportMayorca && (
                    <DataPanel
                        title={t('mayorca_import_panel_title')}
                        subtitle={t('mayorca_import_panel_hint')}
                    >
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                                <span className="bv-icon-chip bv-icon-chip-amber mt-0.5">
                                    <NavIcon name="imports" className="text-base" />
                                </span>
                                <p>{t('mayorca_import_panel_body')}</p>
                            </div>
                            <DangerButton
                                type="button"
                                disabled={mayorcaForm.processing || !workbookBundled}
                                onClick={runMayorcaImport}
                            >
                                {mayorcaForm.processing
                                    ? t('mayorca_import_running')
                                    : t('mayorca_import_button')}
                            </DangerButton>
                        </div>
                        {!workbookBundled && (
                            <p className="mt-3 text-sm text-rose-700 dark:text-rose-300">
                                {t('mayorca_workbook_missing')}
                            </p>
                        )}
                    </DataPanel>
                )}

                <DataPanel title="Upload import file">
                    <form onSubmit={submit} className="space-y-4">
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
                                            {m === 'atomic'
                                                ? 'Atomic (all-or-nothing)'
                                                : 'Partial (skip invalid)'}
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
                                    onChange={(e) =>
                                        form.setData('file', e.target.files?.[0] || null)
                                    }
                                />
                                <InputError message={form.errors.file} className="mt-1" />
                            </div>
                        </div>
                        <PrimaryButton
                            type="submit"
                            disabled={form.processing || !form.data.file}
                        >
                            {form.processing ? 'Validating…' : 'Upload & validate'}
                        </PrimaryButton>
                    </form>
                </DataPanel>

                <div className="grid gap-4 sm:grid-cols-2">
                    {list.map((item) => (
                        <DataPanel
                            key={item.type}
                            title={item.label}
                            subtitle={item.type}
                            actions={
                                <div className="flex flex-wrap gap-2">
                                    <a href={item.csv_url}>
                                        <PrimaryButton type="button">CSV</PrimaryButton>
                                    </a>
                                    <a href={item.xlsx_url}>
                                        <SecondaryButton type="button">Excel</SecondaryButton>
                                    </a>
                                </div>
                            }
                        >
                            <p className="text-sm text-slate-600 dark:text-slate-300">
                                Columns: {(item.headers || []).join(', ')}
                            </p>
                            {(item.notes || []).length > 0 && (
                                <ul className="mt-2 list-disc space-y-1 ps-5 text-xs text-slate-500">
                                    {item.notes.slice(0, 2).map((n) => (
                                        <li key={n}>{n}</li>
                                    ))}
                                </ul>
                            )}
                        </DataPanel>
                    ))}
                </div>

                <DataPanel title="Recent import jobs" padded={false}>
                    {jobs.length === 0 ? (
                        <div className="p-5">
                            <EmptyState title="No import jobs yet." />
                        </div>
                    ) : (
                        <DataTable minWidth="40rem" caption="Recent import jobs">
                            <thead>
                                <tr>
                                    <Th>ID</Th>
                                    <Th>Type</Th>
                                    <Th>Mode</Th>
                                    <Th>Status</Th>
                                    <Th>Rows</Th>
                                    <Th>File</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {jobs.map((job) => (
                                    <tr key={job.id}>
                                        <Td className="tabular-nums">
                                            <Link
                                                href={route('imports.show', job.id)}
                                                className="font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                                            >
                                                #{job.id}
                                            </Link>
                                        </Td>
                                        <Td>{job.type}</Td>
                                        <Td muted>{job.mode}</Td>
                                        <Td>
                                            <StatusBadge status={job.status} />
                                        </Td>
                                        <Td muted className="tabular-nums">
                                            {job.success_rows}/{job.total_rows}
                                            {job.failed_rows > 0 && (
                                                <span className="ms-1 text-rose-600">
                                                    ({job.failed_rows} err)
                                                </span>
                                            )}
                                        </Td>
                                        <Td className="max-w-xs truncate">
                                            {job.original_filename || '—'}
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
