import DataPanel from '@/Components/DataPanel';
import DataRecordsTabs from '@/Components/DataRecordsTabs';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

function formatBytes(n) {
    const bytes = Number(n) || 0;
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Gallery({
    documents,
    grouped,
    filters,
    types,
    projects,
    workers,
}) {
    const [showUpload, setShowUpload] = useState(false);
    const canCreate = useCan('documents.create');
    const canDelete = useCan('documents.delete');

    const form = useForm({
        project_id: filters?.project_id ? String(filters.project_id) : '',
        worker_id: filters?.worker_id ? String(filters.worker_id) : '',
        type: filters?.type || 'site_photo',
        title: '',
        file: null,
    });

    const filteredWorkers = useMemo(() => {
        const list = workers || [];
        if (!form.data.project_id) return list;
        return list.filter((w) => String(w.project_id) === String(form.data.project_id));
    }, [workers, form.data.project_id]);

    const applyFilters = (next) => {
        router.get(route('documents.index'), next, {
            preserveState: true,
            replace: true,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('documents.store'), {
            forceFormData: true,
            onSuccess: () => {
                form.reset('title', 'file');
                setShowUpload(false);
            },
        });
    };

    const groups = grouped || [];
    const t = useTranslations();

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('nav_docs')}
                    subtitle={t('nav_docs_hint')}
                    icon={<NavIcon name="docs" className="text-lg" />}
                    actions={
                        canCreate ? (
                            <PrimaryButton type="button" onClick={() => setShowUpload((v) => !v)}>
                                {showUpload ? 'Close upload' : 'Upload document'}
                            </PrimaryButton>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('nav_docs')} />

            <PageShell>
                <DataRecordsTabs />
                {canCreate && showUpload && (
                    <DataPanel title="Upload">
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel value="Project" />
                                    <select
                                        className={selectClass}
                                        value={form.data.project_id}
                                        onChange={(e) => form.setData('project_id', e.target.value)}
                                        required
                                    >
                                        <option value="">—</option>
                                        {(projects || []).map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.project_id} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel value="Type" />
                                    <select
                                        className={selectClass}
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                        required
                                    >
                                        {(types || []).map((t) => (
                                            <option key={t} value={t}>
                                                {t.replace(/_/g, ' ')}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={form.errors.type} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel value="Worker (optional)" />
                                    <select
                                        className={selectClass}
                                        value={form.data.worker_id}
                                        onChange={(e) => form.setData('worker_id', e.target.value)}
                                    >
                                        <option value="">— none —</option>
                                        {filteredWorkers.map((w) => (
                                            <option key={w.id} value={w.id}>
                                                {w.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Title (optional)" />
                                    <TextInput
                                        className="mt-1 block w-full"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <InputLabel value="File (max 10 MB)" />
                                    <input
                                        type="file"
                                        className="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-emerald-600 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-500 dark:text-slate-300"
                                        onChange={(e) =>
                                            form.setData('file', e.target.files?.[0] || null)
                                        }
                                        required
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        Allowed for this type: jpeg/png/pdf/csv/xlsx as configured for
                                        type. Stored under storage/app/uploads/&#123;project&#125;/&#123;type&#125;/.
                                    </p>
                                    <InputError message={form.errors.file} className="mt-1" />
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <PrimaryButton disabled={form.processing}>Save</PrimaryButton>
                                <SecondaryButton type="button" onClick={() => setShowUpload(false)}>
                                    Cancel
                                </SecondaryButton>
                            </div>
                        </form>
                    </DataPanel>
                )}

                <DataPanel>
                    <div className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div>
                            <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                Type
                            </label>
                            <select
                                className={selectClass}
                                value={filters?.type || ''}
                                onChange={(e) =>
                                    applyFilters({
                                        ...filters,
                                        type: e.target.value || undefined,
                                    })
                                }
                            >
                                <option value="">All types</option>
                                {(types || []).map((t) => (
                                    <option key={t} value={t}>
                                        {t.replace(/_/g, ' ')}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                Project
                            </label>
                            <select
                                className={selectClass}
                                value={filters?.project_id || ''}
                                onChange={(e) =>
                                    applyFilters({
                                        ...filters,
                                        project_id: e.target.value || undefined,
                                    })
                                }
                            >
                                <option value="">All projects</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                Worker
                            </label>
                            <select
                                className={selectClass}
                                value={filters?.worker_id || ''}
                                onChange={(e) =>
                                    applyFilters({
                                        ...filters,
                                        worker_id: e.target.value || undefined,
                                    })
                                }
                            >
                                <option value="">All workers</option>
                                {(workers || []).map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <p className="text-xs text-slate-500 sm:ms-auto">
                            {(documents || []).length} file
                            {(documents || []).length === 1 ? '' : 's'}
                        </p>
                    </div>
                </DataPanel>

                {!groups.length && (
                    <EmptyState
                        title="No documents yet"
                        description="Upload a receipt, contract, or site photo to get started."
                    />
                )}

                {groups.map((group) => (
                    <section key={group.type} className="space-y-4">
                        <div className="flex flex-wrap items-baseline gap-2">
                            <h3 className="font-display text-lg font-semibold capitalize tracking-tight text-slate-900 dark:text-white">
                                {group.label}
                            </h3>
                            <span className="text-xs text-slate-500">
                                {group.documents.length} files
                            </span>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {group.documents.map((doc) => (
                                <article
                                    key={doc.id}
                                    className="overflow-hidden border-b border-slate-200/80 pb-4 dark:border-slate-800"
                                >
                                    <div className="aspect-[4/3] overflow-hidden bg-slate-100 dark:bg-slate-950">
                                        {doc.is_image ? (
                                            <a href={doc.url} target="_blank" rel="noreferrer">
                                                <img
                                                    src={doc.url}
                                                    alt={doc.title || doc.original_name}
                                                    className="h-full w-full object-cover transition duration-200 hover:opacity-95"
                                                />
                                            </a>
                                        ) : (
                                            <a
                                                href={doc.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="flex h-full flex-col items-center justify-center gap-2 px-4 text-center text-sm text-slate-600 dark:text-slate-300"
                                            >
                                                <span className="font-display text-2xl font-semibold uppercase tracking-wider text-slate-400">
                                                    {(doc.original_name || 'file').split('.').pop()}
                                                </span>
                                                <span className="max-w-full truncate underline decoration-emerald-500/50">
                                                    Open file
                                                </span>
                                            </a>
                                        )}
                                    </div>
                                    <div className="space-y-1 pt-3">
                                        <p className="truncate font-medium text-slate-900 dark:text-white">
                                            {doc.title || doc.original_name}
                                        </p>
                                        <p className="truncate text-xs text-slate-500">
                                            {doc.project?.name || '—'}
                                            {doc.worker ? ` · ${doc.worker.name}` : ''}
                                        </p>
                                        <p className="text-[11px] tabular-nums text-slate-400">
                                            {formatBytes(doc.size_bytes)}
                                        </p>
                                        <div className="flex gap-2 pt-1">
                                            {doc.worker_id && (
                                                <Link
                                                    href={route('workers.show', doc.worker_id)}
                                                    className="text-xs text-emerald-700 underline dark:text-emerald-400"
                                                >
                                                    Worker
                                                </Link>
                                            )}
                                            {doc.project_id && (
                                                <Link
                                                    href={route('projects.show', doc.project_id)}
                                                    className="text-xs text-emerald-700 underline dark:text-emerald-400"
                                                >
                                                    Project
                                                </Link>
                                            )}
                                            {canDelete && (
                                                <button
                                                    type="button"
                                                    className="ms-auto text-xs text-rose-600 underline dark:text-rose-400"
                                                    onClick={() => {
                                                        if (confirm('Delete this document?')) {
                                                            router.delete(
                                                                route('documents.destroy', doc.id),
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Delete
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                ))}
            </PageShell>
        </AuthenticatedLayout>
    );
}
