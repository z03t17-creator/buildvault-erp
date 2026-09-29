import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import MoneyInput from '@/Components/MoneyInput';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

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
        contract_value_iqd: project.contract_value_iqd != null ? String(Math.round(Number(project.contract_value_iqd))) : '',
        budget_iqd: project.budget_iqd != null ? String(Math.round(Number(project.budget_iqd))) : '',
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
            <PageShell narrow>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            put(route('projects.update', project.id));
                        }}
                        className="space-y-5"
                    >
                        <FormSection>
                            <FormField>
                                <InputLabel htmlFor="name" value={t('name')} />
                                <TextInput id="name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                <InputError message={errors.name} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="client" value={t('client')} />
                                <TextInput id="client" className="mt-1 block w-full" value={data.client} onChange={(e) => setData('client', e.target.value)} />
                                <InputError message={errors.client} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="location" value={t('location')} />
                                <TextInput id="location" className="mt-1 block w-full" value={data.location} onChange={(e) => setData('location', e.target.value)} />
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="contract_number" value={t('contract_number')} />
                                <TextInput id="contract_number" className="mt-1 block w-full" value={data.contract_number} onChange={(e) => setData('contract_number', e.target.value)} />
                                <InputError message={errors.contract_number} className="mt-1" />
                            </FormField>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <FormField>
                                    <InputLabel htmlFor="start_date" value={t('start_date')} />
                                    <TextInput id="start_date" type="date" className="mt-1 block w-full" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} />
                                    <InputError message={errors.start_date} className="mt-1" />
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="end_date" value={t('end_date')} />
                                    <TextInput id="end_date" type="date" className="mt-1 block w-full" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} />
                                    <InputError message={errors.end_date} className="mt-1" />
                                </FormField>
                            </div>
                            <FormField>
                                <InputLabel htmlFor="status" value={t('status')} />
                                <select
                                    id="status"
                                    className={selectClass}
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                >
                                    {(statuses || []).map((s) => (
                                        <option key={s} value={s}>{t(`status_${s}`, s.replace(/_/g, ' '))}</option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField>
                                <InputLabel htmlFor="description" value={t('description')} />
                                <textarea
                                    id="description"
                                    className={selectClass}
                                    rows={3}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                            </FormField>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <FormField>
                                    <InputLabel htmlFor="contract_value_iqd" value={`${t('contract_value_iqd')} (${t('IQD')})`} />
                                    <MoneyInput id="contract_value_iqd" className="mt-1 block w-full" value={data.contract_value_iqd} onValueChange={(raw) => setData('contract_value_iqd', raw)} />
                                    <InputError message={errors.contract_value_iqd} className="mt-1" />
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="budget_iqd" value={`${t('budget_iqd')} (${t('IQD')})`} />
                                    <MoneyInput id="budget_iqd" className="mt-1 block w-full" value={data.budget_iqd} onValueChange={(raw) => setData('budget_iqd', raw)} />
                                    <InputError message={errors.budget_iqd} className="mt-1" />
                                </FormField>
                            </div>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton disabled={processing}>{t('update')}</PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
