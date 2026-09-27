import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
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
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('towers.floors.store', tower.id));
                    }}
                    className="mx-auto max-w-lg space-y-5 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div>
                        <InputLabel htmlFor="name" value={t('name')} />
                        <TextInput id="name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} className="mt-1" />
                    </div>
                    <PrimaryButton disabled={processing}>{t('save')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
