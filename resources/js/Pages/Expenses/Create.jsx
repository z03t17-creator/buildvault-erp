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

export default function Create({ projects, categories, paymentMethods }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const { data, setData, post, processing, errors } = useForm({
        project_id: '',
        category: 'materials',
        amount_iqd: '',
        expense_date: new Date().toISOString().slice(0, 10),
        supplier: '',
        payment_method: 'cash',
        description: '',
        receipt: null,
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('new_expense')}
                    actions={
                        <Link href={route('expenses.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('new_expense')} />
            <PageShell narrow>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('expenses.store'), { forceFormData: true });
                        }}
                        className="space-y-5"
                        encType="multipart/form-data"
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
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.project_id} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('category')} />
                                <select
                                    className={selectClass}
                                    value={data.category}
                                    onChange={(e) => setData('category', e.target.value)}
                                >
                                    {(categories || []).map((c) => (
                                        <option key={c} value={c}>
                                            {t(`expense_category_${c}`) !== `expense_category_${c}`
                                                ? t(`expense_category_${c}`)
                                                : c}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.category} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={`${t('amount_iqd')} (${iqd})`} />
                                <MoneyInput
                                    className="mt-1 block w-full"
                                    value={data.amount_iqd}
                                    onValueChange={(raw) => setData('amount_iqd', raw)}
                                    required
                                />
                                <InputError message={errors.amount_iqd} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('expense_date')} />
                                <TextInput
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.expense_date}
                                    onChange={(e) => setData('expense_date', e.target.value)}
                                    required
                                />
                                <InputError message={errors.expense_date} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('supplier_person')} />
                                <TextInput
                                    className="mt-1 block w-full"
                                    value={data.supplier}
                                    onChange={(e) => setData('supplier', e.target.value)}
                                />
                                <InputError message={errors.supplier} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('payment_method')} />
                                <select
                                    className={selectClass}
                                    value={data.payment_method}
                                    onChange={(e) => setData('payment_method', e.target.value)}
                                >
                                    {(paymentMethods || []).map((m) => (
                                        <option key={m} value={m}>
                                            {t(`payment_${m}`) !== `payment_${m}` ? t(`payment_${m}`) : m}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.payment_method} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('description')} />
                                <textarea
                                    className={selectClass}
                                    rows={2}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                <InputError message={errors.description} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('receipt_file')} />
                                <input
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    className="mt-1 block w-full text-sm"
                                    onChange={(e) => setData('receipt', e.target.files?.[0] || null)}
                                />
                                <InputError message={errors.receipt} className="mt-1" />
                            </FormField>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton disabled={processing}>{t('create_pending')}</PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
