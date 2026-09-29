import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const textareaClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Edit({ supplier }) {
    const t = useTranslations();
    const { data, setData, put, processing, errors } = useForm({
        name: supplier.name || '',
        contact_name: supplier.contact_name || '',
        phone: supplier.phone || '',
        email: supplier.email || '',
        notes: supplier.notes || '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('edit_supplier')}
                    actions={
                        <Link href={route('stock.suppliers.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('edit_supplier')} />
            <PageShell narrow>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            put(route('stock.suppliers.update', supplier.id));
                        }}
                        className="space-y-5"
                    >
                        <FormSection>
                            {['name', 'contact_name', 'phone', 'email'].map((field) => (
                                <FormField key={field}>
                                    <InputLabel value={t(field)} />
                                    <TextInput
                                        className="mt-1 block w-full"
                                        type={field === 'email' ? 'email' : 'text'}
                                        value={data[field]}
                                        onChange={(e) => setData(field, e.target.value)}
                                        required={field === 'name'}
                                    />
                                    <InputError message={errors[field]} className="mt-1" />
                                </FormField>
                            ))}
                            <FormField>
                                <InputLabel value={t('notes')} />
                                <textarea
                                    className={textareaClass}
                                    rows={3}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                            </FormField>
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
