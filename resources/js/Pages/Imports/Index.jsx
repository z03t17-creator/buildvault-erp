import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import DangerButton from '@/Components/DangerButton';
import EmptyState from '@/Components/EmptyState';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function CountStat({ label, value, hint, active = false }) {
    return (
        <div
            className={
                'bv-card px-4 py-3.5 ' +
                (active ? 'ring-2 ring-slate-500/40 dark:ring-slate-400/40' : '')
            }
        >
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1.5 font-sans text-2xl font-semibold tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                {value ?? 0}
            </div>
            {hint ? (
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            ) : null}
        </div>
    );
}

function StatusChip({ status, t }) {
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status?.replace(/_/g, ' ') || '—';
    const tones = {
        completed: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        failed: 'bg-rose-500/15 text-rose-900 dark:text-rose-300',
        processing: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        pending: 'bg-slate-500/15 text-slate-800 dark:text-slate-200',
        rolled_back: 'bg-slate-500/15 text-slate-600 dark:text-slate-400',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold capitalize ' +
                (tones[status] || tones.pending)
            }
        >
            {label}
        </span>
    );
}

export default function Index({
    types,
    recent,
    modes,
    canImportMayorca,
    workbookBundled,
    overview,
}) {
    const t = useTranslations();
    const canAudit = useCan('vault.audit');
    const canBackups = useCan('vault.backups');
    const list = types || [];
    const jobs = recent || [];
    const modeOptions = modes || ['partial', 'atomic'];
    const fileRef = useRef(null);
    const totals = overview || {
        count: jobs.length,
        completed: 0,
        failed: 0,
    };

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

    const fileName = form.data.file?.name || '';

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('imports')}
                    subtitle={t('imports_page_hint')}
                    icon={<NavIcon name="imports" className="text-lg" />}
                    actions={
                        <>
                            {canBackups && (
                                <Link href={route('backups.index')}>
                                    <SecondaryButton type="button">
                                        <NavIcon name="backups" className="text-sm" />
                                        {t('backups')}
                                    </SecondaryButton>
                                </Link>
                            )}
                            {canAudit && (
                                <Link href={route('audit.index')}>
                                    <SecondaryButton type="button">
                                        <NavIcon name="audit" className="text-sm" />
                                        {t('audit')}
                                    </SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={t('imports')} />
            <PageShell className="!space-y-6">
                {canImportMayorca && (
                    <section className="bv-card overflow-hidden p-0">
                        <div className="border-b border-amber-200/80 bg-amber-50/80 px-4 py-4 sm:px-5 dark:border-amber-900/50 dark:bg-amber-950/30">
                            <div className="flex items-start gap-3">
                                <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-amber-950 dark:bg-amber-400">
                                    <NavIcon name="imports" className="text-base" />
                                </span>
                                <div className="min-w-0">
                                    <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                        {t('mayorca_import_panel_title')}
                                    </h2>
                                    <p className="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
                                        {t('mayorca_import_panel_hint')}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                            <p className="text-sm text-slate-600 dark:text-slate-300">
                                {t('mayorca_import_panel_body')}
                            </p>
                            <DangerButton
                                type="button"
                                disabled={
                                    mayorcaForm.processing || !workbookBundled
                                }
                                onClick={runMayorcaImport}
                            >
                                <NavIcon name="imports" className="text-sm" />
                                {mayorcaForm.processing
                                    ? t('mayorca_import_running')
                                    : t('mayorca_import_button')}
                            </DangerButton>
                        </div>
                        {!workbookBundled && (
                            <p className="border-t border-rose-200/70 px-4 py-3 text-sm text-rose-700 sm:px-5 dark:border-rose-900/40 dark:text-rose-300">
                                {t('mayorca_workbook_missing')}
                            </p>
                        )}
                    </section>
                )}

                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('imports_overview')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('imports_overview_hint', { count: totals.count })}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                        <CountStat
                            label={t('imports_stat_recent')}
                            value={totals.count}
                            hint={t('imports_stat_recent_hint')}
                            active
                        />
                        <CountStat
                            label={t('status_completed')}
                            value={totals.completed}
                            hint={t('imports_stat_completed_hint')}
                        />
                        <CountStat
                            label={t('status_failed')}
                            value={totals.failed}
                            hint={t('imports_stat_failed_hint')}
                        />
                    </div>
                </section>

                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-500/15 text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
                            <NavIcon name="imports" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('imports_upload_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('imports_upload_hint')}
                            </p>
                        </div>
                    </div>
                    <form noValidate onSubmit={submit}>
                        <FormSection cols={3}>
                            <FormField>
                                <InputLabel value={t('imports_type')} />
                                <select
                                    className={fieldClass}
                                    value={form.data.type}
                                    onChange={(e) =>
                                        form.setData('type', e.target.value)
                                    }
                                >
                                    {list.map((item) => (
                                        <option key={item.type} value={item.type}>
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={form.errors.type}
                                    className="mt-1"
                                />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('imports_mode')} />
                                <select
                                    className={fieldClass}
                                    value={form.data.mode}
                                    onChange={(e) =>
                                        form.setData('mode', e.target.value)
                                    }
                                >
                                    {modeOptions.map((m) => (
                                        <option key={m} value={m}>
                                            {m === 'atomic'
                                                ? t('imports_mode_atomic')
                                                : t('imports_mode_partial')}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={form.errors.mode}
                                    className="mt-1"
                                />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('imports_file')} />
                                <input
                                    ref={fileRef}
                                    type="file"
                                    accept=".csv,.xlsx,.txt"
                                    className="sr-only"
                                    onChange={(e) =>
                                        form.setData(
                                            'file',
                                            e.target.files?.[0] || null,
                                        )
                                    }
                                />
                                <div className="mt-1 flex flex-wrap items-center gap-2">
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => fileRef.current?.click()}
                                    >
                                        <NavIcon name="imports" className="text-sm" />
                                        {t('imports_choose_file')}
                                    </SecondaryButton>
                                    <span className="text-sm text-slate-500 dark:text-slate-400">
                                        {fileName || t('imports_no_file')}
                                    </span>
                                </div>
                                <InputError
                                    message={form.errors.file}
                                    className="mt-1"
                                />
                            </FormField>
                        </FormSection>
                        <FormActions className="mt-4">
                            <PrimaryButton
                                type="submit"
                                disabled={form.processing || !form.data.file}
                                className="!bg-slate-800 hover:!bg-slate-700 dark:!bg-slate-200 dark:!text-slate-900"
                            >
                                <NavIcon name="imports" className="text-sm" />
                                {form.processing
                                    ? t('imports_validating')
                                    : t('imports_upload')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </section>

                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('imports_templates_title')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('imports_templates_hint')}
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {list.map((item) => (
                            <div key={item.type} className="bv-card p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="flex items-start gap-3">
                                        <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-500/15 text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
                                            <NavIcon
                                                name="docs"
                                                className="text-sm"
                                            />
                                        </span>
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {item.label}
                                            </p>
                                            <p className="mt-0.5 font-sans text-xs text-slate-500 dark:text-slate-400">
                                                {item.type}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <a href={item.csv_url}>
                                            <PrimaryButton
                                                type="button"
                                                className="!bg-slate-800 hover:!bg-slate-700"
                                            >
                                                CSV
                                            </PrimaryButton>
                                        </a>
                                        <a href={item.xlsx_url}>
                                            <SecondaryButton type="button">
                                                Excel
                                            </SecondaryButton>
                                        </a>
                                    </div>
                                </div>
                                <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                                    {t('imports_columns')}:{' '}
                                    {(item.headers || []).join(', ')}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <DataPanel padded={false}>
                    <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-500/15 text-slate-800 dark:bg-slate-400/15 dark:text-slate-200">
                            <NavIcon name="imports" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('imports_jobs_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('imports_jobs_hint')}
                            </p>
                        </div>
                    </div>
                    {jobs.length === 0 ? (
                        <div className="p-4">
                            <EmptyState
                                icon="imports"
                                title={t('imports_empty_title')}
                                description={t('imports_empty_hint')}
                            />
                        </div>
                    ) : (
                        <DataTable minWidth="44rem" caption={t('imports')}>
                            <thead>
                                <tr>
                                    <Th>{t('backups_col_id')}</Th>
                                    <Th>{t('imports_type')}</Th>
                                    <Th>{t('imports_mode')}</Th>
                                    <Th>{t('status')}</Th>
                                    <Th>{t('imports_rows')}</Th>
                                    <Th>{t('imports_file')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {jobs.map((job) => (
                                    <tr key={job.id}>
                                        <Td className="font-sans tabular-nums">
                                            <Link
                                                href={route(
                                                    'imports.show',
                                                    job.id,
                                                )}
                                                className="font-medium text-slate-900 underline-offset-2 hover:underline dark:text-slate-100"
                                            >
                                                #{job.id}
                                            </Link>
                                        </Td>
                                        <Td>{job.type}</Td>
                                        <Td muted>{job.mode}</Td>
                                        <Td>
                                            <StatusChip
                                                status={job.status}
                                                t={t}
                                            />
                                        </Td>
                                        <Td muted className="font-sans tabular-nums">
                                            {job.success_rows}/{job.total_rows}
                                            {job.failed_rows > 0 ? (
                                                <span className="ms-1 text-rose-600 dark:text-rose-300">
                                                    ({job.failed_rows})
                                                </span>
                                            ) : null}
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
