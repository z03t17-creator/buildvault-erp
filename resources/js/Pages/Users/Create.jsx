import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

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
                    subtitle={t('users_subtitle')}
                    actions={
                        <Link href={route('users.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('create_user')} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('users.store'));
                    }}
                    className="mx-auto max-w-2xl space-y-5 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="name" value={t('full_name')} />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="email" value={t('email')} />
                            <TextInput
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                            />
                            <InputError message={errors.email} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password" value={t('password')} />
                            <TextInput
                                id="password"
                                type="password"
                                className="mt-1 block w-full"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                required
                            />
                            <InputError message={errors.password} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password_confirmation" value={t('confirm_password')} />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                className="mt-1 block w-full"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                required
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="role" value={t('role')} />
                            <select
                                id="role"
                                className={selectClass}
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
                        </div>
                        <div>
                            <InputLabel htmlFor="status" value={t('status')} />
                            <select
                                id="status"
                                className={selectClass}
                                value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                            >
                                {(statuses || ['active', 'disabled']).map((s) => (
                                    <option key={s} value={s}>
                                        {t(`status_${s}`) !== `status_${s}` ? t(`status_${s}`) : s}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.status} className="mt-1" />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="phone" value={t('phone')} />
                            <TextInput
                                id="phone"
                                className="mt-1 block w-full"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                            />
                            <InputError message={errors.phone} className="mt-1" />
                        </div>
                    </div>
                    <PrimaryButton disabled={processing}>{t('save')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
