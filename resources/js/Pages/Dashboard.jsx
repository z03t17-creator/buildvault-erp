import BrandMark from '@/Components/BrandMark';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, router } from '@inertiajs/react';

export default function Dashboard({ maturedHolds }) {
    const t = useTranslations();
    const alerts = maturedHolds || [];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <h2 className="font-display text-2xl font-semibold leading-tight tracking-tight text-slate-900 dark:text-white">
                        {t('dashboard')}
                    </h2>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        BuildVault ERP · ZHAKO
                    </p>
                </div>
            }
        >
            <Head title={t('dashboard')} />

            <div className="py-10">
                <div className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {alerts.length > 0 && (
                        <section className="border border-amber-300/80 bg-amber-50/90 p-5 dark:border-amber-700/60 dark:bg-amber-950/40">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-display text-lg font-semibold text-amber-950 dark:text-amber-100">
                                        Insurance ready to return
                                    </h3>
                                    <p className="mt-1 text-sm text-amber-900/80 dark:text-amber-200/80">
                                        {alerts.length} matured hold{alerts.length === 1 ? '' : 's'} — release returns funds to the staff payroll pool.
                                    </p>
                                </div>
                                <Link href={route('retention-holds.index')} className="text-sm font-medium text-amber-900 underline dark:text-amber-200">
                                    View all holds
                                </Link>
                            </div>
                            <ul className="mt-4 divide-y divide-amber-200/80 dark:divide-amber-800/60">
                                {alerts.map((hold) => (
                                    <li key={hold.id} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                        <div>
                                            <span className="font-medium text-slate-900 dark:text-slate-100">
                                                {hold.worker?.name || `Worker #${hold.worker_id}`}
                                            </span>
                                            <span className="text-slate-500"> · {hold.project?.name}</span>
                                            <div className="mt-0.5 tabular-nums text-slate-700 dark:text-slate-300">
                                                {hold.amount_usd} USD · matured {hold.maturity_date}
                                            </div>
                                        </div>
                                        <PrimaryButton
                                            type="button"
                                            onClick={() => router.post(route('retention-holds.release', hold.id))}
                                        >
                                            Release to payroll
                                        </PrimaryButton>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <section className="flex flex-col items-center justify-center gap-6 border border-slate-200/80 bg-white/80 px-6 py-12 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900/70 sm:py-16">
                        <BrandMark size="hero" href={null} />
                        <p className="max-w-md text-base text-slate-600 dark:text-slate-300">
                            {t('logged_in')}
                        </p>
                        <Link href={route('dashboards.vault')}>
                            <PrimaryButton type="button">Open Zhako Vault</PrimaryButton>
                        </Link>
                        {alerts.length === 0 && (
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                No matured insurance holds awaiting release.
                            </p>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
