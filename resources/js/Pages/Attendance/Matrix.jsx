import DateInput from '@/Components/DateInput';
import EmptyState from '@/Components/EmptyState';
import MoneyAmount from '@/Components/MoneyAmount';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { isValidIsoDate } from '@/lib/isoDate';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function KpiCard({ label, value, hint, tone = 'slate' }) {
    const tones = {
        slate: 'border-white/10 text-slate-100',
        emerald: 'border-emerald-400/25 text-emerald-200',
        rose: 'border-rose-400/25 text-rose-200',
        amber: 'border-amber-400/25 text-amber-200',
        sky: 'border-sky-400/25 text-sky-200',
    };

    return (
        <div
            className={
                'rounded-2xl border bg-white/[0.03] px-4 py-3.5 ' + (tones[tone] || tones.slate)
            }
        >
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </p>
            <p className="mt-1.5 font-sans text-2xl font-semibold tabular-nums sm:text-3xl">
                {value}
            </p>
            {hint ? <p className="mt-1 text-xs text-slate-500">{hint}</p> : null}
        </div>
    );
}

function StatusToggle({ active, label, tone, disabled, onClick }) {
    const tones = {
        present:
            'border-emerald-400/40 bg-emerald-500/15 text-emerald-100 hover:bg-emerald-500/25',
        presentOn: 'border-emerald-400/60 bg-emerald-500 text-emerald-950 shadow-lg shadow-emerald-950/30',
        absent: 'border-rose-400/40 bg-rose-500/15 text-rose-100 hover:bg-rose-500/25',
        absentOn: 'border-rose-400/60 bg-rose-500 text-white shadow-lg shadow-rose-950/30',
        leave: 'border-amber-400/40 bg-amber-500/15 text-amber-100 hover:bg-amber-500/25',
        leaveOn: 'border-amber-400/60 bg-amber-400 text-amber-950 shadow-lg shadow-amber-950/30',
    };

    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={
                'rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50 ' +
                (active ? tones[tone + 'On'] : tones[tone])
            }
        >
            {label}
        </button>
    );
}

function PayChip({ model, t }) {
    const key =
        model === 'daily'
            ? 'staff_pay_daily'
            : model === 'unit'
              ? 'staff_pay_unit'
              : 'staff_pay_monthly';
    const tone =
        model === 'daily'
            ? 'bg-amber-500/15 text-amber-200'
            : model === 'unit'
              ? 'bg-sky-500/15 text-sky-200'
              : 'bg-teal-500/15 text-teal-200';

    return (
        <span className={'inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-semibold ' + tone}>
            {t(key)}
        </span>
    );
}

function uiStatus(status) {
    if (!status) return null;
    if (status === 'present' || status === 'late') return 'present';
    if (status === 'absent_unexcused') return 'absent';
    if (status === 'half_day' || status === 'leave_paid' || status === 'leave_sick') {
        return 'leave';
    }
    return null;
}

