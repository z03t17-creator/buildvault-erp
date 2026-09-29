import DataPanel from '@/Components/DataPanel';
import DataTable, { Td, Th } from '@/Components/DataTable';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

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

export default function Index({ users }) {
    const t = useTranslations();
    const canCreate = useCan('users.create');
    const canUpdate = useCan('users.update');
    const canDisable = useCan('users.disable');
    const canReset = useCan('users.resetPassword');
    const canChangeRole = useCan('users.changeRole');
    const list = users || [];

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
                    subtitle={t('users_subtitle')}
                    actions={
                        canCreate ? (
                            <Link href={route('users.create')}>
                                <PrimaryButton type="button">{t('create_user')}</PrimaryButton>
                            </Link>
                        ) : null
                    }
                />
            }
        >
            <Head title={t('users')} />
            <PageShell>
                {list.length === 0 ? (
                    <EmptyState
                        title={t('no_users')}
                        description={t('no_users_hint')}
                        action={
                            canCreate ? (
                                <Link href={route('users.create')}>
                                    <PrimaryButton type="button">{t('create_user')}</PrimaryButton>
                                </Link>
                            ) : null
                        }
                    />
                ) : (
                    <DataPanel padded={false}>
                        <DataTable minWidth="56rem">
                            <thead>
                                <tr>
                                    <Th>{t('name')}</Th>
                                    <Th>{t('email')}</Th>
                                    <Th>{t('role')}</Th>
                                    <Th>{t('status')}</Th>
                                    <Th>{t('last_login')}</Th>
                                    <Th>{t('created_date')}</Th>
                                    <Th>{t('actions')}</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((user) => (
                                    <tr key={user.id}>
                                        <Td>
                                            <Link
                                                href={route('users.show', user.id)}
                                                className="font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                            >
                                                {user.name}
                                            </Link>
                                        </Td>
                                        <Td muted dir="ltr">
                                            {user.email}
                                        </Td>
                                        <Td>
                                            <StatusBadge status={user.role || '—'} />
                                        </Td>
                                        <Td>
                                            <StatusBadge status={user.status} />
                                        </Td>
                                        <Td muted>{formatWhen(user.last_login_at)}</Td>
                                        <Td muted>{formatWhen(user.created_at)}</Td>
                                        <Td>
                                            <div className="flex flex-wrap gap-2">
                                                <Link href={route('users.show', user.id)}>
                                                    <SecondaryButton type="button">{t('view')}</SecondaryButton>
                                                </Link>
                                                {canUpdate && (
                                                    <Link href={route('users.edit', user.id)}>
                                                        <SecondaryButton type="button">{t('edit')}</SecondaryButton>
                                                    </Link>
                                                )}
                                                {canDisable && user.status === 'active' && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() => disableUser(user.id)}
                                                    >
                                                        {t('disable')}
                                                    </SecondaryButton>
                                                )}
                                                {canDisable && user.status === 'disabled' && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() => enableUser(user.id)}
                                                    >
                                                        {t('enable')}
                                                    </SecondaryButton>
                                                )}
                                                {canReset && (
                                                    <Link href={route('users.edit', user.id) + '#reset-password'}>
                                                        <SecondaryButton type="button">
                                                            {t('reset_password')}
                                                        </SecondaryButton>
                                                    </Link>
                                                )}
                                                {canChangeRole && (
                                                    <Link href={route('users.edit', user.id) + '#change-role'}>
                                                        <SecondaryButton type="button">
                                                            {t('change_role')}
                                                        </SecondaryButton>
                                                    </Link>
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
