import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Edit({ userRecord, roles, statuses }) {
    const t = useTranslations();
    const canReset = useCan('users.resetPassword');
    const canChangeRole = useCan('users.changeRole');

    const profile = useForm({
        name: userRecord.name || '',
        email: userRecord.email || '',
        phone: userRecord.phone || '',
        role: userRecord.role || roles?.[0] || '',
        status: userRecord.status || 'active',
    });

    const passwordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    const roleForm = useForm({
        role: userRecord.role || roles?.[0] || '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('edit_user')}
                    subtitle={userRecord.email}
                    actions={
                        <Link href={route('users.show', userRecord.id)}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('edit_user')} />
            <div className="py-8">
                <div className="mx-auto flex max-w-2xl flex-col gap-6 px-4 sm:px-0">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            profile.put(route('users.update', userRecord.id));
                        }}
                        className="space-y-5 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                    >
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
                            {t('user_profile')}
                        </h2>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="name" value={t('full_name')} />
                                <TextInput
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={profile.data.name}
                                    onChange={(e) => profile.setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={profile.errors.name} className="mt-1" />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="email" value={t('email')} />
                                <TextInput
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    value={profile.data.email}
                                    onChange={(e) => profile.setData('email', e.target.value)}
                                    required
                                />
                                <InputError message={profile.errors.email} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="role" value={t('role')} />
                                <select
                                    id="role"
                                    className={selectClass}
                                    value={profile.data.role}
                                    onChange={(e) => profile.setData('role', e.target.value)}
                                >
                                    {(roles || []).map((r) => (
                                        <option key={r} value={r}>
                                            {r}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={profile.errors.role} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="status" value={t('status')} />
                                <select
                                    id="status"
                                    className={selectClass}
                                    value={profile.data.status}
                                    onChange={(e) => profile.setData('status', e.target.value)}
                                >
                                    {(statuses || ['active', 'disabled']).map((s) => (
                                        <option key={s} value={s}>
                                            {t(`status_${s}`) !== `status_${s}` ? t(`status_${s}`) : s}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={profile.errors.status} className="mt-1" />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="phone" value={t('phone')} />
                                <TextInput
                                    id="phone"
                                    className="mt-1 block w-full"
                                    value={profile.data.phone}
                                    onChange={(e) => profile.setData('phone', e.target.value)}
                                />
                                <InputError message={profile.errors.phone} className="mt-1" />
                            </div>
                        </div>
                        <PrimaryButton disabled={profile.processing}>{t('save')}</PrimaryButton>
                    </form>

                    {canReset && (
                        <form
                            id="reset-password"
                            onSubmit={(e) => {
                                e.preventDefault();
                                passwordForm.post(route('users.reset-password', userRecord.id), {
                                    onSuccess: () => passwordForm.reset('password', 'password_confirmation'),
                                });
                            }}
                            className="space-y-5 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                        >
                            <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
                                {t('reset_password')}
                            </h2>
                            <p className="text-sm text-slate-600 dark:text-slate-300">{t('admin_reset_hint')}</p>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="new_password" value={t('password')} />
                                    <TextInput
                                        id="new_password"
                                        type="password"
                                        className="mt-1 block w-full"
                                        value={passwordForm.data.password}
                                        onChange={(e) => passwordForm.setData('password', e.target.value)}
                                        required
                                    />
                                    <InputError message={passwordForm.errors.password} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="new_password_confirmation" value={t('confirm_password')} />
                                    <TextInput
                                        id="new_password_confirmation"
                                        type="password"
                                        className="mt-1 block w-full"
                                        value={passwordForm.data.password_confirmation}
                                        onChange={(e) =>
                                            passwordForm.setData('password_confirmation', e.target.value)
                                        }
                                        required
                                    />
                                </div>
                            </div>
                            <PrimaryButton disabled={passwordForm.processing}>{t('reset_password')}</PrimaryButton>
                        </form>
                    )}

                    {canChangeRole && (
                        <form
                            id="change-role"
                            onSubmit={(e) => {
                                e.preventDefault();
                                roleForm.post(route('users.change-role', userRecord.id));
                            }}
                            className="space-y-5 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                        >
                            <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
                                {t('change_role')}
                            </h2>
                            <div>
                                <InputLabel htmlFor="change_role" value={t('role')} />
                                <select
                                    id="change_role"
                                    className={selectClass}
                                    value={roleForm.data.role}
                                    onChange={(e) => roleForm.setData('role', e.target.value)}
                                >
                                    {(roles || []).map((r) => (
                                        <option key={r} value={r}>
                                            {r}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={roleForm.errors.role} className="mt-1" />
                            </div>
                            <PrimaryButton disabled={roleForm.processing}>{t('change_role')}</PrimaryButton>
                        </form>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