export default function Matrix({
    date,
    tab = 'day',
    projectId,
    projects,
    grid,
    unitStaff,
    daySummary,
}) {
    const t = useTranslations();
    const canManage = useCan('attendance.manage');
    const canAddStaff = useCan('vault.ledgerManage');
    const rows = grid || [];
    const units = unitStaff || [];
    const [dateInput, setDateInput] = useState(date || '');
    const [savingKey, setSavingKey] = useState(null);
    const [localExtras, setLocalExtras] = useState({});
    const debounceRef = useRef({});

    useEffect(() => {
        setDateInput(date || '');
    }, [date]);

    const summary = daySummary || {
        staff: rows.length,
        present: 0,
        absent: 0,
        leave: 0,
        half_day: 0,
        unchecked: rows.length,
        wage_usd: 0,
        wage_iqd: 0,
        unit_staff: units.length,
    };

    const filter = (next) => {
        router.get(route('attendance.index'), next, {
            preserveState: true,
            replace: true,
        });
    };

    const goTab = (nextTab) => {
        filter({
            date: isValidIsoDate(dateInput) ? dateInput : date,
            project_id: projectId || undefined,
            tab: nextTab,
        });
    };

    const saveStatus = (staffId, status, extras = {}) => {
        if (!canManage) return;
        const key = `${staffId}:${status}:${extras.late_minutes ?? ''}:${extras.overtime_hours ?? ''}`;
        setSavingKey(key);
        router.post(
            route('attendance.status'),
            {
                date: isValidIsoDate(dateInput) ? dateInput : date,
                staff_id: staffId,
                status,
                project_id: projectId || null,
                ...extras,
            },
            {
                preserveScroll: true,
                only: ['grid', 'daySummary', 'flash'],
                onFinish: () => setSavingKey(null),
            },
        );
    };

    const markAllPresent = () => {
        if (!canManage) return;
        setSavingKey('all');
        router.post(
            route('attendance.mark-all-present'),
            {
                date: isValidIsoDate(dateInput) ? dateInput : date,
                project_id: projectId || null,
            },
            {
                preserveScroll: true,
                only: ['grid', 'daySummary', 'flash'],
                onFinish: () => setSavingKey(null),
            },
        );
    };

    const extrasFor = (staffId, attendance) => {
        const local = localExtras[staffId] || {};
        return {
            late_minutes:
                local.late_minutes !== undefined
                    ? local.late_minutes
                    : (attendance?.late_minutes ?? 0),
            overtime_hours:
                local.overtime_hours !== undefined
                    ? local.overtime_hours
                    : (attendance?.overtime_hours ?? 0),
        };
    };

    const queueExtraSave = (staffId, attendance, patch) => {
        const next = { ...extrasFor(staffId, attendance), ...patch };
        setLocalExtras((prev) => ({ ...prev, [staffId]: next }));
        const status =
            uiStatus(attendance?.status) === 'absent'
                ? 'absent_unexcused'
                : uiStatus(attendance?.status) === 'leave'
                  ? 'half_day'
                  : 'present';
        clearTimeout(debounceRef.current[staffId]);
        debounceRef.current[staffId] = setTimeout(() => {
            saveStatus(staffId, status, next);
        }, 450);
    };

    const leaveCount = (summary.leave || 0) + (summary.half_day || 0);

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('attendance')}
                    subtitle={t('attendance_desk_subtitle')}
                    icon={<NavIcon name="attendance" className="text-lg text-emerald-300" />}
                    actions={
                        canAddStaff ? (
                            <Link
                                href={route('staff.create')}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-400/30 bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-500/25"
                            >
                                <NavIcon name="workers" className="text-sm" />
                                {t('vault_form_add_staff')}
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={`${t('attendance')} · ${date}`} />

            <PageShell className="!max-w-6xl !space-y-5">
                <div
                    dir="rtl"
                    className="space-y-5 rounded-3xl border border-white/5 bg-[#0B0F19] p-4 text-slate-100 sm:p-6"
                >
                    {/* Date + tabs + mark all */}
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    {t('date')}
                                </label>
                                <DateInput
                                    className="mt-1 !min-h-[2.5rem] !rounded-xl !border-white/10 !bg-white/[0.04] !text-slate-100"
                                    value={dateInput}
                                    onValueChange={(next) => {
                                        setDateInput(next);
                                        if (isValidIsoDate(next)) {
                                            filter({
                                                date: next,
                                                project_id: projectId || undefined,
                                                tab,
                                            });
                                        }
                                    }}
                                />
                            </div>
                            <div>
                                <label className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    {t('project')}
                                </label>
                                <select
                                    className="mt-1 block min-h-[2.5rem] rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-slate-100"
                                    value={projectId || ''}
                                    onChange={(e) =>
                                        filter({
                                            date: isValidIsoDate(dateInput) ? dateInput : date,
                                            project_id: e.target.value || undefined,
                                            tab,
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
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <div className="inline-flex rounded-xl border border-white/10 bg-white/[0.03] p-1">
                                <button
                                    type="button"
                                    onClick={() => goTab('day')}
                                    className={
                                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition ' +
                                        (tab === 'day'
                                            ? 'bg-emerald-500/20 text-emerald-100'
                                            : 'text-slate-400 hover:text-slate-200')
                                    }
                                >
                                    {t('attendance_tab_day')}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => goTab('unit')}
                                    className={
                                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition ' +
                                        (tab === 'unit'
                                            ? 'bg-sky-500/20 text-sky-100'
                                            : 'text-slate-400 hover:text-slate-200')
                                    }
                                >
                                    {t('attendance_tab_unit')} ({summary.unit_staff || units.length})
                                </button>
                            </div>
                            {canManage && tab === 'day' ? (
                                <button
                                    type="button"
                                    disabled={savingKey === 'all' || rows.length === 0}
                                    onClick={markAllPresent}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-400/35 bg-emerald-500 px-3.5 py-2 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-400 disabled:opacity-50"
                                >
                                    {t('attendance_mark_all_present')}
                                </button>
                            ) : null}
                        </div>
                    </div>

                    {tab === 'day' ? (
                        <>
                            {/* KPIs */}
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <KpiCard
                                    label={t('attendance_kpi_present')}
                                    value={summary.present}
                                    hint={t('attendance_kpi_present_hint')}
                                    tone="emerald"
                                />
                                <KpiCard
                                    label={t('attendance_kpi_absent')}
                                    value={summary.absent}
                                    hint={t('attendance_kpi_absent_hint')}
                                    tone="rose"
                                />
                                <KpiCard
                                    label={t('attendance_kpi_leave')}
                                    value={leaveCount}
                                    hint={t('attendance_kpi_leave_hint')}
                                    tone="amber"
                                />
                                <div className="rounded-2xl border border-white/10 bg-gradient-to-br from-emerald-500/10 via-transparent to-transparent px-4 py-3.5">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                        {t('attendance_kpi_wages')}
                                    </p>
                                    <div className="mt-2 space-y-1">
                                        <p
                                            dir="ltr"
                                            className="font-sans text-lg font-semibold tabular-nums text-emerald-300"
                                        >
                                            <MoneyAmount
                                                value={summary.wage_usd || 0}
                                                label={t('USD')}
                                                size="md"
                                                showLabel={false}
                                                className="text-emerald-300"
                                            />{' '}
                                            <span className="text-xs text-slate-500">{t('USD')}</span>
                                        </p>
                                        <p
                                            dir="ltr"
                                            className="font-sans text-lg font-semibold tabular-nums text-emerald-200"
                                        >
                                            <MoneyAmount
                                                value={summary.wage_iqd || 0}
                                                label={t('IQD')}
                                                size="md"
                                                showLabel={false}
                                                className="text-emerald-200"
                                            />{' '}
                                            <span className="text-xs text-slate-500">{t('IQD')}</span>
                                        </p>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {t('attendance_kpi_wages_hint')}
                                    </p>
                                </div>
                            </div>

                            {rows.length === 0 ? (
                                <EmptyState
                                    icon="attendance"
                                    title={t('attendance_empty')}
                                    description={t('attendance_empty_desk_hint')}
                                    action={
                                        canAddStaff ? (
                                            <Link href={route('staff.create')}>
                                                <span className="inline-flex rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-emerald-950">
                                                    {t('vault_form_add_staff')}
                                                </span>
                                            </Link>
                                        ) : null
                                    }
                                />
                            ) : (
                                <div className="overflow-hidden rounded-2xl border border-white/10 bg-[#111827]/90">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 px-4 py-3">
                                        <div>
                                            <p className="text-sm font-semibold text-white">
                                                {t('attendance_table_title')}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {t('attendance_table_autosave_hint')}
                                            </p>
                                        </div>
                                        <span className="text-xs text-slate-500">
                                            {t('attendance_unchecked')}: {summary.unchecked}
                                        </span>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-sm">
                                            <thead>
                                                <tr className="border-b border-white/10 text-xs uppercase tracking-wide text-slate-500">
                                                    <th className="px-4 py-2.5 text-start font-semibold">
                                                        {t('staff')}
                                                    </th>
                                                    <th className="px-4 py-2.5 text-start font-semibold">
                                                        {t('status')}
                                                    </th>
                                                    <th className="px-4 py-2.5 text-start font-semibold">
                                                        {t('attendance_late_short')}
                                                    </th>
                                                    <th className="px-4 py-2.5 text-start font-semibold">
                                                        {t('attendance_ot_short')}
                                                    </th>
                                                    <th className="px-4 py-2.5 text-end font-semibold">
                                                        {t('attendance_day_wage')}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {rows.map(({ staff, attendance }) => {
                                                    const current = uiStatus(attendance?.status);
                                                    const busy =
                                                        savingKey &&
                                                        String(savingKey).startsWith(
                                                            `${staff.id}:`,
                                                        );
                                                    const extras = extrasFor(staff.id, attendance);

                                                    return (
                                                        <tr
                                                            key={staff.id}
                                                            className="border-t border-white/5 hover:bg-white/[0.02]"
                                                        >
                                                            <td className="px-4 py-3">
                                                                <div className="flex flex-col gap-1">
                                                                    <Link
                                                                        href={route(
                                                                            'staff.show',
                                                                            staff.id,
                                                                        )}
                                                                        className="font-semibold text-white hover:underline"
                                                                    >
                                                                        {staff.name}
                                                                    </Link>
                                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                                        <PayChip
                                                                            model={staff.pay_model}
                                                                            t={t}
                                                                        />
                                                                        {staff.role ? (
                                                                            <span className="text-[11px] text-slate-500">
                                                                                {staff.role}
                                                                            </span>
                                                                        ) : null}
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {canManage ? (
                                                                    <div className="flex flex-wrap gap-1.5">
                                                                        <StatusToggle
                                                                            active={
                                                                                current ===
                                                                                'present'
                                                                            }
                                                                            label={t(
                                                                                'attendance_badge_present',
                                                                            )}
                                                                            tone="present"
                                                                            disabled={busy}
                                                                            onClick={() =>
                                                                                saveStatus(
                                                                                    staff.id,
                                                                                    'present',
                                                                                    extras,
                                                                                )
                                                                            }
                                                                        />
                                                                        <StatusToggle
                                                                            active={
                                                                                current ===
                                                                                'absent'
                                                                            }
                                                                            label={t(
                                                                                'attendance_badge_absent',
                                                                            )}
                                                                            tone="absent"
                                                                            disabled={busy}
                                                                            onClick={() =>
                                                                                saveStatus(
                                                                                    staff.id,
                                                                                    'absent_unexcused',
                                                                                )
                                                                            }
                                                                        />
                                                                        <StatusToggle
                                                                            active={
                                                                                current === 'leave'
                                                                            }
                                                                            label={t(
                                                                                'attendance_badge_leave',
                                                                            )}
                                                                            tone="leave"
                                                                            disabled={busy}
                                                                            onClick={() =>
                                                                                saveStatus(
                                                                                    staff.id,
                                                                                    'half_day',
                                                                                )
                                                                            }
                                                                        />
                                                                    </div>
                                                                ) : (
                                                                    <span className="text-xs text-slate-400">
                                                                        {current
                                                                            ? t(
                                                                                  `attendance_badge_${current === 'leave' ? 'leave' : current}`,
                                                                              )
                                                                            : '—'}
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {canManage ? (
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        max="1440"
                                                                        className="w-20 rounded-lg border border-white/10 bg-white/[0.04] px-2 py-1.5 font-sans text-sm tabular-nums text-slate-100"
                                                                        value={extras.late_minutes}
                                                                        disabled={
                                                                            busy ||
                                                                            current === 'absent' ||
                                                                            current === 'leave'
                                                                        }
                                                                        onChange={(e) =>
                                                                            queueExtraSave(
                                                                                staff.id,
                                                                                attendance,
                                                                                {
                                                                                    late_minutes:
                                                                                        Number(
                                                                                            e.target
                                                                                                .value ||
                                                                                                0,
                                                                                        ),
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                ) : (
                                                                    <span
                                                                        dir="ltr"
                                                                        className="font-sans tabular-nums text-slate-300"
                                                                    >
                                                                        {attendance?.late_minutes ??
                                                                            0}
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {canManage ? (
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        max="24"
                                                                        step="0.5"
                                                                        className="w-20 rounded-lg border border-white/10 bg-white/[0.04] px-2 py-1.5 font-sans text-sm tabular-nums text-slate-100"
                                                                        value={
                                                                            extras.overtime_hours
                                                                        }
                                                                        disabled={
                                                                            busy ||
                                                                            current === 'absent'
                                                                        }
                                                                        onChange={(e) =>
                                                                            queueExtraSave(
                                                                                staff.id,
                                                                                attendance,
                                                                                {
                                                                                    overtime_hours:
                                                                                        Number(
                                                                                            e.target
                                                                                                .value ||
                                                                                                0,
                                                                                        ),
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                ) : (
                                                                    <span
                                                                        dir="ltr"
                                                                        className="font-sans tabular-nums text-slate-300"
                                                                    >
                                                                        {attendance?.overtime_hours ??
                                                                            0}
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td
                                                                className="px-4 py-3 text-end font-sans tabular-nums text-slate-200"
                                                                dir="ltr"
                                                            >
                                                                <MoneyAmount
                                                                    value={staff.daily_wage || 0}
                                                                    label={staff.currency}
                                                                    size="sm"
                                                                    showLabel={false}
                                                                    className="text-slate-200"
                                                                />{' '}
                                                                <span className="text-[11px] text-slate-500">
                                                                    {staff.currency === 'IQD'
                                                                        ? t('IQD')
                                                                        : t('USD')}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="overflow-hidden rounded-2xl border border-white/10 bg-[#111827]/90">
                            <div className="border-b border-white/10 px-4 py-3">
                                <p className="text-sm font-semibold text-white">
                                    {t('attendance_unit_title')}
                                </p>
                                <p className="text-xs text-slate-500">
                                    {t('attendance_unit_hint')}
                                </p>
                            </div>
                            {units.length === 0 ? (
                                <div className="px-4 py-10 text-center text-sm text-slate-400">
                                    {t('attendance_unit_empty')}
                                </div>
                            ) : (
                                <ul className="divide-y divide-white/5">
                                    {units.map((person) => (
                                        <li
                                            key={person.id}
                                            className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                                        >
                                            <div>
                                                <Link
                                                    href={route('staff.show', person.id)}
                                                    className="font-semibold text-white hover:underline"
                                                >
                                                    {person.name}
                                                </Link>
                                                <div className="mt-1">
                                                    <PayChip model="unit" t={t} />
                                                </div>
                                            </div>
                                            <Link
                                                href={route('vault.lines.job-pay.create', {
                                                    staff_id: person.id,
                                                })}
                                                className="rounded-lg border border-amber-400/30 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold text-amber-100"
                                            >
                                                {t('vault_form_job_pay')}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </div>
            </PageShell>
        </AuthenticatedLayout>
    );
}
