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

export default function Create({ suppliers, defaults }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        sku: '',
        category: '',
        unit: defaults?.unit || 'pcs',
        quantity: defaults?.quantity ?? 0,
        min_quantity: defaults?.min_quantity ?? 0,
        purchase_price_iqd: defaults?.purchase_price_iqd ?? 0,
        supplier_id: '',
        location: '',
        notes: '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('new_product')}
                    actions={
                        <Link href={route('stock.items.index')}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('new_product')} />
            <PageShell narrow>
                <DataPanel>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('stock.items.store'));
                        }}
                        className="space-y-5"
                    >
                        <FormSection>
                            {[
                                ['name', 'name', true],
                                ['sku', 'sku', false],
                                ['category', 'category', false],
                                ['unit', 'unit', true],
                                ['quantity', 'quantity', false],
                                ['min_quantity', 'min_quantity', false],
                                ['location', 'location', false],
                            ].map(([field, labelKey, required]) => (
                                <FormField key={field}>
                                    <InputLabel value={t(labelKey)} />
                                    <TextInput
                                        className="mt-1 block w-full"
                                        type={['quantity', 'min_quantity'].includes(field) ? 'number' : 'text'}
                                        step="0.001"
                                        value={data[field]}
                                        onChange={(e) => setData(field, e.target.value)}
                                        required={required}
                                    />
                                    <InputError message={errors[field]} className="mt-1" />
                                </FormField>
                            ))}
                            <FormField>
                                <InputLabel value={`${t('purchase_price_iqd')} (${iqd})`} />
                                <MoneyInput
                                    className="mt-1 block w-full"
                                    value={data.purchase_price_iqd}
                                    onValueChange={(raw) => setData('purchase_price_iqd', raw)}
                                />
                                <InputError message={errors.purchase_price_iqd} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('supplier')} />
                                <select
                                    className={selectClass}
                                    value={data.supplier_id}
                                    onChange={(e) => setData('supplier_id', e.target.value)}
                                >
                                    <option value="">—</option>
                                    {(suppliers || []).map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.supplier_id} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('notes')} />
                                <textarea
                                    className={selectClass}
                                    rows={3}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                                <InputError message={errors.notes} className="mt-1" />
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
