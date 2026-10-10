import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { stockFieldClass, stockMoneyClass } from '@/Components/StockDesk';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ suppliers = [], categories = [], units = [], defaults = {} }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const unitOptions = units.length ? units : ['Pcs', 'M2', 'Bag', 'Meter'];
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        sku: '',
        barcode: '',
        auto_sku: defaults.auto_sku !== false,
        stock_category_id: '',
        unit: defaults.unit || 'Pcs',
        quantity: defaults.quantity ?? 0,
        min_quantity: defaults.min_quantity ?? 0,
        purchase_price_iqd: defaults.purchase_price_iqd ?? 0,
        supplier_id: '',
        location: '',
        notes: '',
    });

    return (
        <AuthenticatedLayout
            desk
            header={
                <PageHeader
                    title={t('warehouse_add_item')}
                    subtitle={t('warehouse_item_form_hint')}
                    icon={<NavIcon name="stock" className="text-lg text-emerald-700 dark:text-emerald-300" />}
                    actions={
                        <Link href={route('stock.items.index')}>
                            <SecondaryButton type="button">{t('back')}</SecondaryButton>
                        </Link>
                    }
                />
            }
        >
            <Head title={t('warehouse_add_item')} />
            <PageShell className="!max-w-4xl !space-y-4">
                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('stock.items.store'));
                    }}
                    className="bv-card space-y-4 p-4 sm:p-5"
                >
                    <FormSection cols={2}>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('name')} htmlFor="name" />
                            <TextInput
                                id="name"
                                className={stockFieldClass}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                autoFocus
                            />
                            <InputError message={errors.name} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('category')} htmlFor="stock_category_id" />
                            <select
                                id="stock_category_id"
                                className={stockFieldClass}
                                value={data.stock_category_id}
                                onChange={(e) => setData('stock_category_id', e.target.value)}
                            >
                                <option value="">{t('uncategorized')}</option>
                                {categories.map((cat) => (
                                    <option key={cat.id} value={cat.id}>
                                        {cat.name}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField>
                            <InputLabel value={t('unit')} htmlFor="unit" />
                            <select
                                id="unit"
                                className={stockFieldClass}
                                value={data.unit}
                                onChange={(e) => setData('unit', e.target.value)}
                            >
                                {unitOptions.map((unit) => (
                                    <option key={unit} value={unit}>
                                        {unit}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField>
                            <div className="flex items-center justify-between gap-2">
                                <InputLabel value={t('sku')} htmlFor="sku" />
                                <label className="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                    <input
                                        type="checkbox"
                                        checked={!!data.auto_sku}
                                        onChange={(e) => setData('auto_sku', e.target.checked)}
                                    />
                                    {t('warehouse_auto_sku')}
                                </label>
                            </div>
                            <TextInput
                                id="sku"
                                className={stockFieldClass}
                                value={data.sku}
                                onChange={(e) => setData('sku', e.target.value)}
                                disabled={!!data.auto_sku}
                                placeholder={data.auto_sku ? t('warehouse_auto_sku_hint') : ''}
                            />
                            <InputError message={errors.sku} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('barcode')} htmlFor="barcode" />
                            <TextInput
                                id="barcode"
                                className={stockFieldClass}
                                value={data.barcode}
                                onChange={(e) => setData('barcode', e.target.value)}
                                placeholder={t('warehouse_barcode_hint')}
                            />
                            <InputError message={errors.barcode} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('quantity')} htmlFor="quantity" />
                            <MoneyInput
                                id="quantity"
                                className={stockMoneyClass}
                                value={String(data.quantity ?? '')}
                                onValueChange={(next) => setData('quantity', next)}
                                allowDecimals
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('min_quantity')} htmlFor="min_quantity" />
                            <MoneyInput
                                id="min_quantity"
                                className={stockMoneyClass}
                                value={String(data.min_quantity ?? '')}
                                onValueChange={(next) => setData('min_quantity', next)}
                                allowDecimals
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={`${t('warehouse_avg_cost')} (${iqd})`} htmlFor="purchase_price_iqd" />
                            <MoneyInput
                                id="purchase_price_iqd"
                                className={stockMoneyClass}
                                value={String(data.purchase_price_iqd ?? '')}
                                onValueChange={(next) => setData('purchase_price_iqd', next)}
                                allowDecimals={false}
                            />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('supplier')} htmlFor="supplier_id" />
                            <select
                                id="supplier_id"
                                className={stockFieldClass}
                                value={data.supplier_id}
                                onChange={(e) => setData('supplier_id', e.target.value)}
                            >
                                <option value="">—</option>
                                {suppliers.map((row) => (
                                    <option key={row.id} value={row.id}>
                                        {row.name}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('warehouse_shelf')} htmlFor="location" />
                            <TextInput
                                id="location"
                                className={stockFieldClass}
                                value={data.location}
                                onChange={(e) => setData('location', e.target.value)}
                                placeholder={t('warehouse_shelf_placeholder')}
                            />
                        </FormField>
                        <FormField className="sm:col-span-2">
                            <InputLabel value={t('note')} htmlFor="notes" />
                            <TextInput
                                id="notes"
                                className={stockFieldClass}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                        </FormField>
                    </FormSection>
                    <FormActions>
                        <PrimaryButton disabled={processing}>{t('save')}</PrimaryButton>
                        <Link href={route('stock.items.index')}>
                            <SecondaryButton type="button">{t('cancel')}</SecondaryButton>
                        </Link>
                    </FormActions>
                </form>
            </PageShell>
        </AuthenticatedLayout>
    );
}
