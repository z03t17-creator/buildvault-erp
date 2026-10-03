import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ roles, statuses }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles?.[0] || 'Accountant',
        phone: '',
        status: 'active',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('create_user')}
                    subtitle={t('users_create_hint')}
                    icon={<NavIcon name="users" className="text-lg" />}
                    actions={
                        <Link href={route('users.index')}>
                            <SecondaryButton type="button">
                                <NavIcon name="users" className="text-sm" />
                                {t('users')}
                            </SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('create_user')} />
            <PageShell narrow className="!space-y-6">
                <section className="bv-card p-4 sm:p-5">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-sky-900 dark:bg-sky-400/15 dark:text-sky-200">
                            <NavIcon name="edit" className="text-base" />
                        </span>
                        <div>
                            <h2 className="font-display text-base font-semibold text-slate-900 dark:text-white">
                                {t('users_create_title')}
                            </h2>
                            <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {t('users_create_card_hint')}
                            </p>
                        </div>
                    </div>
                    <form
                        noValidate
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('users.store'));
                        }}
                    >
                        <FormSection cols={2}>
                            <FormField>
                                <InputLabel htmlFor="name" value={t('full_name')} />
                                <TextInput
                                    id="name"
                                    className={fieldClass}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    autoComplete="name"
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="email" value={t('email')} />
                                <TextInput
                                    id="email"
                                    type="email"
                                    className={fieldClass}
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    autoComplete="email"
                                    dir="ltr"
                                />
                                <InputError message={errors.email} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="password" value={t('password')} />
                                <TextInput
                                    id="password"
                                    type="password"
                                    className={fieldClass}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel
                                    htmlFor="password_confirmation"
                                    value={t('confirm_password')}
                                />
                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    className={fieldClass}
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData('password_confirmation', e.target.value)
                                    }
                                    autoComplete="new-password"
                                />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="role" value={t('role')} />
                                <select
                                    id="role"
                                    className={fieldClass}
                                    value={data.role}
                                    onChange={(e) => setData('role', e.target.value)}
                                >
                                    {(roles || []).map((r) => (
                                        <option key={r} value={r}>
                                            {r}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.role} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="status" value={t('status')} />
                                <select
                                    id="status"
                                    className={fieldClass}
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                >
                                    {(statuses || ['active', 'disabled']).map((s) => (
                                        <option key={s} value={s}>
                                            {t(`status_${s}`) !== `status_${s}`
                                                ? t(`status_${s}`)
                                                : s}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.status} className="mt-1" />
                            </FormField>
                            <FormField className="sm:col-span-2">
                                <InputLabel htmlFor="phone" value={t('phone')} />
                                <TextInput
                                    id="phone"
                                    className={fieldClass}
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    dir="ltr"
                                />
                                <InputError message={errors.phone} className="mt-1" />
                            </FormField>
                        </FormSection>
                        <FormActions className="mt-4">
                            <PrimaryButton
                                disabled={processing}
                                className="!bg-sky-600 hover:!bg-sky-500 dark:!bg-sky-400 dark:!text-sky-950"
                            >
                                <NavIcon name="users" className="text-sm" />
                                {t('create_user')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </section>
            </PageShell>
        </AuthenticatedLayout>
    );
}
