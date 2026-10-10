import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function Create() {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        contact_name: '',
        phone: '',
        email: '',
        notes: '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('new_supplier')}
                    subtitle={t('suppliers_form_hint')}
                    icon={<NavIcon name="suppliers" className="text-lg" />}
                    actions={
                        <Link href={route('stock.suppliers.index')}>
                            <SecondaryButton type="button">{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('new_supplier')} />
            <PageShell narrow className="!space-y-6">
                <StockTabs />
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('stock.suppliers.store'));
                        }}
                        className="space-y-5"
                    >
                        <FormSection cols={2}>
                            {['name', 'contact_name', 'phone', 'email'].map((field) => (
                                <FormField key={field}>
                                    <InputLabel value={t(field)} />
                                    <TextInput
                                        className={fieldClass}
                                        type={field === 'email' ? 'email' : 'text'}
                                        value={data[field]}
                                        onChange={(e) => setData(field, e.target.value)}
                                        required={field === 'name'}
                                        autoFocus={field === 'name'}
                                    />
                                    <InputError message={errors[field]} className="mt-1" />
                                </FormField>
                            ))}
                            <FormField className="sm:col-span-2">
                                <InputLabel value={t('notes')} />
                                <textarea
                                    className={fieldClass + ' py-2'}
                                    rows={3}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                                <InputError message={errors.notes} className="mt-1" />
                            </FormField>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton
                                disabled={processing}
                                className="!bg-rose-600 hover:!bg-rose-500"
                            >
                                {t('save')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
