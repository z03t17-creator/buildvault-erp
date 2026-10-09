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
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

const fieldClass =
    'mt-1 block w-full min-h-[2.5rem] rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

const moneyFieldClass =
    'mt-1 block w-full min-h-[2.75rem] rounded-xl border border-slate-200 bg-white px-3 font-sans text-lg font-semibold tabular-nums shadow-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100';

export default function Create({ suppliers, categories, defaults }) {
    const t = useTranslations();
    const iqd = t('IQD');
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        sku: '',
        stock_category_id: '',
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
                    subtitle={t('stock_product_form_hint')}
                    icon={<NavIcon name="stockIn" className="text-lg" />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('stock.categories.index')}>
                                <SecondaryButton type="button">
                                    <NavIcon name="stockCategories" className="text-sm" />
                                    {t('stock_categories')}
                                </SecondaryButton>
                            </Link>
                            <Link href={route('stock.items.index')}>
                                <SecondaryButton type="button">{t('back')}</SecondaryButton>
                            </Link>
                        </div>
                    }
                />
            }
        >
            <Head title={t('new_product')} />
            <PageShell narrow className="!space-y-6">
                <DataPanel>
                    <div className="mb-4 flex items-start gap-3">
                        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-900 dark:bg-rose-400/15 dark:text-rose-200">
                            <NavIcon name="stock" className="text-base" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {t('stock_product_form_details')}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {t('stock_product_form_details_hint')}
                            </p>
                        </div>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(route('stock.items.store'));
                        }}
                        className="space-y-5"
                    >
                        <FormSection cols={2}>
                            <FormField>
                                <InputLabel value={t('name')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('sku')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                />
                                <InputError message={errors.sku} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('category')} />
                                <select
                                    className={fieldClass}
                                    value={data.stock_category_id}
                                    onChange={(e) => setData('stock_category_id', e.target.value)}
                                >
                                    <option value="">{t('uncategorized')}</option>
                                    {(categories || []).map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.stock_category_id} className="mt-1" />
                                {(categories || []).length === 0 ? (
                                    <p className="mt-1 text-xs text-slate-500">
                                        <Link
                                            href={route('stock.categories.create')}
                                            className="font-semibold text-rose-700 underline-offset-2 hover:underline dark:text-rose-300"
                                        >
                                            {t('new_stock_category')}
                                        </Link>
                                    </p>
                                ) : null}
                            </FormField>
                            <FormField>
                                <InputLabel value={t('unit')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.unit}
                                    onChange={(e) => setData('unit', e.target.value)}
                                    required
                                />
                                <InputError message={errors.unit} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('quantity')} />
                                <TextInput
                                    className={fieldClass}
                                    type="number"
                                    step="0.001"
                                    value={data.quantity}
                                    onChange={(e) => setData('quantity', e.target.value)}
                                />
                                <InputError message={errors.quantity} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('min_quantity')} />
                                <TextInput
                                    className={fieldClass}
                                    type="number"
                                    step="0.001"
                                    value={data.min_quantity}
                                    onChange={(e) => setData('min_quantity', e.target.value)}
                                />
                                <InputError message={errors.min_quantity} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={`${t('purchase_price_iqd')} (${iqd})`} />
                                <MoneyInput
                                    className={moneyFieldClass}
                                    value={data.purchase_price_iqd}
                                    onValueChange={(raw) => setData('purchase_price_iqd', raw)}
                                />
                                <InputError message={errors.purchase_price_iqd} className="mt-1" />
                            </FormField>
                            <FormField>
                                <InputLabel value={t('supplier')} />
                                <select
                                    className={fieldClass}
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
                                <InputLabel value={t('location')} />
                                <TextInput
                                    className={fieldClass}
                                    value={data.location}
                                    onChange={(e) => setData('location', e.target.value)}
                                />
                                <InputError message={errors.location} className="mt-1" />
                            </FormField>
                            <FormField className="sm:col-span-2">
                                <InputLabel value={t('notes')} />
                                <textarea
                                    className={fieldClass + ' py-2'}
                                    rows={3}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                                <InputError message={errors.notes} className="mt-1" />
                            </FormField>
                        </FormSection>
                        <FormActions>
                            <PrimaryButton
                                disabled={processing}
                                className="!bg-rose-600 hover:!bg-rose-500"
                            >
                                {t('save')}
                            </PrimaryButton>
                        </FormActions>
                    </form>
                </DataPanel>
            </PageShell>
        </AuthenticatedLayout>
    );
}
