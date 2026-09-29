import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PageShell from '@/Components/PageShell';
import DataPanel from '@/Components/DataPanel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ tower, project }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({ name: '' });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('create_floor')}
                    subtitle={`${project?.name} · ${tower?.name}`}
                    actions={
                        <Link href={route('towers.floors.index', tower.id)}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('create_floor')} />
            <PageShell narrow>
                <DataPanel>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('towers.floors.store', tower.id));
                    }}
                    className="space-y-5"
                >
                    <div>
                        <InputLabel htmlFor="name" value={t('name')} />
                        <TextInput id="name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} className="mt-1" />
                    </div>
                    <PrimaryButton disabled={processing}>{t('save')}</PrimaryButton>
                </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
