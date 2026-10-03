import FlashBanner from '@/Components/FlashBanner';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1.5 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function ForgotPassword({ status }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title={t('forgot_password_title')} />

            <div className="mb-6">
                <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-600 text-white dark:bg-teal-400 dark:text-teal-950">
                    <NavIcon name="lock" className="text-lg" />
                </div>
                <h1 className="font-display text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    {t('forgot_password_title')}
                </h1>
                <p className="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {t('forgot_password_hint')}
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
                        isFocused
                        dir="ltr"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href={route('login')}>
                        <SecondaryButton type="button">
                            {t('login')}
                        </SecondaryButton>
                    </Link>
                    <PrimaryButton
                        disabled={processing}
                        className="!bg-teal-700 hover:!bg-teal-600 dark:!bg-teal-400 dark:!text-teal-950"
                    >
                        <NavIcon name="lock" className="text-sm" />
                        {t('forgot_password_send')}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
