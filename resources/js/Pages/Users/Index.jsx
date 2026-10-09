import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router } from '@inertiajs/react';

function CountStat({ label, value, hint, active = false }) {
    return (
        <div
            className={
                'bv-card px-4 py-3.5 ' +
                (active ? 'ring-2 ring-sky-500/40 dark:ring-sky-400/40' : '')
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

function RoleChip({ role, t }) {
    const key = `role_label_${roleKey(role)}`;
    const label = t(key) !== key ? t(key) : role || '—';
    const tones = {
        'super-admin': 'bg-sky-500/15 text-sky-950 dark:text-sky-200',
        'boss-contractor': 'bg-teal-500/15 text-teal-900 dark:text-teal-200',
        accountant: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        'stock-manager': 'bg-slate-500/15 text-slate-800 dark:text-slate-200',
    };
    const tone = tones[roleKey(role)] || tones['stock-manager'];

    return (
        <span
            className={
                'inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold ' +
                tone
            }
        >
            <NavIcon name="users" className="text-xs" />
            {label}
        </span>
    );
}

function StatusChip({ status, t }) {
    const key = `status_${status}`;
    const label = t(key) !== key ? t(key) : status || '—';
    const tones = {
        active: 'bg-emerald-500/15 text-emerald-900 dark:text-emerald-300',
        disabled: 'bg-rose-500/15 text-rose-900 dark:text-rose-300',
    };

    return (
        <span
            className={
                'inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold capitalize ' +
                (tones[status] || tones.active)
            }
        >
            {label}
        </span>
    );
}

function roleKey(role) {
    return String(role || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

function formatWhen(value) {
    if (!value) {
        return '—';
    }
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

function roleLabel(role, t) {
    const key = `role_label_${roleKey(role)}`;
    return t(key) !== key ? t(key) : role;
}

export default function Index({
    users,
    filters,
    roles,
    statuses,
    roleCounts,
    statusCounts,
    overview,
}) {
    const t = useTranslations();
    const canCreate = useCan('users.create');
    const canUpdate = useCan('users.update');
    const canDisable = useCan('users.disable');
    const list = users || [];
    const activeRole = filters?.role || '';
    const activeStatus = filters?.status || '';
    const rCounts = roleCounts || { all: list.length };
    const sCounts = statusCounts || {
        all: list.length,
        active: 0,
        disabled: 0,
    };
    const totals = overview || {
        count: list.length,
        active: 0,
        disabled: 0,
    };

    const apply = (next = {}) => {
        const params = {};
        const role = next.role !== undefined ? next.role : activeRole;
        const status = next.status !== undefined ? next.status : activeStatus;
        if (role) params.role = role;
        if (status) params.status = status;
        router.get(route('users.index'), params, {
            preserveState: true,
            replace: true,
        });
    };

    const statusChips = [
        { key: '', label: t('users_filter_all'), count: sCounts.all },
        ...(statuses || ['active', 'disabled']).map((s) => ({
            key: s,
            label: t(`status_${s}`) !== `status_${s}` ? t(`status_${s}`) : s,
            count: sCounts[s] ?? 0,
        })),
    ];

    const roleChips = [
        { key: '', label: t('users_filter_all_roles'), count: rCounts.all },
        ...(roles || []).map((r) => ({
            key: r,
            label: roleLabel(r, t),
            count: rCounts[r] ?? 0,
        })),
    ];

    const disableUser = (id) => {
        if (!window.confirm(t('confirm_disable_user'))) {
            return;
        }
        router.post(route('users.disable', id));
    };

    const enableUser = (id) => {
        router.post(route('users.enable', id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('users')}
                    subtitle={t('users_page_hint')}
                    icon={<NavIcon name="users" className="text-lg" />}
                    actions={
                        canCreate ? (
                            <Link href={route('users.create')}>
                                <PrimaryButton
                                    type="button"
                                    className="!bg-sky-600 hover:!bg-sky-500 dark:!bg-sky-400 dark:!text-sky-950"
                                >
                                    <NavIcon name="users" className="text-sm" />
                                    {t('create_user')}
                                </PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('users')} />
            <PageShell className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-3 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-sky-900 dark:bg-sky-400/15 dark:text-sky-200">
                            <NavIcon name="filter" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('users_filters_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('users_filters_hint')}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {statusChips.map((chip) => {
                            const active = activeStatus === chip.key;
                            return (
                                <button
                                    key={`status-${chip.key || 'all'}`}
                                    type="button"
                                    onClick={() => apply({ status: chip.key })}
                                    className={
                                        'inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition ' +
                                        (active
                                            ? 'bg-sky-600 text-white dark:bg-sky-400 dark:text-sky-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-sky-50 hover:text-sky-900 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-sky-950/40 dark:hover:text-sky-100')
                                    }
                                >
                                    {chip.label}
                                    <span
                                        className={
                                            'rounded-md px-1.5 py-0.5 text-xs tabular-nums ' +
                                            (active
                                                ? 'bg-white/20 text-white dark:bg-sky-950/20 dark:text-sky-950'
                                                : 'bg-white text-slate-500 dark:bg-slate-900 dark:text-slate-400')
                                        }
                                    >
                                        {chip.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {roleChips.map((chip) => {
                            const active = activeRole === chip.key;
                            return (
                                <button
                                    key={`role-${chip.key || 'all'}`}
                                    type="button"
                                    onClick={() => apply({ role: chip.key })}
                                    className={
                                        'inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition ' +
                                        (active
                                            ? 'bg-sky-700 text-white dark:bg-sky-300 dark:text-sky-950'
                                            : 'bg-slate-100 text-slate-700 hover:bg-sky-50 hover:text-sky-900 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-sky-950/40 dark:hover:text-sky-100')
                                    }
                                >
                                    {chip.label}
                                    <span
                                        className={
                                            'rounded-md px-1.5 py-0.5 text-xs tabular-nums ' +
                                            (active
                                                ? 'bg-white/20 text-white dark:bg-sky-950/20 dark:text-sky-950'
                                                : 'bg-white text-slate-500 dark:bg-slate-900 dark:text-slate-400')
                                        }
                                    >
                                        {chip.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section>
                    <div className="mb-3">
                        <h2 className="font-display text-lg font-semibold text-slate-900 dark:text-white">
                            {t('users_overview')}
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {t('users_overview_hint', { count: totals.count })}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                        <CountStat
                            label={t('users_stat_shown')}
                            value={totals.count}
                            hint={t('users_stat_shown_hint')}
                            active
                        />
                        <CountStat
                            label={t('status_active')}
                            value={totals.active}
                            hint={t('users_stat_active_hint')}
                        />
                        <CountStat
                            label={t('status_disabled')}
                            value={totals.disabled}
                            hint={t('users_stat_disabled_hint')}
                        />
                    </div>
                </section>

                {list.length === 0 ? (
                    <EmptyState
                        icon="users"
                        title={t('no_users')}
                        description={t('no_users_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('users.create')}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-sky-600 hover:!bg-sky-500"
                                    >
                                        <NavIcon name="users" className="text-sm" />
                                        {t('create_user')}
                                    </PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <div className="flex items-center gap-3 border-b border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-sky-500/15 text-sky-900 dark:bg-sky-400/15 dark:text-sky-200">
                                <NavIcon name="users" className="text-base" />
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {t('users_table_title')}
                                </p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {t('users_table_hint')}
                                </p>
                            </div>
                        </div>
                        <DataTable minWidth="52rem" caption={t('users')}>
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('email')}</Th>
                                    <Th>{t('role')}</Th>
                                    <Th>{t('status')}</Th>
                                    <Th>{t('last_login')}</Th>
                                    <Th>{t('actions')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((user) => (
                                    <tr key={user.id}>
                                        <Td>
                                            <Link
                                                href={route('users.show', user.id)}
                                                className="inline-flex items-center gap-2 font-medium text-sky-950 underline-offset-2 hover:underline dark:text-sky-100"
                                            >
                                                <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-500/15 text-sky-900 dark:bg-sky-400/15 dark:text-sky-200">
                                                    <NavIcon
                                                        name="users"
                                                        className="text-xs"
                                                    />
                                                </span>
                                                {user.name}
                                            </Link>
                                        </Td>
                                        <Td muted dir="ltr">
                                            {user.email}
                                        </Td>
                                        <Td>
                                            <RoleChip role={user.role} t={t} />
                                        </Td>
                                        <Td>
                                            <StatusChip status={user.status} t={t} />
                                        </Td>
                                        <Td muted className="font-sans tabular-nums text-xs">
                                            {formatWhen(user.last_login_at)}
                                        </Td>
                                        <Td>
                                            <div className="flex flex-wrap gap-2">
                                                <Link href={route('users.show', user.id)}>
                                                    <SecondaryButton type="button">
                                                        {t('view')}
                                                    </SecondaryButton>
                                                </Link>
                                                {canUpdate && (
                                                    <Link
                                                        href={route('users.edit', user.id)}
                                                    >
                                                        <SecondaryButton type="button">
                                                            <NavIcon
                                                                name="edit"
                                                                className="text-sm"
                                                            />
                                                            {t('edit')}
                                                        </SecondaryButton>
                                                    </Link>
                                                )}
                                                {canDisable &&
                                                    user.status === 'active' && (
                                                        <SecondaryButton
                                                            type="button"
                                                            onClick={() =>
                                                                disableUser(user.id)
                                                            }
                                                        >
                                                            {t('disable')}
                                                        </SecondaryButton>
                                                    )}
                                                {canDisable &&
                                                    user.status === 'disabled' && (
                                                        <PrimaryButton
                                                            type="button"
                                                            className="!bg-sky-600 hover:!bg-sky-500"
                                                            onClick={() =>
                                                                enableUser(user.id)
                                                            }
                                                        >
                                                            {t('enable')}
                                                        </PrimaryButton>
                                                    )}
                                            </div>
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
