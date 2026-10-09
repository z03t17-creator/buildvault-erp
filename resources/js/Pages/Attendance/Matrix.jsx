import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import DateInput from '@/Components/DateInput';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { isValidIsoDate } from '@/lib/isoDate';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

function formatTime(value) {
    if (!value) return '—';
    return String(value).slice(0, 5);
}

function CountStat({ label, value, hint, active = false }) {
    return (
        <div
            className={
                'bv-card px-4 py-3.5 ' +
                (active ? 'ring-2 ring-indigo-500/40 dark:ring-indigo-400/40' : '')
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
    if (!status) {
        return <span className="text-xs text-slate-400">—</span>;
    }
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status.replace(/_/g, ' ');
    const tones = {
        present: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        late: 'bg-amber-500/15 text-amber-950 dark:text-amber-200',
        absent_unexcused: 'bg-rose-500/15 text-rose-900 dark:text-rose-300',
        leave_paid: 'bg-sky-500/15 text-sky-900 dark:text-sky-300',
        leave_sick: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
        forfeit_day: 'bg-rose-500/15 text-rose-900 dark:text-rose-300',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold capitalize ' +
                (tones[status] || tones.present)
            }
        >
            {label}
        </span>
    );
}

function RoleChip({ role, t }) {
    const key = `worker_role_${role}`;
    const label = t(key) !== key ? t(key) : role?.replace(/_/g, ' ') || '—';

    return (
        <span className="inline-flex items-center rounded-lg bg-indigo-500/10 px-2 py-0.5 text-[11px] font-semibold text-indigo-900 dark:text-indigo-200">
            {label}
        </span>
    );
}

export default function Matrix({
    date,
    projectId,
    projects,
    floors,
    grid,
    daySummary,
    shiftStart,
    lateForfeitMinutes,
}) {
    const t = useTranslations();
    const canManage = useCan('attendance.manage');
    const canAddStaff = useCan('vault.ledgerManage');
    const rows = grid || [];
    const [selected, setSelected] = useState([]);
    const [dateInput, setDateInput] = useState(date || '');
    const summary = daySummary || {
        staff: rows.length,
        recorded: 0,
        present: 0,
        late: 0,
        absent: 0,
        leave: 0,
        forfeit_days: 0,
        unchecked: rows.length,
    };

    const allIds = useMemo(() => rows.map((r) => r.staff.id), [rows]);
    const allSelected = allIds.length > 0 && selected.length === allIds.length;

    const checkIn = useForm({
        date,
        check_in: shiftStart || '08:00',
        shift_start: shiftStart || '08:00',
        floor_id: floors?.[0]?.id || '',
        project_id: projectId || '',
        staff_ids: [],
    });
    const checkOut = useForm({
        date,
        check_out: '17:00',
        staff_ids: [],
    });

    const toggle = (id) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const toggleAll = () => setSelected(allSelected ? [] : allIds);

    const filter = (next) => {
        router.get(route('attendance.index'), next, {
            preserveState: true,
            replace: true,
        });
    };

    const applyDate = () => {
        if (!isValidIsoDate(dateInput)) {
            return;
        }
        filter({
            date: dateInput,
            project_id: projectId || undefined,
        });
    };

    const submitCheckIn = () => {
        checkIn
            .transform((data) => ({
                ...data,
                date: dateInput || date,
                staff_ids: selected,
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
            .transform((data) => ({
                ...data,
                date: dateInput || date,
                staff_ids: selected,
            }))
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
                    subtitle={t('attendance_page_hint')}
                    icon={<NavIcon name="attendance" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canAddStaff ? (
                                <Link
                                    href={route('staff.create')}
                                >
                                    <SecondaryButton type="button">
                                        <NavIcon name="payroll" className="text-sm" />
                                        {t('vault_form_add_staff')}
                                    </SecondaryButton>
                                </Link>
                            ) : null}

                        </div>
                    }
                />
            }
        >
            <Head title={`${t('attendance')} · ${date}`} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('attendance_filters_title')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('attendance_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:items-end">
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('date')}
                            </label>
                            <DateInput
                                className="mt-1"
                                value={dateInput}
                                onValueChange={(next) => {
                                    setDateInput(next);
                                    if (isValidIsoDate(next)) {
                                        filter({
                                            date: next,
                                            project_id: projectId || '',
                                        });
                                    }
                                }}
                                onBlur={applyDate}
                            />
                        </div>
                        <div>
                            <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {t('project')}
                            </label>
                            <select
                                className={fieldClass}
                                value={projectId || ''}
                                onChange={(e) =>
                                    filter({
                                        date: isValidIsoDate(dateInput)
                                            ? dateInput
                                            : date,
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
                        <p className="text-xs text-slate-500 dark:text-slate-400 sm:col-span-2 lg:col-span-1">
                            {t('attendance_employees_only')}
                            {lateForfeitMinutes
                                ? ` · ${t('attendance_late_note', {
                                      minutes: lateForfeitMinutes,
                                  })}`
                                : ''}
                        </p>
                    </div>
                </section>

                <section>
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                            <NavIcon name="attendance" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('attendance_overview_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('attendance_overview_hint', { date })}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-7">
                        <CountStat
                            label={t('staff')}
                            value={summary.staff}
                            hint={t('attendance_stat_staff_hint')}
                        />
                        <CountStat
                            label={t('status_present')}
                            value={summary.present}
                            hint={t('worker_att_present_hint')}
                            active={summary.present > 0}
                        />
                        <CountStat
                            label={t('status_late')}
                            value={summary.late}
                            hint={t('worker_att_late_hint')}
                            active={summary.late > 0}
                        />
                        <CountStat
                            label={t('status_absent_unexcused')}
                            value={summary.absent}
                            hint={t('worker_att_absent_hint')}
                            active={summary.absent > 0}
                        />
                        <CountStat
                            label={t('worker_att_leave')}
                            value={summary.leave}
                            hint={t('worker_att_leave_hint')}
                        />
                        <CountStat
                            label={t('worker_att_forfeit')}
                            value={summary.forfeit_days}
                            hint={t('worker_att_forfeit_hint')}
                            active={summary.forfeit_days > 0}
                        />
                        <CountStat
                            label={t('attendance_unchecked')}
                            value={summary.unchecked}
                            hint={t('attendance_stat_unchecked_hint')}
                            active={summary.unchecked > 0}
                        />
                    </div>
                </section>

                {canManage && (
                    <section className="bv-card p-4 sm:p-5">
                        <div className="mb-3 flex flex-wrap items-start justify-between gap-3">
                            <div className="flex items-start gap-3">
                                <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                                    <NavIcon name="attendance" className="text-base" />
                                </span>
                                <div>
                                    <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                        {t('attendance_actions')}
                                    </h2>
                                    <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                        {t('attendance_actions_hint', {
                                            count: selected.length,
                                        })}
                                    </p>
                                </div>
                            </div>
                            <span className="rounded-lg bg-indigo-500/10 px-2.5 py-1 text-xs font-semibold text-indigo-900 dark:text-indigo-200">
                                {t('attendance_selected', {
                                    count: selected.length,
                                })}
                            </span>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('shift_start')}
                                </label>
                                <input
                                    type="time"
                                    className={fieldClass}
                                    value={checkIn.data.shift_start}
                                    onChange={(e) =>
                                        checkIn.setData('shift_start', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('check_in')}
                                </label>
                                <input
                                    type="time"
                                    className={fieldClass}
                                    value={checkIn.data.check_in}
                                    onChange={(e) =>
                                        checkIn.setData('check_in', e.target.value)
                                    }
                                />
                            </div>
                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('floor')}
                                </label>
                                <select
                                    className={fieldClass}
                                    value={checkIn.data.floor_id}
                                    onChange={(e) =>
                                        checkIn.setData('floor_id', e.target.value)
                                    }
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
                                className="!bg-indigo-600 hover:!bg-indigo-500 dark:!bg-indigo-400 dark:!text-indigo-950"
                                disabled={!selected.length || checkIn.processing}
                                onClick={submitCheckIn}
                            >
                                <NavIcon name="attendance" className="text-sm" />
                                {t('check_in_selected')}
                            </PrimaryButton>
                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {t('check_out')}
                                </label>
                                <input
                                    type="time"
                                    className={fieldClass}
                                    value={checkOut.data.check_out}
                                    onChange={(e) =>
                                        checkOut.setData('check_out', e.target.value)
                                    }
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
                                    router.post(route('attendance.mark-absences'), {
                                        date: isValidIsoDate(dateInput)
                                            ? dateInput
                                            : date,
                                    })
                                }
                            >
                                {t('mark_absences')}
                            </SecondaryButton>
                        </div>
                        {checkIn.errors.check_in ? (
                            <p className="mt-2 text-sm text-rose-600">
                                {checkIn.errors.check_in}
                            </p>
                        ) : null}
                    </section>
                )}

                {rows.length === 0 ? (
                    <EmptyState
                        icon="attendance"
                        title={t('attendance_empty')}
                        description={t('attendance_empty_hint')}
                        action={
                            canAddStaff ? (
                                <Link
                                    href={route('staff.create')}
                                >
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-indigo-600 hover:!bg-indigo-500"
                                    >
                                        <NavIcon name="payroll" className="text-sm" />
                                        {t('vault_form_add_staff')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                                <NavIcon name="attendance" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('attendance_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('attendance_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable
                            minWidth="52rem"
                            caption={t('attendance')}
                            stickyFirstColumn
                        >
                            <thead>
                                <tr>
                                    <Th>
                                        <span className="inline-flex items-center gap-2">
                                            {canManage ? (
                                                <input
                                                    type="checkbox"
                                                    checked={allSelected}
                                                    onChange={toggleAll}
                                                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    aria-label={t('attendance_select_all')}
                                                />
                                            ) : null}
                                            {t('staff_name')}
                                        </span>
                                    </Th>
                                    <Th>{t('project')}</Th>
                                    <Th>{t('check_in')}</Th>
                                    <Th>{t('check_out')}</Th>
                                    <Th align="end">{t('late_minutes')}</Th>
                                    <Th>{t('status')}</Th>
                                    <Th>{t('forfeit_day')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map(({ staff, attendance }) => (
                                    <tr
                                        key={staff.id}
                                        className={
                                            attendance?.forfeit_day
                                                ? 'bg-rose-50/50 dark:bg-rose-950/20'
                                                : attendance?.status === 'late'
                                                  ? 'bg-amber-50/40 dark:bg-amber-950/10'
                                                  : ''
                                        }
                                    >
                                        <Td>
                                            <div className="flex items-start gap-2.5">
                                                {canManage ? (
                                                    <input
                                                        type="checkbox"
                                                        checked={selected.includes(
                                                            staff.id,
                                                        )}
                                                        onChange={() =>
                                                            toggle(staff.id)
                                                        }
                                                        className="mt-2 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                        aria-label={staff.name}
                                                    />
                                                ) : null}
                                                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/15 text-indigo-900 dark:bg-indigo-400/15 dark:text-indigo-200">
                                                    <NavIcon
                                                        name="payroll"
                                                        className="text-sm"
                                                    />
                                                </span>
                                                <span>
                                                    <Link
                                                        href={route('staff.create')}
                                                        className="font-medium text-indigo-950 underline-offset-2 hover:underline dark:text-indigo-100"
                                                    >
                                                        {staff.name}
                                                    </Link>
                                                    {staff.role ? (
                                                        <span className="mt-1 block">
                                                            <RoleChip
                                                                role={staff.role}
                                                                t={t}
                                                            />
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </div>
                                        </Td>
                                        <Td muted>
                                            {staff.project?.name || '—'}
                                        </Td>
                                        <Td className="font-sans tabular-nums">
                                            {formatTime(attendance?.check_in)}
                                        </Td>
                                        <Td className="font-sans tabular-nums">
                                            {formatTime(attendance?.check_out)}
                                        </Td>
                                        <Td
                                            align="end"
                                            className="font-sans tabular-nums"
                                        >
                                            {attendance?.late_minutes ?? 0}
                                        </Td>
                                        <Td>
                                            <StatusChip
                                                status={attendance?.status}
                                                t={t}
                                            />
                                        </Td>
                                        <Td>
                                            {attendance?.forfeit_day ? (
                                                <StatusChip
                                                    status="forfeit_day"
                                                    t={t}
                                                />
                                            ) : (
                                                <span className="text-slate-300">—</span>
                                            )}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    </DataPanel>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
