import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Edit({ worker, projects, roles }) {
    const t = useTranslations();
    const { data, setData, put, processing, errors } = useForm({
        name: worker.name || '',
        role: worker.role || 'laborer',
        project_id: worker.project_id ?? '',
        daily_rate_usd: worker.daily_rate_usd ?? '',
        overtime_rate_usd: worker.overtime_rate_usd ?? '',
        spending_limit_usd: worker.spending_limit_usd ?? '',
        phone: worker.phone || '',
        national_id_number: worker.national_id_number || '',
        avatar_path: worker.avatar_path || '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${t('edit')}: ${worker.name}`}
                    actions={
                        <Link href={route('workers.show', worker.id)}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={`${t('edit')} ${worker.name}`} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('workers.update', worker.id));
                    }}
                    className="mx-auto max-w-2xl space-y-5 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="name" value={t('name')} />
                            <TextInput id="name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                            <InputError message={errors.name} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="role" value={t('role')} />
                            <select id="role" className={selectClass} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                                {(roles || []).map((r) => (
                                    <option key={r} value={r}>{r.replace(/_/g, ' ')}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="project_id" value={t('project')} />
                            <select id="project_id" className={selectClass} value={data.project_id} onChange={(e) => setData('project_id', e.target.value)}>
                                <option value="">{t('unassigned')}</option>
                                {(projects || []).map((p) => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="daily_rate_usd" value={t('daily_rate')} />
                            <TextInput id="daily_rate_usd" type="number" step="0.01" className="mt-1 block w-full" value={data.daily_rate_usd} onChange={(e) => setData('daily_rate_usd', e.target.value)} />
                        </div>
                        <div>
                            <InputLabel htmlFor="overtime_rate_usd" value={t('overtime_rate')} />
                            <TextInput id="overtime_rate_usd" type="number" step="0.01" className="mt-1 block w-full" value={data.overtime_rate_usd} onChange={(e) => setData('overtime_rate_usd', e.target.value)} />
                        </div>
                        <div>
                            <InputLabel htmlFor="phone" value={t('phone')} />
                            <TextInput id="phone" className="mt-1 block w-full" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        </div>
                        <div>
                            <InputLabel htmlFor="avatar_path" value={t('avatar_path')} />
                            <TextInput id="avatar_path" className="mt-1 block w-full" value={data.avatar_path} onChange={(e) => setData('avatar_path', e.target.value)} />
                            <p className="mt-1 text-xs text-slate-500">{t('avatar_upload_later')}</p>
                        </div>
                    </div>
                    <PrimaryButton disabled={processing}>{t('update')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
