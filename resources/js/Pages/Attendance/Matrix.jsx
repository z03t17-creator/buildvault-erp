import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function formatTime(value) {
    if (!value) return '—';
    return String(value).slice(0, 5);
}

export default function Matrix({ date, projectId, projects, floors, grid }) {
    const t = useTranslations();
    const rows = grid || [];
    const [selected, setSelected] = useState([]);

    const allIds = useMemo(() => rows.map((r) => r.worker.id), [rows]);
    const allSelected = allIds.length > 0 && selected.length === allIds.length;

    const checkIn = useForm({
        date,
        check_in: '08:00',
        floor_id: floors?.[0]?.id || '',
        worker_ids: [],
    });
    const checkOut = useForm({
        date,
        check_out: '17:00',
        worker_ids: [],
    });

    const toggle = (id) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const toggleAll = () => {
        setSelected(allSelected ? [] : allIds);
    };

    const submitCheckIn = () => {
        checkIn.transform((data) => ({ ...data, worker_ids: selected })).post(route('attendance.check-in'), {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    };

    const submitCheckOut = () => {
        checkOut.transform((data) => ({ ...data, worker_ids: selected })).post(route('attendance.check-out'), {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    };

    const filter = (next) => {
        router.get(route('attendance.index'), next, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('matrix_title')}
                    subtitle={t('matrix_hint')}
                />
            }
        >
            <Head title={t('matrix_title')} />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-end gap-4 border border-slate-200/80 bg-white/80 p-4 dark:border-slate-700 dark:bg-slate-900/70">
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('date')}</label>
                            <input
                                type="date"
                                className="mt-1 rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                value={date}
                                onChange={(e) => filter({ date: e.target.value, project_id: projectId || undefined })}
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('project')}</label>
                            <select
                                className="mt-1 rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                value={projectId || ''}
                                onChange={(e) => filter({ date, project_id: e.target.value || undefined })}
                            >
                                <option value="">{t('all_projects')}</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('floor')}</label>
                            <select
                                className="mt-1 rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                value={checkIn.data.floor_id}
                                onChange={(e) => checkIn.setData('floor_id', e.target.value)}
                            >
                                <option value="">—</option>
                                {(floors || []).map((f) => (
                                    <option key={f.id} value={f.id}>
                                        {f.tower?.name ? `${f.tower.name} / ` : ''}{f.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('check_in')}</label>
                            <input
                                type="time"
                                className="mt-1 rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                value={checkIn.data.check_in}
                                onChange={(e) => checkIn.setData('check_in', e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-500">{t('check_out')}</label>
                            <input
                                type="time"
                                className="mt-1 rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                                value={checkOut.data.check_out}
                                onChange={(e) => checkOut.setData('check_out', e.target.value)}
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <PrimaryButton type="button" disabled={selected.length === 0 || checkIn.processing} onClick={submitCheckIn}>
                                {t('bulk_check_in')} ({selected.length})
                            </PrimaryButton>
                            <SecondaryButton type="button" disabled={selected.length === 0 || checkOut.processing} onClick={submitCheckOut}>
                                {t('bulk_check_out')} ({selected.length})
                            </SecondaryButton>
                        </div>
                    </div>

                    <div className="bv-surface shadow-sm">
                        <div className="bv-table-wrap">
                        <table className="bv-table min-w-[44rem] border-collapse">
                            <thead className="sticky top-0 z-10 bg-slate-100/95 text-start text-xs uppercase tracking-wider text-slate-500 backdrop-blur dark:bg-slate-950/95 dark:text-slate-400">
                                <tr>
                                    <th className="border-b border-slate-200 px-3 py-3 dark:border-slate-800">
                                        <input
                                            type="checkbox"
                                            checked={allSelected}
                                            onChange={toggleAll}
                                            aria-label={t('select')}
                                            className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                    </th>
                                    <th className="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-800">{t('worker')}</th>
                                    <th className="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-800">{t('project')}</th>
                                    <th className="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-800">{t('check_in')}</th>
                                    <th className="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-800">{t('check_out')}</th>
                                    <th className="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-800">{t('status')}</th>
                                    <th className="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-800">{t('late_min')}</th>
                                    <th className="border-b border-slate-200 px-3 py-3 font-semibold dark:border-slate-800">{t('ot_hours')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-10 text-center text-slate-500">
                                            {t('no_workers')}
                                        </td>
                                    </tr>
                                )}
                                {rows.map(({ worker, attendance }, index) => {
                                    const checked = selected.includes(worker.id);
                                    return (
                                        <tr
                                            key={worker.id}
                                            className={`border-b border-slate-100 dark:border-slate-800 ${
                                                index % 2 === 0
                                                    ? 'bg-white/40 dark:bg-slate-900/40'
                                                    : 'bg-slate-50/50 dark:bg-slate-950/30'
                                            } ${checked ? '!bg-emerald-50/80 dark:!bg-emerald-950/35' : ''}`}
                                        >
                                            <td className="px-3 py-2.5">
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    onChange={() => toggle(worker.id)}
                                                    className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                />
                                            </td>
                                            <td className="px-3 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                {worker.name}
                                            </td>
                                            <td className="px-3 py-2.5 text-slate-600 dark:text-slate-300">
                                                {worker.project?.name || t('unassigned')}
                                            </td>
                                            <td className="px-3 py-2.5 font-mono tabular-nums text-slate-700 dark:text-slate-200">
                                                {formatTime(attendance?.check_in)}
                                            </td>
                                            <td className="px-3 py-2.5 font-mono tabular-nums text-slate-700 dark:text-slate-200">
                                                {formatTime(attendance?.check_out)}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                {attendance?.status ? (
                                                    <StatusBadge status={attendance.status} />
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5 tabular-nums text-slate-700 dark:text-slate-200">
                                                {attendance?.late_minutes ?? '—'}
                                            </td>
                                            <td className="px-3 py-2.5 tabular-nums text-slate-700 dark:text-slate-200">
                                                {attendance?.overtime_hours ?? '—'}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
