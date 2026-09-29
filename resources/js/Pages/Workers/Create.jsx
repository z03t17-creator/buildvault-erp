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

export default function Create({ projects, roles }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        role: 'laborer',
        project_id: '',
        daily_rate_usd: '',
        overtime_rate_usd: '',
        manual_ot_hours: '',
        spending_limit_usd: '',
        phone: '',
        national_id_number: '',
        avatar: null,
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('create_worker')}
                    actions={
                        <Link href={route('workers.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('create_worker')} />
            <PageShell narrow>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('workers.store'), { forceFormData: true });
                        }}
                        className="space-y-5"
                        encType="multipart/form-data"
                    >
                        <FormSection>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <FormField className="sm:col-span-2">
                                    <InputLabel htmlFor="name" value={t('name')} />
                                    <TextInput id="name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                    <InputError message={errors.name} className="mt-1" />
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="role" value={t('role')} />
                                    <select id="role" className={selectClass} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                                        {(roles || []).map((r) => (
                                            <option key={r} value={r}>{r.replace(/_/g, ' ')}</option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="project_id" value={t('project')} />
                                    <select id="project_id" className={selectClass} value={data.project_id} onChange={(e) => setData('project_id', e.target.value)}>
                                        <option value="">{t('unassigned')}</option>
                                        {(projects || []).map((p) => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="daily_rate_usd" value={t('daily_rate')} />
                                    <MoneyInput id="daily_rate_usd" allowDecimals className="mt-1 block w-full" value={data.daily_rate_usd} onValueChange={(raw) => setData('daily_rate_usd', raw)} />
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="overtime_rate_usd" value={t('overtime_rate')} />
                                    <MoneyInput id="overtime_rate_usd" allowDecimals className="mt-1 block w-full" value={data.overtime_rate_usd} onValueChange={(raw) => setData('overtime_rate_usd', raw)} />
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="manual_ot_hours" value={t('manual_ot_hours')} />
                                    <TextInput
                                        id="manual_ot_hours"
                                        type="number"
                                        step="0.25"
                                        min="0"
                                        className="mt-1 block w-full"
                                        value={data.manual_ot_hours}
                                        onChange={(e) => setData('manual_ot_hours', e.target.value)}
                                    />
                                    <InputError message={errors.manual_ot_hours} className="mt-1" />
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="phone" value={t('phone')} />
                                    <TextInput id="phone" className="mt-1 block w-full" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                </FormField>
                                <FormField>
                                    <InputLabel htmlFor="national_id_number" value={t('national_id')} />
                                    <TextInput id="national_id_number" className="mt-1 block w-full" value={data.national_id_number} onChange={(e) => setData('national_id_number', e.target.value)} />
                                </FormField>
                                <FormField className="sm:col-span-2">
                                    <InputLabel htmlFor="avatar" value={t('avatar')} />
                                    <input
                                        id="avatar"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/gif"
                                        className="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-emerald-500 dark:text-slate-300"
                                        onChange={(e) => setData('avatar', e.target.files?.[0] ?? null)}
                                    />
                                    <p className="mt-1 text-xs text-slate-500">{t('avatar_hint')}</p>
                                    <InputError message={errors.avatar} className="mt-1" />
                                </FormField>
                            </div>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton disabled={processing}>{t('save')}</PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
