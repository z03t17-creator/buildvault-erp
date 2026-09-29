import FlashBanner from '@/Components/FlashBanner';
import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <div className="mb-6">
                <h1 className="font-display text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    Verify email
                </h1>
                <p className="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Thanks for signing up. Please verify your email address using the link we sent
                    you, or request another below.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <FlashBanner tone="success" className="mb-5">
                    A new verification link has been sent to your email address.
                </FlashBanner>
            )}

            <form onSubmit={submit}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <PrimaryButton disabled={processing}>Resend Verification Email</PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-slate-600 underline underline-offset-2 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-slate-400 dark:hover:text-emerald-400"
                    >
                        Log Out
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
