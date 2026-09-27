import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ project, statuses }) {
    const t = useTranslations();
    const { data, setData, put, processing, errors } = useForm({
        name: project.name || '',
        description: project.description || '',
        location: project.location || '',
        status: project.status || 'planning',
        total_budget_usd: project.total_budget_usd ?? '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${t('edit')}: ${project.name}`}
                    actions={
                        <Link href={route('projects.show', project.id)}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={`${t('edit')} ${project.name}`} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('projects.update', project.id));
                    }}
                    className="mx-auto max-w-2xl space-y-5 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div>
                        <InputLabel htmlFor="name" value={t('name')} />
                        <TextInput id="name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="location" value={t('location')} />
                        <TextInput id="location" className="mt-1 block w-full" value={data.location} onChange={(e) => setData('location', e.target.value)} />
                    </div>
                    <div>
                        <InputLabel htmlFor="status" value={t('status')} />
                        <select
                            id="status"
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                        >
                            {(statuses || []).map((s) => (
                                <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="description" value={t('description')} />
                        <textarea
                            id="description"
                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                            rows={3}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="budget" value={t('budget_usd')} />
                        <TextInput id="budget" type="number" step="0.01" className="mt-1 block w-full" value={data.total_budget_usd} onChange={(e) => setData('total_budget_usd', e.target.value)} />
                    </div>
                    <PrimaryButton disabled={processing}>{t('update')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
