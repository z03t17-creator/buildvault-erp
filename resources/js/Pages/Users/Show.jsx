import DataPanel from '@/Components/DataPanel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router, usePage } from '@inertiajs/react';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-100">{children}</dd>
        </div>
    );
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
                    subtitle={userRecord.email}
                    actions={
                        <>
                            <Link href={route('users.index')}>
                                <SecondaryButton>{t('back')}</SecondaryButton>
                            </Link>
                            {canUpdate && (
                                <Link href={route('users.edit', userRecord.id)}>
                                    <SecondaryButton>{t('edit')}</SecondaryButton>
                                </Link>
                            )}
                            {canDisable && userRecord.status === 'active' && currentUserId !== userRecord.id && (
                                <SecondaryButton type="button" onClick={disableUser}>
                                    {t('disable')}
                                </SecondaryButton>
                            )}
                            {canDisable && userRecord.status === 'disabled' && (
                                <PrimaryButton type="button" onClick={() => router.post(route('users.enable', userRecord.id))}>
                                    {t('enable')}
                                </PrimaryButton>
                            )}
                            {canAudit && (
                                <Link href={route('audit.index', { subject_user_id: userRecord.id })}>
                                    <SecondaryButton type="button">{t('view_activity')}</SecondaryButton>
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title={userRecord.name} />
            <PageShell narrow>
                <DataPanel>
                    <div className="mb-6 flex flex-wrap items-center gap-3">
                        <StatusBadge status={userRecord.role || '—'} />
                        <StatusBadge status={userRecord.status} />
                    </div>
                    <dl className="grid gap-6 sm:grid-cols-2">
                        <Field label={t('full_name')}>{userRecord.name}</Field>
                        <Field label={t('email')}>
                            <span dir="ltr">{userRecord.email}</span>
                        </Field>
                        <Field label={t('phone')}>{userRecord.phone || '—'}</Field>
                        <Field label={t('role')}>{userRecord.role || '—'}</Field>
                        <Field label={t('status')}>{t(`status_${userRecord.status}`) || userRecord.status}</Field>
                        <Field label={t('last_login')}>{formatWhen(userRecord.last_login_at)}</Field>
                        <Field label={t('created_date')}>{formatWhen(userRecord.created_at)}</Field>
                        <Field label={t('updated_at')}>{formatWhen(userRecord.updated_at)}</Field>
                    </dl>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
