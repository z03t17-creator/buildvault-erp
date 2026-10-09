import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1.5 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function ResetPassword({ token, email }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('reset_password_title')} />

            <div className="mb-6">
                <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-600 text-white dark:bg-teal-400 dark:text-teal-950">
                    <NavIcon name="lock" className="text-lg" />
                </div>
                <h1 className="font-display text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    {t('reset_password_title')}
                </h1>
                <p className="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {t('reset_password_hint')}
                </p>
            </div>

            <form noValidate onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value={t('email')} />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className={fieldClass}
                        autoComplete="username"
                        dir="ltr"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value={t('password')} />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className={fieldClass}
                        autoComplete="new-password"
                        isFocused
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value={t('confirm_password')}
                    />
                    <TextInput
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className={fieldClass}
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <PrimaryButton
                    className="w-full !bg-teal-700 hover:!bg-teal-600 dark:!bg-teal-400 dark:!text-teal-950"
                    disabled={processing}
                >
                    <NavIcon name="lock" solid className="text-sm" />
                    {t('reset_password_submit')}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
