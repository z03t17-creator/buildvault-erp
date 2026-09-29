import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';
import PageShell from '@/Components/PageShell';
import DataPanel from '@/Components/DataPanel';

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
                    actions={
                        <Link href={route('stock.suppliers.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('new_supplier')} />
            <PageShell narrow>
                <DataPanel>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('stock.suppliers.store'));
                    }}
                    className="space-y-5 dark:"
                >
                    {['name', 'contact_name', 'phone', 'email'].map((field) => (
                        <div key={field}>
                            <InputLabel value={t(field === 'name' ? 'name' : field)} />
                            <TextInput
                                className="mt-1 block w-full"
                                type={field === 'email' ? 'email' : 'text'}
                                value={data[field]}
                                onChange={(e) => setData(field, e.target.value)}
                                required={field === 'name'}
                            />
                            <InputError message={errors[field]} className="mt-1" />
                        </div>
                    ))}
                    <div>
                        <InputLabel value={t('notes')} />
                        <textarea
                            className="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-950"
                            rows={3}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </div>
                    <PrimaryButton disabled={processing}>{t('save')}</PrimaryButton>
                </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
