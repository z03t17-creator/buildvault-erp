import BrandMark from '@/Components/BrandMark';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    const t = useTranslations();

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
                    <section className="flex flex-col items-center justify-center gap-6 border border-slate-200/80 bg-white/80 px-6 py-12 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900/70 sm:py-16">
                        <BrandMark size="hero" href={null} />
                        <p className="max-w-md text-base text-slate-600 dark:text-slate-300">
                            {t('logged_in')}
                        </p>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
