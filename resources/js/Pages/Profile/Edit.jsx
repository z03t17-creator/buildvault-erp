import DataPanel from '@/Components/DataPanel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    const t = useTranslations();
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('profile')}
                    subtitle={t('profile_subtitle')}
                />
            }
        >
            <Head title={t('profile')} />

            <PageShell narrow>
                <DataPanel>
                    <div className="flex items-center gap-4">
                        <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-emerald-600/10 text-2xl font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                            {(user.name || '?').charAt(0).toUpperCase()}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-display text-xl font-semibold text-slate-900 dark:text-white" dir="auto">
                                {user.name}
                            </p>
                            <p className="truncate text-sm text-slate-500" dir="ltr">
                                {user.email}
                            </p>
                        </div>
                    </div>
                </DataPanel>

                <DataPanel title={t('profile_information')}>
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                    />
                </DataPanel>

                <DataPanel title={t('update_password')}>
                    <UpdatePasswordForm className="max-w-xl" />
                </DataPanel>

                <DataPanel title={t('delete_account')}>
                    <DeleteUserForm className="max-w-xl" />
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
