import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useCan from '@/hooks/useCan';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

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
                    icon={<NavIcon name="edit" className="text-lg" />}
                    actions={
                        <>
                            <Link href={route('users.show', userRecord.id)}>
                                <SecondaryButton type="button">
                                    {t('view')}
                                </SecondaryButton>
                            </Link>
                            <Link href={route('users.index')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="users" className="text-sm" />
                                    {t('users')}
                                </SecondaryButton>
                            </Link>
                        </>
                    }
                />
            }
        >
            <Head title={t('edit_user')} />
            <PageShell narrow className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-sky-900 dark:bg-sky-400/15 dark:text-sky-200">
                            <NavIcon name="users" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('user_profile')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('users_edit_profile_hint')}
                            </p>
                        </div>
                    </div>
                    <form
                        noValidate
                        onSubmit={(e) => {
                            e.preventDefault();
                            profile.put(route('users.update', userRecord.id));
                        }}
                    >
                        <FormSection cols={2}>
                            <FormField>
                                <InputLabel htmlFor="name" value={t('full_name')} />
                                <TextInput
                                    id="name"
                                    className={fieldClass}
                                    value={profile.data.name}
                                    onChange={(e) =>
                                        profile.setData('name', e.target.value)
                                    }
                                />
                                <InputError
                                    message={profile.errors.name}
                                    className="mt-1"
                                />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="email" value={t('email')} />
                                <TextInput
                                    id="email"
                                    type="email"
                                    className={fieldClass}
                                    value={profile.data.email}
                                    onChange={(e) =>
                                        profile.setData('email', e.target.value)
                                    }
                                    dir="ltr"
                                />
                                <InputError
                                    message={profile.errors.email}
                                    className="mt-1"
                                />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="role" value={t('role')} />
                                <select
                                    id="role"
                                    className={fieldClass}
                                    value={profile.data.role}
                                    onChange={(e) =>
                                        profile.setData('role', e.target.value)
                                    }
                                >
                                    {(roles || []).map((r) => (
                                        <option key={r} value={r}>
                                            {r}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={profile.errors.role}
                                    className="mt-1"
                                />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="status" value={t('status')} />
                                <select
                                    id="status"
                                    className={fieldClass}
                                    value={profile.data.status}
                                    onChange={(e) =>
                                        profile.setData('status', e.target.value)
                                    }
                                >
                                    {(statuses || ['active', 'disabled']).map((s) => (
                                        <option key={s} value={s}>
                                            {t(`status_${s}`) !== `status_${s}`
                                                ? t(`status_${s}`)
                                                : s}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={profile.errors.status}
                                    className="mt-1"
                                />
                            </FormField>
                            <FormField className="sm:col-span-2">
                                <InputLabel htmlFor="phone" value={t('phone')} />
                                <TextInput
                                    id="phone"
                                    className={fieldClass}
                                    value={profile.data.phone}
                                    onChange={(e) =>
                                        profile.setData('phone', e.target.value)
                                    }
                                    dir="ltr"
                                />
                                <InputError
                                    message={profile.errors.phone}
                                    className="mt-1"
                                />
                            </FormField>
                        </FormSection>
                        <FormActions className="mt-4">
                            <PrimaryButton
                                disabled={profile.processing}
                                className="!bg-sky-600 hover:!bg-sky-500 dark:!bg-sky-400 dark:!text-sky-950"
                            >
                                {t('save')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </section>

                {canReset && (
                    <section id="reset-password" className="bv-card p-4 sm:p-5">
                        <div className="mb-4 flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-900 dark:bg-amber-400/15 dark:text-amber-200">
                                <NavIcon name="insurance" className="text-base" />
                            </span>
                            <div>
                                <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('reset_password')}
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {t('admin_reset_hint')}
                                </p>
                            </div>
                        </div>
                        <form
                            noValidate
                            onSubmit={(e) => {
                                e.preventDefault();
                                passwordForm.post(
                                    route('users.reset-password', userRecord.id),
                                    {
                                        onSuccess: () =>
                                            passwordForm.reset(
                                                'password',
                                                'password_confirmation',
                                            ),
                                    },
                                );
                            }}
                        >
                            <FormSection cols={2}>
                                <FormField>
                                    <InputLabel
                                        htmlFor="new_password"
                                        value={t('password')}
                                    />
                                    <TextInput
                                        id="new_password"
                                        type="password"
                                        className={fieldClass}
                                        value={passwordForm.data.password}
                                        onChange={(e) =>
                                            passwordForm.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                    <InputError
                                        message={passwordForm.errors.password}
                                        className="mt-1"
                                    />
                                </FormField>
                                <FormField>
                                    <InputLabel
                                        htmlFor="new_password_confirmation"
                                        value={t('confirm_password')}
                                    />
                                    <TextInput
                                        id="new_password_confirmation"
                                        type="password"
                                        className={fieldClass}
                                        value={
                                            passwordForm.data.password_confirmation
                                        }
                                        onChange={(e) =>
                                            passwordForm.setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </FormField>
                            </FormSection>
                            <FormActions className="mt-4">
                                <PrimaryButton
                                    disabled={passwordForm.processing}
                                    className="!bg-amber-600 hover:!bg-amber-500"
                                >
                                    {t('reset_password')}
                                </PrimaryButton>
                            </FormActions>
                        </form>
                    </section>
                )}

                {canChangeRole && (
                    <section id="change-role" className="bv-card p-4 sm:p-5">
                        <div className="mb-4 flex items-start gap-3">
                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-sky-900 dark:bg-sky-400/15 dark:text-sky-200">
                                <NavIcon name="audit" className="text-base" />
                            </span>
                            <div>
                                <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                    {t('change_role')}
                                </h2>
                                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {t('users_change_role_hint')}
                                </p>
                            </div>
                        </div>
                        <form
                            noValidate
                            onSubmit={(e) => {
                                e.preventDefault();
                                roleForm.post(
                                    route('users.change-role', userRecord.id),
                                );
                            }}
                        >
                            <FormSection cols={2}>
                                <FormField>
                                    <InputLabel
                                        htmlFor="change_role"
                                        value={t('role')}
                                    />
                                    <select
                                        id="change_role"
                                        className={fieldClass}
                                        value={roleForm.data.role}
                                        onChange={(e) =>
                                            roleForm.setData('role', e.target.value)
                                        }
                                    >
                                        {(roles || []).map((r) => (
                                            <option key={r} value={r}>
                                                {r}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={roleForm.errors.role}
                                        className="mt-1"
                                    />
                                </FormField>
                            </FormSection>
                            <FormActions className="mt-4">
                                <PrimaryButton
                                    disabled={roleForm.processing}
                                    className="!bg-sky-600 hover:!bg-sky-500"
                                >
                                    {t('change_role')}
                                </PrimaryButton>
                            </FormActions>
                        </form>
                    </section>
                )}
            </PageShell>
        </AuthenticatedLayout>
    );
}
