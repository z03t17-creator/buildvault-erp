import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
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
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
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
                        <div className="bv-surface">
                            <div className="bv-table-wrap">
                                <table className="bv-table min-w-[56rem]">
                                    <thead className="border-b border-slate-200 bg-slate-50/80 text-start text-xs uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400">
                                        <tr>
                                            <th className="px-4 py-3 font-semibold">{t('name')}</th>
                                            <th className="px-4 py-3 font-semibold">{t('email')}</th>
                                            <th className="px-4 py-3 font-semibold">{t('role')}</th>
                                            <th className="px-4 py-3 font-semibold">{t('status')}</th>
                                            <th className="px-4 py-3 font-semibold">{t('last_login')}</th>
                                            <th className="px-4 py-3 font-semibold">{t('created_date')}</th>
                                            <th className="px-4 py-3 font-semibold">{t('actions')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {list.map((user) => (
                                            <tr key={user.id}>
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={route('users.show', user.id)}
                                                        className="font-medium text-emerald-800 underline-offset-2 hover:underline dark:text-emerald-300"
                                                    >
                                                        {user.name}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 dark:text-slate-300" dir="ltr">
                                                    {user.email}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={user.role || '—'} />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={user.status} />
                                                </td>
                                                <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                                    {formatWhen(user.last_login_at)}
                                                </td>
                                                <td className="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                                    {formatWhen(user.created_at)}
                                                </td>
                                                <td className="px-4 py-3">
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
                                                            <SecondaryButton type="button" onClick={() => disableUser(user.id)}>
                                                                {t('disable')}
                                                            </SecondaryButton>
                                                        )}
                                                        {canDisable && user.status === 'disabled' && (
                                                            <SecondaryButton type="button" onClick={() => enableUser(user.id)}>
                                                                {t('enable')}
                                                            </SecondaryButton>
                                                        )}
                                                        {canReset && (
                                                            <Link href={route('users.edit', user.id) + '#reset-password'}>
                                                                <SecondaryButton type="button">{t('reset_password')}</SecondaryButton>
                                                            </Link>
                                                        )}
                                                        {canChangeRole && (
                                                            <Link href={route('users.edit', user.id) + '#change-role'}>
                                                                <SecondaryButton type="button">{t('change_role')}</SecondaryButton>
                                                            </Link>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
