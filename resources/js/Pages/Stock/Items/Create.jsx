import FormSection, { FormActions, FormField } from '@/Components/FormSection';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import MoneyInput from '@/Components/MoneyInput';
import PageHeader from '@/Components/PageHeader';
import PageShell from '@/Components/PageShell';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import SuggestionCombobox from '@/Components/SuggestionCombobox';
import { stockFieldClass, stockMoneyClass } from '@/Components/StockDesk';
import TextInput from '@/Components/TextInput';
import StockTabs from '@/Components/StockTabs';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import useTranslations from '@/hooks/useTranslations';
import { NavIcon } from '@/lib/navIcons';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({
    categories = [],
    units = [],
    currencies = ['USD', 'IQD'],
    defaults = {},
}) {
    const t = useTranslations();
    const unitOptions = units.length
        ? units
        : ['Pcs', 'M2', 'Bag', 'Meter', 'دانە', 'm²', 'جوال', 'مەتر'];
    const categoryNames = categories.map((cat) => cat.name).filter(Boolean);
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        auto_sku: true,
        category: '',
        unit: defaults.unit || 'Pcs',
        quantity: 0,
        currency: defaults.currency || 'IQD',
        purchase_price: defaults.purchase_price ?? 0,
        notes: '',
    });
    const priceLabel = data.currency === 'USD' ? t('USD') : t('IQD');

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
                <StockTabs />
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
                            <InputLabel value={t('category')} htmlFor="category" />
                            <SuggestionCombobox
                                id="category"
                                className={stockFieldClass}
                                value={data.category}
                                onChange={(next) => setData('category', next)}
                                suggestions={categoryNames}
                                placeholder={t('warehouse_category_type_hint')}
                            />
                            <InputError message={errors.category || errors.stock_category_id} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('unit')} htmlFor="unit" />
                            <SuggestionCombobox
                                id="unit"
                                className={stockFieldClass}
                                value={data.unit}
                                onChange={(next) => setData('unit', next)}
                                suggestions={unitOptions}
                                placeholder={t('warehouse_unit_type_hint')}
                            />
                            <InputError message={errors.unit} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel value={t('currency')} htmlFor="currency" />
                            <div className="mt-1 flex gap-2">
                                {currencies.map((code) => (
                                    <button
                                        key={code}
                                        type="button"
                                        onClick={() => setData('currency', code)}
                                        className={
                                            'min-h-[2.5rem] flex-1 rounded-xl border text-sm font-semibold transition ' +
                                            (data.currency === code
                                                ? 'border-emerald-500 bg-emerald-50 text-emerald-900 ring-2 ring-emerald-500/25 dark:border-emerald-400 dark:bg-emerald-950/40 dark:text-emerald-100'
                                                : 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300')
                                        }
                                        aria-pressed={data.currency === code}
                                    >
                                        {code}
                                    </button>
                                ))}
                            </div>
                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {t('warehouse_currency_pick_hint')}
                            </p>
                            <InputError message={errors.currency} className="mt-1" />
                        </FormField>
                        <FormField>
                            <InputLabel
                                value={`${t('warehouse_avg_cost')} (${priceLabel})`}
                                htmlFor="purchase_price"
                            />
                            <MoneyInput
                                id="purchase_price"
                                className={stockMoneyClass}
                                value={String(data.purchase_price ?? '')}
                                onValueChange={(next) => setData('purchase_price', next)}
                                allowDecimals={data.currency === 'USD'}
                            />
                            <InputError message={errors.purchase_price} className="mt-1" />
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
