import DataPanel from '@/Components/DataPanel';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const selectClass =
    'w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

const STATUS_CELL = {
    pending:
        'border-amber-200/80 bg-amber-50/90 text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100',
    in_progress:
        'border-sky-200/80 bg-sky-50/90 text-sky-950 dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-100',
    completed:
        'border-emerald-200/80 bg-emerald-50/90 text-emerald-950 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-100',
    inspected:
        'border-teal-200/80 bg-teal-50/90 text-teal-950 dark:border-teal-800/60 dark:bg-teal-950/40 dark:text-teal-100',
};

function categoryLabel(t, cat) {
    const key = `spatial_cat_${cat}`;
    const v = t(key);
    return v !== key ? v : cat;
}

function cellKey(floor, unit) {
    return `${floor}|${unit}`;
}

export default function Index({
    projects,
    blocks,
    people,
    filters,
    categories,
    statuses,
    matrix,
    project,
}) {
    const t = useTranslations();
    const canUpdate = useCan('spatial.update');
    const canBulk = useCan('spatial.bulkAssign');

    const [projectId, setProjectId] = useState(filters?.project_id ? String(filters.project_id) : '');
    const [blockId, setBlockId] = useState(filters?.block_id ? String(filters.block_id) : '');
    const [category, setCategory] = useState(filters?.category || 'mdf');
    const [selectedIds, setSelectedIds] = useState(() => new Set());
    const [activeCell, setActiveCell] = useState(null);
    const [bulkOpen, setBulkOpen] = useState(false);

    const floors = matrix?.floors || [];
    const units = matrix?.units || [];
    const cells = matrix?.cells || {};
    const summary = matrix?.summary || {};

    const cellForm = useForm({
        status: 'pending',
        assigned_worker_id: '',
        is_company_crew: false,
        notes: '',
    });

    const bulkForm = useForm({
        unit_ids: [],
        status: '',
        assigned_worker_id: '',
        is_company_crew: false,
        notes: '',
    });

    const applyFilters = (next = {}) => {
        const pid = next.project_id !== undefined ? next.project_id : projectId;
        const bid = next.block_id !== undefined ? next.block_id : blockId;
        const cat = next.category !== undefined ? next.category : category;
        setSelectedIds(new Set());
        router.get(
            route('spatial.index'),
            {
                project_id: pid || undefined,
                block_id: bid || undefined,
                category: cat || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const openCell = (cell, floor, unitLabel) => {
        if (!canUpdate) return;
        if (!cell) return;
        setActiveCell(cell);
        cellForm.setData({
            status: cell.status || 'pending',
            assigned_worker_id: cell.assigned_worker_id ? String(cell.assigned_worker_id) : '',
            is_company_crew: !!cell.is_company_crew,
            notes: cell.notes || '',
        });
        cellForm.clearErrors();
    };

    const saveCell = (e) => {
        e.preventDefault();
        if (!activeCell) return;
        cellForm.transform((data) => ({
            status: data.status,
            notes: data.notes || null,
            is_company_crew: !!data.is_company_crew,
            assigned_worker_id: data.is_company_crew
                ? null
                : data.assigned_worker_id
                  ? Number(data.assigned_worker_id)
                  : null,
        }));
        cellForm.patch(route('spatial.units.update', activeCell.id), {
            preserveScroll: true,
            onSuccess: () => {
                setActiveCell(null);
                cellForm.reset();
            },
        });
    };

    const toggleSelect = (id) => {
        if (!canBulk) return;
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const selectFloor = (floor) => {
        if (!canBulk) return;
        const ids = units
            .map((u) => cells[cellKey(floor, u)]?.id)
            .filter(Boolean);
        setSelectedIds((prev) => {
            const next = new Set(prev);
            const allSelected = ids.every((id) => next.has(id));
            ids.forEach((id) => (allSelected ? next.delete(id) : next.add(id)));
            return next;
        });
    };

    const openBulk = () => {
        bulkForm.setData({
            unit_ids: Array.from(selectedIds),
            status: '',
            assigned_worker_id: '',
            is_company_crew: false,
            notes: '',
        });
        bulkForm.clearErrors();
        setBulkOpen(true);
    };

    const saveBulk = (e) => {
        e.preventDefault();
        bulkForm.transform((data) => {
            const payload = {
                unit_ids: Array.from(selectedIds),
                is_company_crew: !!data.is_company_crew,
            };
            if (data.status) payload.status = data.status;
            if (data.notes) payload.notes = data.notes;
            if (data.is_company_crew) {
                payload.assigned_worker_id = null;
            } else if (data.assigned_worker_id) {
                payload.assigned_worker_id = Number(data.assigned_worker_id);
            }
            return payload;
        });
        bulkForm.post(route('spatial.bulk-assign'), {
            preserveScroll: true,
            onSuccess: () => {
                setBulkOpen(false);
                setSelectedIds(new Set());
                bulkForm.reset();
            },
        });
    };

    const legend = useMemo(
        () =>
            (statuses || ['pending', 'in_progress', 'completed', 'inspected']).map((s) => (
                <span key={s} className="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                    <span className={`inline-block h-2.5 w-2.5 rounded-sm border ${STATUS_CELL[s] || ''}`} />
                    <StatusBadge status={s} />
                </span>
            )),
        [statuses],
    );

    const hasGrid = floors.length > 0 && units.length > 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('spatial_grid')}
                    subtitle={t('spatial_grid_subtitle')}
                    actions={
                        canBulk && selectedIds.size > 0 ? (
                            <PrimaryButton type="button" onClick={openBulk}>
                                {t('spatial_bulk_assign')} ({selectedIds.size})
                            </PrimaryButton>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('spatial_grid')} />
            <PageShell>
                <DataPanel>
                    <div className="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
                        <div className="min-w-[10rem] flex-1">
                            <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                {t('project')}
                            </label>
                            <select
                                className={selectClass}
                                value={projectId}
                                onChange={(e) => {
                                    const v = e.target.value;
                                    setProjectId(v);
                                    setBlockId('');
                                    applyFilters({ project_id: v, block_id: '' });
                                }}
                            >
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="min-w-[8rem] flex-1">
                            <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                {t('spatial_block')}
                            </label>
                            <select
                                className={selectClass}
                                value={blockId}
                                onChange={(e) => {
                                    const v = e.target.value;
                                    setBlockId(v);
                                    applyFilters({ block_id: v });
                                }}
                            >
                                {(blocks || []).length === 0 ? (
                                    <option value="">{t('spatial_no_blocks')}</option>
                                ) : (
                                    (blocks || []).map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.code}
                                            {b.name ? ` — ${b.name}` : ''}
                                        </option>
                                    ))
                                )}
                            </select>
                        </div>
                        <div className="w-full lg:w-auto">
                            <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                {t('spatial_category')}
                            </label>
                            <div className="flex flex-wrap gap-2">
                                {(categories || []).map((cat) => {
                                    const active = category === cat;
                                    return (
                                        <button
                                            key={cat}
                                            type="button"
                                            onClick={() => {
                                                setCategory(cat);
                                                applyFilters({ category: cat });
                                            }}
                                            className={`rounded-md px-3 py-1.5 text-sm font-medium transition ${
                                                active
                                                    ? 'bg-emerald-700 text-white shadow-sm dark:bg-emerald-600'
                                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'
                                            }`}
                                        >
                                            {categoryLabel(t, cat)}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                        {legend}
                        <span className="text-xs text-slate-500">
                            {t('spatial_xoman_hint')}
                        </span>
                    </div>

                    {project && (
                        <div className="mt-3 flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-300">
                            <span>
                                {t('spatial_total')}: <strong className="tabular-nums">{summary.total || 0}</strong>
                            </span>
                            <span>
                                {t('status_pending')}: <strong className="tabular-nums">{summary.pending || 0}</strong>
                            </span>
                            <span>
                                {t('status_in_progress')}:{' '}
                                <strong className="tabular-nums">{summary.in_progress || 0}</strong>
                            </span>
                            <span>
                                {t('status_completed')}:{' '}
                                <strong className="tabular-nums">{summary.completed || 0}</strong>
                            </span>
                            <span>
                                {t('status_inspected')}:{' '}
                                <strong className="tabular-nums">{summary.inspected || 0}</strong>
                            </span>
                            <span>
                                xoman: <strong className="tabular-nums">{summary.company_crew || 0}</strong>
                            </span>
                        </div>
                    )}
                </DataPanel>

                {!hasGrid ? (
                    <EmptyState
                        title={t('spatial_empty')}
                        description={t('spatial_empty_hint')}
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="overflow-x-auto">
                            <table className="min-w-full border-collapse text-sm">
                                <thead>
                                    <tr className="bg-slate-50 dark:bg-slate-900/80">
                                        <th className="sticky left-0 z-10 border border-slate-200 bg-slate-50 px-2 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                                            {t('spatial_floor')}
                                        </th>
                                        {units.map((u) => (
                                            <th
                                                key={u}
                                                className="border border-slate-200 px-2 py-2 text-center text-xs font-semibold tabular-nums text-slate-600 dark:border-slate-700 dark:text-slate-300"
                                            >
                                                {u}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {[...floors].reverse().map((floor) => (
                                        <tr key={floor}>
                                            <th className="sticky left-0 z-10 border border-slate-200 bg-white px-2 py-1.5 text-left font-semibold tabular-nums text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                <button
                                                    type="button"
                                                    className="underline-offset-2 hover:underline"
                                                    onClick={() => selectFloor(floor)}
                                                    title={t('spatial_select_floor')}
                                                >
                                                    {t('spatial_floor')} {floor}
                                                </button>
                                            </th>
                                            {units.map((unitLabel) => {
                                                const key = cellKey(floor, unitLabel);
                                                const cell = cells[key];
                                                const selected = cell && selectedIds.has(cell.id);
                                                const tone = cell
                                                    ? STATUS_CELL[cell.status] || STATUS_CELL.pending
                                                    : 'border-slate-100 bg-slate-50/50 text-slate-400 dark:border-slate-800 dark:bg-slate-900/40';
                                                return (
                                                    <td
                                                        key={key}
                                                        className={`border p-1 align-top dark:border-slate-700 ${
                                                            selected ? 'ring-2 ring-inset ring-emerald-500' : ''
                                                        }`}
                                                    >
                                                        {cell ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => openCell(cell, floor, unitLabel)}
                                                                onContextMenu={(e) => {
                                                                    e.preventDefault();
                                                                    toggleSelect(cell.id);
                                                                }}
                                                                className={`flex min-h-[3.25rem] w-full min-w-[5.5rem] flex-col items-stretch gap-1 rounded-sm border px-1.5 py-1 text-left transition hover:brightness-95 ${tone}`}
                                                            >
                                                                <div className="flex items-center justify-between gap-1">
                                                                    <StatusBadge
                                                                        status={cell.status}
                                                                        className="!px-1 !py-0 text-[10px]"
                                                                    />
                                                                    {canBulk && (
                                                                        <input
                                                                            type="checkbox"
                                                                            className="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                                            checked={!!selected}
                                                                            onChange={() => toggleSelect(cell.id)}
                                                                            onClick={(e) => e.stopPropagation()}
                                                                            aria-label={t('spatial_select_cell')}
                                                                        />
                                                                    )}
                                                                </div>
                                                                <span className="truncate text-xs font-medium leading-tight">
                                                                    {cell.is_company_crew
                                                                        ? 'xoman'
                                                                        : cell.assigned_worker?.name || '—'}
                                                                </span>
                                                            </button>
                                                        ) : (
                                                            <div className={`min-h-[3.25rem] min-w-[5.5rem] rounded-sm border ${tone}`} />
                                                        )}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="border-t border-slate-100 px-4 py-2 text-xs text-slate-500 dark:border-slate-800">
                            {t('spatial_mobile_hint')}
                        </p>
                    </DataPanel>
                )}
            </PageShell>

            <Modal show={!!activeCell} onClose={() => setActiveCell(null)} maxWidth="md">
                <form onSubmit={saveCell} className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {t('spatial_edit_cell')}
                    </h2>
                    {activeCell && (
                        <p className="mt-1 text-sm text-slate-500">
                            {t('spatial_floor')} {activeCell.floor_number} · {t('spatial_unit')}{' '}
                            {activeCell.unit_label} · {categoryLabel(t, activeCell.category)}
                        </p>
                    )}

                    <div className="mt-4 space-y-4">
                        <div>
                            <InputLabel value={t('status')} />
                            <select
                                className={`mt-1 ${selectClass}`}
                                value={cellForm.data.status}
                                onChange={(e) => cellForm.setData('status', e.target.value)}
                            >
                                {(statuses || []).map((s) => (
                                    <option key={s} value={s}>
                                        {t(`status_${s}`) !== `status_${s}` ? t(`status_${s}`) : s}
                                    </option>
                                ))}
                            </select>
                            <InputError message={cellForm.errors.status} className="mt-1" />
                        </div>

                        <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input
                                type="checkbox"
                                className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                checked={!!cellForm.data.is_company_crew}
                                onChange={(e) =>
                                    cellForm.setData({
                                        ...cellForm.data,
                                        is_company_crew: e.target.checked,
                                        assigned_worker_id: e.target.checked
                                            ? ''
                                            : cellForm.data.assigned_worker_id,
                                    })
                                }
                            />
                            {t('spatial_company_crew')} (xoman / خۆمان)
                        </label>

                        {!cellForm.data.is_company_crew && (
                            <div>
                                <InputLabel value={t('spatial_assign_person')} />
                                <select
                                    className={`mt-1 ${selectClass}`}
                                    value={cellForm.data.assigned_worker_id}
                                    onChange={(e) =>
                                        cellForm.setData('assigned_worker_id', e.target.value)
                                    }
                                >
                                    <option value="">{t('spatial_unassigned')}</option>
                                    {(people || []).map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                            {p.labor_kind && p.labor_kind !== 'unclassified'
                                                ? ` (${p.labor_kind})`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={cellForm.errors.assigned_worker_id}
                                    className="mt-1"
                                />
                            </div>
                        )}

                        <div>
                            <InputLabel value={t('notes')} />
                            <TextInput
                                className="mt-1 block w-full"
                                value={cellForm.data.notes}
                                onChange={(e) => cellForm.setData('notes', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <SecondaryButton type="button" onClick={() => setActiveCell(null)}>
                            {t('cancel')}
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={cellForm.processing}>
                            {t('save')}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal show={bulkOpen} onClose={() => setBulkOpen(false)} maxWidth="md">
                <form onSubmit={saveBulk} className="p-6">
                    <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {t('spatial_bulk_assign')}
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {selectedIds.size} {t('spatial_units_selected')}
                    </p>

                    <div className="mt-4 space-y-4">
                        <div>
                            <InputLabel value={t('status')} />
                            <select
                                className={`mt-1 ${selectClass}`}
                                value={bulkForm.data.status}
                                onChange={(e) => bulkForm.setData('status', e.target.value)}
                            >
                                <option value="">{t('spatial_keep_status')}</option>
                                {(statuses || []).map((s) => (
                                    <option key={s} value={s}>
                                        {t(`status_${s}`) !== `status_${s}` ? t(`status_${s}`) : s}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input
                                type="checkbox"
                                className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                checked={!!bulkForm.data.is_company_crew}
                                onChange={(e) =>
                                    bulkForm.setData({
                                        ...bulkForm.data,
                                        is_company_crew: e.target.checked,
                                        assigned_worker_id: e.target.checked
                                            ? ''
                                            : bulkForm.data.assigned_worker_id,
                                    })
                                }
                            />
                            {t('spatial_company_crew')} (xoman)
                        </label>

                        {!bulkForm.data.is_company_crew && (
                            <div>
                                <InputLabel value={t('spatial_assign_person')} />
                                <select
                                    className={`mt-1 ${selectClass}`}
                                    value={bulkForm.data.assigned_worker_id}
                                    onChange={(e) =>
                                        bulkForm.setData('assigned_worker_id', e.target.value)
                                    }
                                >
                                    <option value="">{t('spatial_keep_person')}</option>
                                    {(people || []).map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    <div className="mt-6 flex justify-end gap-2">
                        <SecondaryButton type="button" onClick={() => setBulkOpen(false)}>
                            {t('cancel')}
                        </SecondaryButton>
                        <PrimaryButton type="submit" disabled={bulkForm.processing}>
                            {t('apply')}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
