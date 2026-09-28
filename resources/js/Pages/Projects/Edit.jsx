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
        client: project.client || '',
        description: project.description || '',
        location: project.location || '',
        contract_number: project.contract_number || '',
        status: project.status || 'planning',
        start_date: project.start_date ? String(project.start_date).slice(0, 10) : '',
        end_date: project.end_date ? String(project.end_date).slice(0, 10) : '',
        contract_value_iqd: project.contract_value_iqd ?? '',
        budget_iqd: project.budget_iqd ?? '',
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
                        <InputLabel htmlFor="client" value={t('client')} />
                        <TextInput id="client" className="mt-1 block w-full" value={data.client} onChange={(e) => setData('client', e.target.value)} />
                        <InputError message={errors.client} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="location" value={t('location')} />
                        <TextInput id="location" className="mt-1 block w-full" value={data.location} onChange={(e) => setData('location', e.target.value)} />
                    </div>
                    <div>
                        <InputLabel htmlFor="contract_number" value={t('contract_number')} />
                        <TextInput id="contract_number" className="mt-1 block w-full" value={data.contract_number} onChange={(e) => setData('contract_number', e.target.value)} />
                        <InputError message={errors.contract_number} className="mt-1" />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="start_date" value={t('start_date')} />
                            <TextInput id="start_date" type="date" className="mt-1 block w-full" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                            <InputError message={errors.start_date} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="end_date" value={t('end_date')} />
                            <TextInput id="end_date" type="date" className="mt-1 block w-full" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} />
                            <InputError message={errors.end_date} className="mt-1" />
                        </div>
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
                                <option key={s} value={s}>{t(`status_${s}`, s.replace(/_/g, ' '))}</option>
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
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="contract_value_iqd" value={t('contract_value_iqd')} />
                            <TextInput id="contract_value_iqd" type="number" step="1" min="0" className="mt-1 block w-full" value={data.contract_value_iqd} onChange={(e) => setData('contract_value_iqd', e.target.value)} />
                            <InputError message={errors.contract_value_iqd} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="budget_iqd" value={t('budget_iqd')} />
                            <TextInput id="budget_iqd" type="number" step="1" min="0" className="mt-1 block w-full" value={data.budget_iqd} onChange={(e) => setData('budget_iqd', e.target.value)} />
                            <InputError message={errors.budget_iqd} className="mt-1" />
                        </div>
                    </div>
                    <PrimaryButton disabled={processing}>{t('update')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
