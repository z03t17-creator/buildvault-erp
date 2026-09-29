import DataPanel from '@/Components/DataPanel';
import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { Head, Link, useForm } from '@inertiajs/react';

const selectClass =
    'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ projects, currencies }) {
    const t = useTranslations();
    const { data, setData, post, processing, errors } = useForm({
        project_id: '',
        client_name: '',
        currency: 'USD',
        amount: '',
        received_on: new Date().toISOString().slice(0, 10),
        reference: '',
        notes: '',
        lock_retention: true,
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('new_client_advance')}
                    actions={
                        <Link href={route('client-advances.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('new_client_advance')} />
            <PageShell narrow>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('client-advances.store'));
                        }}
                        className="space-y-5"
                    >
                        <FormSection>
                            <FormField>
                                <InputLabel value={t('project')} />
                                <select
                                    className={selectClass}
                                    value={data.project_id}
                                    onChange={(e) => setData('project_id', e.target.value)}
                                    required
                                >
                                    <option value="">—</option>
                                    {(projects || []).map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                <InputError message={errors.project_id} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('client_name')} />
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={data.client_name}
                                    onChange={(e) => setData('client_name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.client_name} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('currency')} />
                                <select
                                    className={selectClass}
                                    value={data.currency}
                                    onChange={(e) => setData('currency', e.target.value)}
                                >
                                    {(currencies || ['USD', 'IQD']).map((c) => (
                                        <option key={c} value={c}>{c}</option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField>
                                <InputLabel value={`${t('money_in')} / ${t('amount')}`} />
                                <MoneyInput
                                    className="mt-1 block w-full"
                                    value={data.amount}
                                    onValueChange={(raw) => setData('amount', raw)}
                                    required
                                />
                                <InputError message={errors.amount} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('date')} />
                                <TextInput
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.received_on}
                                    onChange={(e) => setData('received_on', e.target.value)}
                                    required
                                />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('reference')} />
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                />
                            </FormField>
                            <FormField className="sm:col-span-2">
                                <label className="inline-flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={!!data.lock_retention}
                                        onChange={(e) => setData('lock_retention', e.target.checked)}
                                    />
                                    {t('lock_retention')}
                                </label>
                            </FormField>
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
