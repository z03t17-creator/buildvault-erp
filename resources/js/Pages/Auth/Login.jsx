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

const inputClass =
    'mt-1.5 block w-full min-h-touch rounded-xl border-slate-300 focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

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

            <div className="mb-7">
                <div className="bv-icon-chip mb-3">
                    <NavIcon name="lock" className="text-lg" />
                </div>
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700 dark:text-teal-300">
                    BuildVault
                </p>
                <h1 className="mt-1.5 font-display text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    {t('login')}
                </h1>
                <p className="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    ZHAKO construction ERP · USD & IQD vault
                </p>
            </div>

            {status && (
                <FlashBanner tone="success" className="mb-5">
                    {status}
                </FlashBanner>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value={t('email')} />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className={inputClass}
                        autoComplete="username"
                        isFocused={true}
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
                        className={inputClass}
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-between gap-3">
                    <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        <span>{t('remember_me')}</span>
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm font-medium text-teal-700 underline-offset-2 hover:underline dark:text-teal-300"
                        >
                            {t('forgot_password')}
                        </Link>
                    )}
                </div>

                <PrimaryButton className="w-full" disabled={processing}>
                    <NavIcon name="lock" solid className="text-sm" />
                    {t('login')}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
