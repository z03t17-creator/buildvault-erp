import DataPanel from '@/Components/DataPanel';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const selectClass =
    'rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

function formatTime(value) {
    if (!value) return '—';
    return String(value).slice(0, 5);
}

export default function Matrix({
    date,
    projectId,
    projects,
    floors,
    grid,
    shiftStart,
    lateForfeitMinutes,
}) {
    const t = useTranslations();
    const canManage = useCan('attendance.manage');
    const rows = grid || [];
    const [selected, setSelected] = useState([]);

    const allIds = useMemo(() => rows.map((r) => r.worker.id), [rows]);
    const allSelected = allIds.length > 0 && selected.length === allIds.length;

    const checkIn = useForm({
        date,
        check_in: shiftStart || '08:00',
        shift_start: shiftStart || '08:00',
        floor_id: floors?.[0]?.id || '',
        project_id: projectId || '',
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

    const toggleAll = () => setSelected(allSelected ? [] : allIds);

    const filter = (next) => {
        router.get(route('attendance.index'), next, { preserveState: true, replace: true });
    };

    const submitCheckIn = () => {
        checkIn
            .transform((data) => ({
                ...data,
                worker_ids: selected,
                floor_id: data.floor_id || null,
                project_id: data.project_id || null,
            }))
            .post(route('attendance.check-in'), {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
            });
    };

    const submitCheckOut = () => {
        checkOut
            .transform((data) => ({ ...data, worker_ids: selected }))
            .post(route('attendance.check-out'), {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
            });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('attendance')}
                    subtitle={t('attendance_subtitle', {
                        minutes: lateForfeitMinutes || 30,
                    })}
                />
            }
        >
            <Head title={t('attendance')} />
            <PageShell>
                <DataPanel>
                    <div className="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
                        <div>
                            <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                {t('date')}
                            </label>
                            <input
                                type="date"
                                className={selectClass}
                                value={date}
                                onChange={(e) =>
                                    filter({
                                        date: e.target.value,
                                        project_id: projectId || undefined,
                                    })
                                }
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                {t('project')}
                            </label>
                            <select
                                className={selectClass}
                                value={projectId || ''}
                                onChange={(e) =>
                                    filter({
                                        date,
                                        project_id: e.target.value || undefined,
                                    })
                                }
                            >
                                <option value="">{t('all_projects')}</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <p className="text-xs text-slate-500 lg:ml-auto">
                            {t('attendance_employees_only')}
                        </p>
                    </div>
                </DataPanel>

                {canManage && (
                    <DataPanel title={t('attendance_actions')}>
                        <div className="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
                            <div>
                                <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                    {t('shift_start')}
                                </label>
                                <input
                                    type="time"
                                    className={selectClass}
                                    value={checkIn.data.shift_start}
                                    onChange={(e) =>
                                        checkIn.setData('shift_start', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                    {t('check_in')}
                                </label>
                                <input
                                    type="time"
                                    className={selectClass}
                                    value={checkIn.data.check_in}
                                    onChange={(e) => checkIn.setData('check_in', e.target.value)}
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                    {t('floor')}
                                </label>
                                <select
                                    className={selectClass}
                                    value={checkIn.data.floor_id}
                                    onChange={(e) => checkIn.setData('floor_id', e.target.value)}
                                >
                                    <option value="">—</option>
                                    {(floors || []).map((f) => (
                                        <option key={f.id} value={f.id}>
                                            {f.tower?.name ? `${f.tower.name} / ` : ''}
                                            {f.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <PrimaryButton
                                type="button"
                                disabled={!selected.length || checkIn.processing}
                                onClick={submitCheckIn}
                            >
                                {t('check_in_selected')}
                            </PrimaryButton>
                            <div>
                                <label className="mb-1 block text-xs uppercase tracking-wide text-slate-500">
                                    {t('check_out')}
                                </label>
                                <input
                                    type="time"
                                    className={selectClass}
                                    value={checkOut.data.check_out}
                                    onChange={(e) => checkOut.setData('check_out', e.target.value)}
                                />
                            </div>
                            <SecondaryButton
                                type="button"
                                disabled={!selected.length || checkOut.processing}
                                onClick={submitCheckOut}
                            >
                                {t('check_out_selected')}
                            </SecondaryButton>
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    router.post(route('attendance.mark-absences'), { date })
                                }
                            >
                                {t('mark_absences')}
                            </SecondaryButton>
                        </div>
                        {checkIn.errors.check_in && (
                            <p className="mt-2 text-sm text-rose-600">{checkIn.errors.check_in}</p>
                        )}
                    </DataPanel>
                )}

                {rows.length === 0 ? (
                    <EmptyState
                        title={t('attendance_empty')}
                        description={t('attendance_empty_hint')}
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50 dark:bg-slate-900/80">
                                    <tr>
                                        {canManage && (
                                            <th className="px-3 py-2 text-left">
                                                <input
                                                    type="checkbox"
                                                    checked={allSelected}
                                                    onChange={toggleAll}
                                                    className="rounded border-slate-300 text-emerald-600"
                                                />
                                            </th>
                                        )}
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            {t('worker_name')}
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            {t('project')}
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            {t('check_in')}
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            {t('check_out')}
                                        </th>
                                        <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            {t('late_minutes')}
                                        </th>
                                        <th className="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            {t('status')}
                                        </th>
                                        <th className="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            {t('forfeit_day')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map(({ worker, attendance }) => (
                                        <tr
                                            key={worker.id}
                                            className="border-t border-slate-100 dark:border-slate-800"
                                        >
                                            {canManage && (
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={selected.includes(worker.id)}
                                                        onChange={() => toggle(worker.id)}
                                                        className="rounded border-slate-300 text-emerald-600"
                                                    />
                                                </td>
                                            )}
                                            <td className="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">
                                                {worker.name}
                                            </td>
                                            <td className="px-3 py-2 text-slate-500">
                                                {worker.project?.name || '—'}
                                            </td>
                                            <td className="px-3 py-2 font-mono tabular-nums">
                                                {formatTime(attendance?.check_in)}
                                            </td>
                                            <td className="px-3 py-2 font-mono tabular-nums">
                                                {formatTime(attendance?.check_out)}
                                            </td>
                                            <td className="px-3 py-2 text-right font-mono tabular-nums">
                                                {attendance?.late_minutes ?? 0}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {attendance ? (
                                                    <StatusBadge status={attendance.status} />
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {attendance?.forfeit_day ? (
                                                    <StatusBadge status="forfeit_day" label={t('forfeit_day')} />
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
