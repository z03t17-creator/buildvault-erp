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

export default function Edit({ item, suppliers }) {
    const t = useTranslations();
    const { data, setData, put, processing, errors } = useForm({
        name: item.name || '',
        sku: item.sku || '',
        category: item.category || '',
        unit: item.unit || 'pcs',
        min_quantity: item.min_quantity ?? 0,
        purchase_price_iqd: item.purchase_price_iqd ?? 0,
        supplier_id: item.supplier_id || '',
        location: item.location || '',
        notes: item.notes || '',
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={t('edit_product')}
                    actions={
                        <Link href={route('stock.items.show', item.id)}>
                            <SecondaryButton>{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('edit_product')} />
            <div className="py-8">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('stock.items.update', item.id));
                    }}
                    className="mx-auto max-w-xl space-y-4 border border-slate-200/80 bg-white/80 p-6 dark:border-slate-700 dark:bg-slate-900/70"
                >
                    <p className="text-sm text-slate-500">
                        {t('quantity')}: <span className="tabular-nums font-medium text-slate-800 dark:text-slate-100">{item.quantity}</span>{' '}
                        {item.unit} — {t('qty_via_movements')}
                    </p>
                    {[
                        ['name', 'name', true],
                        ['sku', 'sku', false],
                        ['category', 'category', false],
                        ['unit', 'unit', true],
                        ['min_quantity', 'min_quantity', false],
                        ['purchase_price_iqd', 'purchase_price_iqd', false],
                        ['location', 'location', false],
                    ].map(([field, labelKey, required]) => (
                        <div key={field}>
                            <InputLabel value={t(labelKey)} />
                            <TextInput
                                className="mt-1 block w-full"
                                type={['min_quantity', 'purchase_price_iqd'].includes(field) ? 'number' : 'text'}
                                step={field === 'purchase_price_iqd' ? '1' : '0.001'}
                                value={data[field]}
                                onChange={(e) => setData(field, e.target.value)}
                                required={required}
                            />
                            <InputError message={errors[field]} className="mt-1" />
                        </div>
                    ))}
                    <div>
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
                    </div>
                    <div>
                        <InputLabel value={t('notes')} />
                        <textarea
                            className={selectClass}
                            rows={3}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </div>
                    <PrimaryButton disabled={processing}>{t('update')}</PrimaryButton>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
