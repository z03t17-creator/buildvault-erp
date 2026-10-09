import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, router, usePage } from '@inertiajs/react';

function Meta({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {label}
            </dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                {children}
            </dd>
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

    return (
        <span
            className={
                'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold ' +
                (tones[roleKey(role)] || tones['stock-manager'])
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
                'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold capitalize ' +
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

export default function Show({ userRecord }) {
    const t = useTranslations();
    const page = usePage();
    const canUpdate = useCan('users.update');
    const canDisable = useCan('users.disable');
    const canAudit = useCan('vault.audit');
    const currentUserId = page.props.auth?.user?.id;
    const isDisabled = userRecord.status === 'disabled';

    const disableUser = () => {
        if (!window.confirm(t('confirm_disable_user'))) {
            return;
        }
        router.post(route('users.disable', userRecord.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={userRecord.name}
                    subtitle={t('users_show_hint')}
                    icon={<NavIcon name="users" className="text-lg" />}
                    actions={
                        <>
                            <Link href={route('users.index')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="users" className="text-sm" />
                                    {t('users')}
                                </SecondaryButton>
                            </Link>
                            {canUpdate && (
                                <Link href={route('users.edit', userRecord.id)}>
                                    <PrimaryButton
                                        type="button"
                                        className="!bg-sky-600 hover:!bg-sky-500 dark:!bg-sky-400 dark:!text-sky-950"
                                    >
                                        <NavIcon name="edit" className="text-sm" />
                                        {t('edit')}
                                    </PrimaryButton>
                                </Link>
                            )}
                            {canDisable &&
                                userRecord.status === 'active' &&
                                currentUserId !== userRecord.id && (
                                    <SecondaryButton type="button" onClick={disableUser}>
                                        {t('disable')}
                                    </SecondaryButton>
                                )}
                            {canDisable && isDisabled && (
                                <PrimaryButton
                                    type="button"
                                    className="!bg-sky-600 hover:!bg-sky-500"
                                    onClick={() =>
                                        router.post(route('users.enable', userRecord.id))
                                    }
                                >
                                    {t('enable')}
                                </PrimaryButton>
                            )}
                            {canAudit && (
                                <Link
                                    href={route('audit.index', {
                                        subject_user_id: userRecord.id,
                                    })}
                                >
                                    <SecondaryButton type="button">
                                        <NavIcon name="audit" className="text-sm" />
                                        {t('view_activity')}
                                    </SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={userRecord.name} />
            <PageShell narrow className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="flex flex-wrap items-start gap-3">
                        <span className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-600 text-white dark:bg-sky-400 dark:text-sky-950">
                            <NavIcon name="users" className="text-lg" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <RoleChip role={userRecord.role} t={t} />
                                <StatusChip status={userRecord.status} t={t} />
                            </div>
                            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                {t('users_show_card_hint')}
                            </p>
                        </div>
                    </div>

                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        <Meta label={t('full_name')}>{userRecord.name}</Meta>
                        <Meta label={t('email')}>
                            <span dir="ltr">{userRecord.email}</span>
                        </Meta>
                        <Meta label={t('phone')}>{userRecord.phone || '—'}</Meta>
                        <Meta label={t('role')}>{userRecord.role || '—'}</Meta>
                        <Meta label={t('status')}>
                            {t(`status_${userRecord.status}`) !==
                            `status_${userRecord.status}`
                                ? t(`status_${userRecord.status}`)
                                : userRecord.status}
                        </Meta>
                        <Meta label={t('last_login')}>
                            {formatWhen(userRecord.last_login_at)}
                        </Meta>
                        <Meta label={t('created_date')}>
                            {formatWhen(userRecord.created_at)}
                        </Meta>
                        <Meta label={t('updated_at')}>
                            {formatWhen(userRecord.updated_at)}
                        </Meta>
                    </dl>
                </section>

                {canUpdate && (
                    <section className="flex flex-wrap gap-2">
                        <Link href={route('users.edit', userRecord.id) + '#reset-password'}>
                            <SecondaryButton type="button">
                                <NavIcon name="insurance" className="text-sm" />
                                {t('reset_password')}
                            </SecondaryButton>
                        </Link>
                        <Link href={route('users.edit', userRecord.id) + '#change-role'}>
                            <SecondaryButton type="button">
                                <NavIcon name="audit" className="text-sm" />
                                {t('change_role')}
                            </SecondaryButton>
                        </Link>
                    </section>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
