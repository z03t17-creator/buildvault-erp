import Checkbox from '@/Components/Checkbox';
import FlashBanner from '@/Components/FlashBanner';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1.5 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function Login({ status, canResetPassword }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('login')} />

            <div className="mb-6">
                <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-600 text-white dark:bg-teal-400 dark:text-teal-950">
                    <NavIcon name="lock" className="text-lg" />
                </div>
                <h1 className="font-display text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    {t('login')}
                </h1>
                <p className="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {t('login_page_hint')}
                </p>
            </div>

            {status ? (
                <FlashBanner tone="success" className="mb-5">
                    {status}
                </FlashBanner>
            ) : null}

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
                        isFocused
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
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span>{t('remember_me')}</span>
                    </label>
                    {canResetPassword ? (
                        <Link
                            href={route('password.request')}
                            className="text-sm font-semibold text-teal-800 underline-offset-2 hover:underline dark:text-teal-300"
                        >
                            {t('forgot_password')}
                        </Link>
                    ) : null}
                </div>

                <PrimaryButton
                    className="w-full !bg-teal-700 hover:!bg-teal-600 dark:!bg-teal-400 dark:!text-teal-950 dark:hover:!bg-teal-300"
                    disabled={processing}
                >
                    <NavIcon name="lock" solid className="text-sm" />
                    {t('login')}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
